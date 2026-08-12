<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\CartRepositoryInterface;
use App\Models\Cart;
use App\Models\CartDetail;

class CartRepository extends BaseRepository implements CartRepositoryInterface
{
    protected function modelClass(): string
    {
        return Cart::class;
    }

    public function getOrCreateForUser(int $userId): Cart
    {
        return Cart::firstOrCreate(['user_id' => $userId]);
    }

    public function getWithDetails(int $userId)
    {
        return $this->query()
            ->with(['details.product.images', 'user'])
            ->where('user_id', $userId)
            ->first();
    }

    public function addDetail(int $cartId, array $data): CartDetail
    {
        return CartDetail::create(array_merge(['cart_id' => $cartId], $data));
    }

    public function findDetailOwned(int $cartDetailId, int $userId): CartDetail
    {
        return CartDetail::where('id', $cartDetailId)
            ->whereHas('cart', fn ($q) => $q->where('user_id', $userId))
            ->firstOrFail();
    }

    public function updateDetail(int $cartDetailId, array $data): CartDetail
    {
        $detail = CartDetail::findOrFail($cartDetailId);
        $detail->update($data);

        return $detail;
    }

    public function removeDetail(int $cartDetailId): bool
    {
        return CartDetail::findOrFail($cartDetailId)->delete();
    }

    public function clearForUser(int $userId): void
    {
        Cart::where('user_id', $userId)->delete();
    }
}
