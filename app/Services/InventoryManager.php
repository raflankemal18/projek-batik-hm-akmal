<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\ProductSize;

class InventoryManager
{
    public static function decrement(int $productSizeId, int $qty): void
    {
        $productSize = ProductSize::where('id', $productSizeId)->first();
        $stockBefore = $productSize?->stock ?? 0;

        $affected = ProductSize::where('id', $productSizeId)
            ->where('stock', '>=', $qty)
            ->decrement('stock', $qty);

        if ($affected === 0) {
            throw new InsufficientStockException(
                "Stok tidak cukup untuk product size #{$productSizeId}",
                [
                    'product_id' => $productSize?->product_id,
                    'size' => $productSize?->size,
                    'requested_qty' => $qty,
                    'available' => $stockBefore,
                ]
            );
        }

        AuditLogService::recordTransaction('stock.decrement', $productSize, 'Stok dikurangi', [
            'product_id' => $productSize?->product_id,
            'size' => $productSize?->size,
            'qty' => $qty,
            'stock_before' => $stockBefore,
            'stock_after' => $stockBefore - $qty,
        ]);
    }

    public static function restore(int $productSizeId, int $qty, ?string $reason = null): void
    {
        $productSize = ProductSize::where('id', $productSizeId)->first();
        $stockBefore = $productSize?->stock ?? 0;

        ProductSize::where('id', $productSizeId)->increment('stock', $qty);

        AuditLogService::recordTransaction('stock.restore', $productSize, 'Stok dikembalikan', [
            'product_id' => $productSize?->product_id,
            'size' => $productSize?->size,
            'qty' => $qty,
            'stock_before' => $stockBefore,
            'stock_after' => $stockBefore + $qty,
            'reason' => $reason,
        ]);
    }
}
