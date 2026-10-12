<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ProductVariantRepositoryInterface;
use App\Models\ProductVariant;

class ProductVariantRepository extends BaseRepository implements ProductVariantRepositoryInterface
{
    protected function modelClass(): string
    {
        return ProductVariant::class;
    }

    public function findOrNull(int $id): ?ProductVariant
    {
        return $this->query()->where('id', $id)->first();
    }

    public function findForUpdate(int $id): ?ProductVariant
    {
        return $this->query()->where('id', $id)->lockForUpdate()->first();
    }

    public function findActiveForProduct(int $variantId, int $productId): ?ProductVariant
    {
        return $this->query()
            ->where('id', $variantId)
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->first();
    }

    public function findActiveByName(int $productId, string $name): ?ProductVariant
    {
        return $this->query()
            ->where('product_id', $productId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->where('is_active', true)
            ->first();
    }

    public function decrementIfAvailable(int $id, int $qty): int
    {
        return $this->query()
            ->where('id', $id)
            ->where('stock', '>=', $qty)
            ->decrement('stock', $qty);
    }

    public function increment(int $id, int $qty): void
    {
        $this->query()->where('id', $id)->increment('stock', $qty);
    }
}
