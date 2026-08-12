<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\ProductSize;

class InventoryManager
{
    public static function decrement(int $productSizeId, int $qty): void
    {
        $affected = ProductSize::where('id', $productSizeId)
            ->where('stock', '>=', $qty)
            ->decrement('stock', $qty);

        if ($affected === 0) {
            throw new InsufficientStockException(
                "Stok tidak cukup untuk product size #{$productSizeId}"
            );
        }
    }

    public static function restore(int $productSizeId, int $qty): void
    {
        ProductSize::where('id', $productSizeId)->increment('stock', $qty);
    }
}
