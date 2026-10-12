<?php

namespace App\Services;

use App\Contracts\Repositories\ProductSizeRepositoryInterface;
use App\Contracts\Repositories\ProductVariantRepositoryInterface;
use App\Exceptions\InsufficientStockException;

class InventoryManager
{
    public function __construct(
        private ProductSizeRepositoryInterface $productSizeRepository,
        private ProductVariantRepositoryInterface $productVariantRepository,
    ) {}

    /**
     * Jalur lama: produk tanpa motif (products_sizes). Dipertahankan supaya
     * order lama & produk yang belum dimigrasi tetap bisa diproses.
     */
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

    /** Jalur motif: stok milik variasi, pesan error menyebut nama motif (bukan ID). */
    public function decrementVariant(int $variantId, int $qty): void
    {
        $variant = $this->productVariantRepository->findOrNull($variantId);
        $stockBefore = $variant?->stock ?? 0;

        $affected = $this->productVariantRepository->decrementIfAvailable($variantId, $qty);

        if ($affected === 0) {
            $motif = $variant?->name ?? "variasi #{$variantId}";

            throw new InsufficientStockException(
                "Stok tidak cukup untuk motif {$motif}",
                [
                    'product_id' => $variant?->product_id,
                    'product_variant_id' => $variantId,
                    'motif_name' => $variant?->name,
                    'requested_qty' => $qty,
                    'available' => $stockBefore,
                ]
            );
        }

        AuditLogService::recordTransaction('stock.decrement', $variant, 'Stok motif dikurangi', [
            'product_id' => $variant?->product_id,
            'product_variant_id' => $variantId,
            'motif_name' => $variant?->name,
            'qty' => $qty,
            'stock_before' => $stockBefore,
            'stock_after' => $stockBefore - $qty,
        ]);
    }

    public function restoreVariant(int $variantId, int $qty, ?string $reason = null): void
    {
        $variant = $this->productVariantRepository->findOrNull($variantId);
        $stockBefore = $variant?->stock ?? 0;

        $this->productVariantRepository->increment($variantId, $qty);

        AuditLogService::recordTransaction('stock.restore', $variant, 'Stok motif dikembalikan', [
            'product_id' => $variant?->product_id,
            'product_variant_id' => $variantId,
            'motif_name' => $variant?->name,
            'qty' => $qty,
            'stock_before' => $stockBefore,
            'stock_after' => $stockBefore + $qty,
            'reason' => $reason,
        ]);
    }
}