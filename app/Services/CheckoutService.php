<?php

namespace App\Services;

use App\Contracts\Repositories\CartRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\ProductSizeRepositoryInterface;
use App\Exceptions\EmptyCartException;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private CartRepositoryInterface $cartRepository,
        private ProductSizeRepositoryInterface $productSizeRepository,
        private InventoryManager $inventoryManager,
    ) {}

    public function createDraftOrder(array $checkoutData, User $user): Order
    {
        // $attempts=3: two concurrent checkouts locking the same ProductSize
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

                $harga = $detail->product->harga;
                $subtotal = $harga * $detail->quantity;
                $totalHarga += $subtotal;

                $this->inventoryManager->decrement($productSize->id, $detail->quantity);

                $orderDetails[] = [
                    'product_id' => $detail->product_id,
                    'product_size_id' => $productSize->id,
                    'size' => $detail->size,
                    'quantity' => $detail->quantity,
                    'harga' => $harga,
                    'subtotal' => $subtotal,
                ];
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
                $this->orderRepository->addDetail($order->id, array_diff_key($detail, ['subtotal' => null]));
            }

            AuditLogService::recordTransaction('checkout.create', $order, 'Draft order dibuat', [
                'order_id' => $order->id,
                'items' => array_map(fn ($d) => [
                    'product_id' => $d['product_id'],
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
}
