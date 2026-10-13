<?php

namespace App\Services;

use App\Contracts\Repositories\CartRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\ProductSizeRepositoryInterface;
use App\Contracts\Repositories\ProductVariantRepositoryInterface;
use App\Exceptions\EmptyCartException;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private CartRepositoryInterface $cartRepository,
        private ProductSizeRepositoryInterface $productSizeRepository,
        private ProductVariantRepositoryInterface $productVariantRepository,
        private InventoryManager $inventoryManager,
    ) {}

    public function createDraftOrder(array $checkoutData, User $user): Order
    {
        // $attempts=3: dua concurrent checkouts locking the same ProductSize
        // row can trigger an InnoDB deadlock (SQLSTATE 40001) even though
        // only one of them should legitimately win — Laravel retries the
        // whole transaction automatically instead of surfacing a raw 500.
        return DB::transaction(function () use ($checkoutData, $user) {
            $existingDraft = $this->orderRepository->getActiveDraftForUserForUpdate($user->id);

            if ($existingDraft) {
                return $existingDraft;
            }

            $cart = $this->cartRepository->getForCheckout($user->id);

            if (! $cart || $cart->details->isEmpty()) {
                throw new EmptyCartException('Keranjang kosong');
            }

            $totalHarga = 0;
            $orderDetails = [];

            foreach ($cart->details as $detail) {
                if ($detail->product_variant_id) {
                    $prepared = $this->prepareVariantDetail($detail);
                } else {
                    $prepared = $this->prepareSizeDetail($detail);
                }

                $totalHarga += $prepared['subtotal'];
                $orderDetails[] = $prepared;
            }

            $order = $this->orderRepository->create([
                'user_id' => $user->id,
                'total_harga' => $totalHarga,
                'status' => 'draft',
                'expires_at' => now()->addMinutes(10),
                'name' => $checkoutData['name'] ?? null,
                'email' => $checkoutData['email'] ?? null,
                'phone' => $checkoutData['phone'] ?? null,
                'alamat' => $checkoutData['alamat'] ?? null,
                'message' => $checkoutData['message'] ?? null,
            ]);

            foreach ($orderDetails as $detail) {
                // 'subtotal' hanya untuk perhitungan di atas; kolomnya tidak ada
                // di order_details dan sengaja tidak dibuat fillable.
                $this->orderRepository->addDetail($order->id, array_diff_key($detail, ['subtotal' => null]));
            }

            AuditLogService::recordTransaction('checkout.create', $order, 'Draft order dibuat', [
                'order_id' => $order->id,
                'items' => array_map(fn ($d) => [
                    'product_id' => $d['product_id'],
                    'product_variant_id' => $d['product_variant_id'] ?? null,
                    'motif_name' => $d['motif_name'] ?? null,
                    'size' => $d['size'],
                    'qty' => $d['quantity'],
                    'harga' => $d['harga'],
                    'subtotal' => $d['subtotal'],
                ], $orderDetails),
                'total' => $totalHarga,
            ]);

            $cart->details()->delete();
            $cart->delete();

            return $order;
        }, 3);
    }

    /**
     * Baris keranjang dengan motif: harga & stok milik variasi, dan nama motif
     * ikut disimpan sebagai snapshot supaya order lama tetap terbaca walaupun
     * motif di katalog nanti diganti nama atau dihapus.
     */
    private function prepareVariantDetail($detail, $lockedVariant = null): array
    {
        $this->guardArchivedProduct($detail->product_id);

        $variant = $lockedVariant ?? $this->productVariantRepository->findForUpdate($detail->product_variant_id);

        if (! $variant || ! $variant->is_active) {
            throw new InsufficientStockException(
                'Motif sudah tidak tersedia',
                [
                    'product_id' => $detail->product_id,
                    'product_variant_id' => $detail->product_variant_id,
                    'requested_qty' => $detail->quantity,
                    'available' => 0,
                ]
            );
        }

        if ($variant->stock < $detail->quantity) {
            throw new InsufficientStockException(
                "Stok tidak cukup untuk {$detail->product->name} motif {$variant->name}",
                [
                    'product_id' => $detail->product_id,
                    'product_variant_id' => $variant->id,
                    'motif_name' => $variant->name,
                    'requested_qty' => $detail->quantity,
                    'available' => $variant->stock,
                ]
            );
        }

        $harga = (float) $variant->price;

        $this->inventoryManager->decrementVariant($variant->id, $detail->quantity);

        return [
            'product_id' => $detail->product_id,
            'product_variant_id' => $variant->id,
            'motif_name' => $variant->name,
            'size' => $variant->name,
            'quantity' => $detail->quantity,
            'harga' => $harga,
            'subtotal' => $harga * $detail->quantity,
        ];
    }

    /**
     * Produk diarsipkan (soft delete) tidak boleh dibelikan: meskipun produknya
     * masih ada di baris keranjang, produk ini sudah tidak ada di katalog.
     */
    private function guardArchivedProduct(int $productId): void
    {
        $archived = Product::withTrashed()->where('id', $productId)->whereNotNull('deleted_at')->exists();

        if ($archived) {
            throw new InsufficientStockException(
                'Produk ini sudah tidak tersedia',
                ['product_id' => $productId, 'requested_qty' => 1, 'available' => 0]
            );
        }
    }

    /**
     * Baris keranjang produk lama. Kalau produknya meantime sudah bermotif
     * (mis. setelah command migrasi), baris tanpa product_variant_id
     * dipetakan ke motif bernama sama supaya stok yang dikurangi tetap satu
     * sumber; kalau motifnya tidak ada, checkout ditolak dengan pesan jelas
     * alih-alih diam-diam mengurangi stok products_sizes yang sudah tidak
     * dipakai pembeli.
     */
    private function prepareSizeDetail($detail): array
    {
        $this->guardArchivedProduct($detail->product_id);

        $product = Product::find($detail->product_id);

        if ($product?->has_variants) {
            $variant = $this->productVariantRepository->findActiveByName($product->id, (string) $detail->size);

            if (! $variant) {
                throw new InsufficientStockException(
                    "Motif untuk {$product->name} tidak tersedia lagi, silakan pilih ulang motifnya",
                    [
                        'product_id' => $detail->product_id,
                        'requested_size' => $detail->size,
                        'requested_qty' => $detail->quantity,
                        'available' => 0,
                    ]
                );
            }

            return $this->prepareVariantDetail($detail, $variant);
        }

        $productSize = $this->productSizeRepository->findByProductAndSizeForUpdate($detail->product_id, $detail->size);

        if (! $productSize || $productSize->stock < $detail->quantity) {
            throw new InsufficientStockException(
                "Stok tidak cukup untuk {$detail->product->name} size {$detail->size}",
                [
                    'product_id' => $detail->product_id,
                    'size' => $detail->size,
                    'requested_qty' => $detail->quantity,
                    'available' => $productSize->stock ?? 0,
                ]
            );
        }

        $harga = (float) $detail->product->harga;

        $this->inventoryManager->decrement($productSize->id, $detail->quantity);

        return [
            'product_id' => $detail->product_id,
            'product_size_id' => $productSize->id,
            'size' => $detail->size,
            'quantity' => $detail->quantity,
            'harga' => $harga,
            'subtotal' => $harga * $detail->quantity,
        ];
    }
}