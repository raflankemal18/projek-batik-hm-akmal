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
            ->with(['details.product.images', 'details.variant', 'user'])
            ->where('user_id', $userId)
            ->first();
    }

    public function getForCheckout(int $userId): ?Cart
    {
        return $this->query()
            ->with(['details.product'])
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();
    }

    public function addDetail(int $cartId, array $data): CartDetail
    {
        return CartDetail::create(array_merge(['cart_id' => $cartId], $data));
    }

    public function findDetailForProduct(int $cartId, int $productId, string $size): ?CartDetail
    {
        return CartDetail::where('cart_id', $cartId)
            ->where('product_id', $productId)
            ->where('size', $size)
            ->first();
    }

    public function findDetailForVariant(int $cartId, int $productId, ?int $productVariantId): ?CartDetail
    {
        // Identitas baris = produk + variasi. Dua motif berbeda dari produk yang
        // sama TIDAK digabung; motif yang sama menambah kuantitas barisnya.
        $query = CartDetail::where('cart_id', $cartId)->where('product_id', $productId);

        return $productVariantId === null
            ? $query->whereNull('product_variant_id')->first()
            : $query->where('product_variant_id', $productVariantId)->first();
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
