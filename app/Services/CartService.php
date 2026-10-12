<?php

namespace App\Services;

use App\Contracts\Repositories\CartRepositoryInterface;
use App\Contracts\Repositories\ProductVariantRepositoryInterface;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Http\Requests\Cart\CartAddRequest;
use App\Http\Requests\Cart\CartQuantityRequest;
use App\Models\ProductSize;
use App\Models\User;

class CartService
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private ProductVariantRepositoryInterface $productVariantRepository,
    ) {}

    public function add(CartAddRequest $request, User $user): void
    {
        $variantId = $request->input('product_variant_id');

        if ($variantId !== null) {
            $this->addVariant($request, $user, (int) $variantId);

            return;
        }

        $product = Product::findOrFail($request->product_id);

        // Produk bermotif TIDAK boleh dibeli lewat jalur ukuran: products_sizes
        // dan product_variants adalah dua catatan stok terpisah, jadi membiarkan
        // keduanya bisa terjual membuat stok fisik dan stok yang tampil berbeda.
        // Sampai halaman detail produk menampilkan motif (P6), permintaan lama
        // yang hanya mengirim `size` diteruskan ke motif dengan nama yang sama —
        // jadi tetap ada SATU sumber stok, yaitu product_variants.
        if ($product->has_variants) {
            $variant = $this->productVariantRepository->findActiveByName($product->id, (string) $request->size);

            if (! $variant) {
                throw new InsufficientStockException(
                    'Motif tidak tersedia untuk produk ini',
                    [
                        'product_id' => $product->id,
                        'requested_size' => $request->size,
                        'requested_qty' => $request->quantity,
                        'available' => 0,
                    ]
                );
            }

            $this->addVariant($request, $user, $variant->id);

            return;
        }

        $this->addSize($request, $user);
    }

    /**
     * Motif: variasi wajib milik produk yang dipilih dan masih aktif. Baris keranjang
     * diidentifikasi oleh (produk + variasi), bukan teks size.
     */
    private function addVariant(CartAddRequest $request, User $user, int $variantId): void
    {
        $variant = $this->productVariantRepository->findActiveForProduct($variantId, (int) $request->product_id);

        if (! $variant) {
            throw new InsufficientStockException(
                'Motif tidak tersedia untuk produk ini',
                [
                    'product_id' => $request->product_id,
                    'product_variant_id' => $variantId,
                    'requested_qty' => $request->quantity,
                    'available' => 0,
                ]
            );
        }

        if ($variant->stock < $request->quantity) {
            throw new InsufficientStockException("Stok tidak cukup untuk motif {$variant->name}", [
                'product_id' => $request->product_id,
                'product_variant_id' => $variant->id,
                'motif_name' => $variant->name,
                'requested_qty' => $request->quantity,
                'available' => $variant->stock,
            ]);
        }

        $cart = $this->cartRepository->getOrCreateForUser($user->id);

        $existing = $this->cartRepository->findDetailForVariant($cart->id, (int) $request->product_id, $variant->id);

        if ($existing) {
            $newQty = $existing->quantity + $request->quantity;

            if ($newQty > $variant->stock) {
                throw new InsufficientStockException("Jumlah melebihi stok motif {$variant->name}");
            }

            $this->cartRepository->updateDetail($existing->id, ['quantity' => $newQty]);

            return;
        }

        $this->cartRepository->addDetail($cart->id, [
            'product_id' => $request->product_id,
            'product_variant_id' => $variant->id,
            // kolom size legacy tetap diisi agar halaman keranjang lama & data
            // historis tidak kosong; order detail motif memakai motif_name.
            'size' => $variant->name,
            'quantity' => $request->quantity,
        ]);
    }

    /** Jalur lama: produk tanpa motif, memilih ukuran (products_sizes). */
    private function addSize(CartAddRequest $request, User $user): void
    {
        $productSize = ProductSize::where('product_id', $request->product_id)
            ->where('size', $request->size)
            ->firstOrFail();

        if ($productSize->stock < $request->quantity) {
            throw new InsufficientStockException('Stok tidak cukup');
        }

        $cart = $this->cartRepository->getOrCreateForUser($user->id);

        $existing = $this->cartRepository->findDetailForProduct($cart->id, $request->product_id, $request->size);

        if ($existing) {
            $newQty = $existing->quantity + $request->quantity;
            if ($newQty > $productSize->stock) {
                throw new InsufficientStockException('Jumlah melebihi stok tersedia');
            }
            $this->cartRepository->updateDetail($existing->id, ['quantity' => $newQty]);
        } else {
            $this->cartRepository->addDetail($cart->id, [
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'size' => $request->size,
            ]);
        }
    }

    public function updateQuantity(CartQuantityRequest $request, User $user): void
    {
        $cartDetail = $this->cartRepository->findDetailOwned($request->cart_detail_id, $user->id);

        // Baris yang sudah terhubung ke motif dicek terhadap stok motifnya.
        $variantId = $cartDetail->product_variant_id;

        // Baris lama (belum punya product_variant_id) pada produk yang meantime
        // sudah bermotif diarahkan ke motif bernama sama, supaya validasi stok
        // tetap membaca satu sumber stok yang sama.
        if (! $variantId) {
            $product = Product::find($cartDetail->product_id);

            if ($product?->has_variants) {
                $variantId = $this->productVariantRepository->findActiveByName($product->id, (string) $cartDetail->size)?->id;
            }
        }

        if ($variantId) {
            $variant = $this->productVariantRepository->findActiveForProduct($variantId, $cartDetail->product_id);

            if (! $variant || $request->quantity > $variant->stock) {
                throw new InsufficientStockException('Stok tidak cukup');
            }

            $this->cartRepository->updateDetail($cartDetail->id, [
                'quantity' => $request->quantity,
                'product_variant_id' => $variant->id,
            ]);

            return;
        }

        $productSize = ProductSize::where('product_id', $cartDetail->product_id)
            ->where('size', $cartDetail->size)
            ->first();

        if (! $productSize || $request->quantity > $productSize->stock) {
            throw new InsufficientStockException('Stok tidak cukup');
        }

        $this->cartRepository->updateDetail($cartDetail->id, ['quantity' => $request->quantity]);
    }

    public function remove(int $cartDetailId, User $user): void
    {
        $cartDetail = $this->cartRepository->findDetailOwned($cartDetailId, $user->id);

        $this->cartRepository->removeDetail($cartDetail->id);
    }

    public function getCartWithProducts(User $user)
    {
        $cart = $this->cartRepository->getWithDetails($user->id);

        return $cart ? collect([$cart]) : collect();
    }
}