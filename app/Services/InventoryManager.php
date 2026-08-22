<?php

namespace App\Services;

use App\Contracts\Repositories\ProductSizeRepositoryInterface;
use App\Exceptions\InsufficientStockException;

class InventoryManager
{
    public function __construct(private ProductSizeRepositoryInterface $productSizeRepository) {}

    public function decrement(int $productSizeId, int $qty): void
    {
        $productSize = $this->productSizeRepository->findOrNull($productSizeId);
        $stockBefore = $productSize?->stock ?? 0;

        $affected = $this->productSizeRepository->decrementIfAvailable($productSizeId, $qty);

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

    public function restore(int $productSizeId, int $qty, ?string $reason = null): void
    {
        $productSize = $this->productSizeRepository->findOrNull($productSizeId);
        $stockBefore = $productSize?->stock ?? 0;

        $this->productSizeRepository->increment($productSizeId, $qty);

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
