<?php

namespace App\Services;

use App\Contracts\Repositories\CartRepositoryInterface;
use App\Exceptions\InsufficientStockException;
use App\Http\Requests\Cart\CartAddRequest;
use App\Http\Requests\Cart\CartQuantityRequest;
use App\Models\ProductSize;
use App\Models\User;

class CartService
{
    public function __construct(private CartRepositoryInterface $cartRepository) {}

    public function add(CartAddRequest $request, User $user): void
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
