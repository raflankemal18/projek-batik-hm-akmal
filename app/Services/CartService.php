<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\Cart\CartAddRequest;
use App\Http\Requests\Cart\CartQuantityRequest;
use App\Models\Cart;
use App\Models\CartDetail;
use App\Models\ProductSize;
use App\Models\User;

class CartService
{
    public function add(CartAddRequest $request, User $user): void
    {
        $productSize = ProductSize::where('product_id', $request->product_id)
            ->where('size', $request->size)
            ->firstOrFail();

        if ($productSize->stock < $request->quantity) {
            throw new InsufficientStockException('Stok tidak cukup');
        }

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        $existing = CartDetail::where('cart_id', $cart->id)
            ->where('product_id', $request->product_id)
            ->where('size', $request->size)
            ->first();

        if ($existing) {
            $newQty = $existing->quantity + $request->quantity;
            if ($newQty > $productSize->stock) {
                throw new InsufficientStockException('Jumlah melebihi stok tersedia');
            }
            $existing->update(['quantity' => $newQty]);
        } else {
            CartDetail::create([
                'cart_id' => $cart->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'size' => $request->size,
            ]);
        }
    }

    public function updateQuantity(CartQuantityRequest $request, User $user): void
    {
        $cartDetail = CartDetail::where('id', $request->cart_detail_id)
            ->whereHas('cart', fn ($q) => $q->where('user_id', $user->id))
            ->firstOrFail();

        $productSize = ProductSize::where('product_id', $cartDetail->product_id)
            ->where('size', $cartDetail->size)
            ->first();

        if (! $productSize || $request->quantity > $productSize->stock) {
            throw new InsufficientStockException('Stok tidak cukup');
        }

        $cartDetail->update(['quantity' => $request->quantity]);
    }

    public function remove(int $cartDetailId, User $user): void
    {
        $cartDetail = CartDetail::where('id', $cartDetailId)
            ->whereHas('cart', fn ($q) => $q->where('user_id', $user->id))
            ->firstOrFail();

        $cartDetail->delete();
    }

    public function getCartWithProducts(User $user)
    {
        return Cart::with(['details.product.images', 'user'])
            ->where('user_id', $user->id)
            ->get();
    }
}
