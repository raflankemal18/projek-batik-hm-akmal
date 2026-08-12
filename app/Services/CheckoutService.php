<?php

namespace App\Services;

use App\Exceptions\EmptyCartException;
use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\ProductSize;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function createDraftOrder(array $checkoutData, User $user): Order
    {
        return DB::transaction(function () use ($checkoutData, $user) {
            $existingDraft = Order::where('user_id', $user->id)
                ->where('status', 'draft')
                ->lockForUpdate()
                ->first();

            if ($existingDraft) {
                return $existingDraft;
            }

            $cart = Cart::where('user_id', $user->id)
                ->with(['details.product'])
                ->lockForUpdate()
                ->first();

            if (! $cart || $cart->details->isEmpty()) {
                throw new EmptyCartException('Keranjang kosong');
            }

            $totalHarga = 0;
            $orderDetails = [];

            foreach ($cart->details as $detail) {
                $productSize = ProductSize::where('product_id', $detail->product_id)
                    ->where('size', $detail->size)
                    ->lockForUpdate()
                    ->first();

                if (! $productSize || $productSize->stock < $detail->quantity) {
                    throw new InsufficientStockException(
                        "Stok tidak cukup untuk {$detail->product->name} size {$detail->size}"
                    );
                }

                $harga = $detail->product->harga;
                $subtotal = $harga * $detail->quantity;
                $totalHarga += $subtotal;

                InventoryManager::decrement($productSize->id, $detail->quantity);

                $orderDetails[] = [
                    'product_id' => $detail->product_id,
                    'product_size_id' => $productSize->id,
                    'size' => $detail->size,
                    'quantity' => $detail->quantity,
                    'harga' => $harga,
                ];
            }

            $order = Order::create([
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
                OrderDetail::create(array_merge($detail, ['order_id' => $order->id]));
            }

            $cart->details()->delete();
            $cart->delete();

            return $order;
        });
    }
}
