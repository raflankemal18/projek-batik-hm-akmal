<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ProductSizeRepositoryInterface;
use App\Models\ProductSize;

class ProductSizeRepository extends BaseRepository implements ProductSizeRepositoryInterface
{
    protected function modelClass(): string
    {
        return ProductSize::class;
    }

    public function findOrNull(int $id): ?ProductSize
    {
        return $this->query()->where('id', $id)->first();
    }

    public function findByProductAndSizeForUpdate(int $productId, string $size): ?ProductSize
    {
        return $this->query()
            ->where('product_id', $productId)
            ->where('size', $size)
            ->lockForUpdate()
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
