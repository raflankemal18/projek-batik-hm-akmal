<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\Product\StoreVariantRequest;
use App\Http\Requests\Admin\Product\UpdateVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AuditLogService;
use App\Services\ProductVariantService;

class ProductVariantController extends Controller
{
    public function __construct(private ProductVariantService $variantService) {}

    public function store(StoreVariantRequest $request, Product $product)
    {
        $this->authorize('manageVariants', $product);

        $variant = $this->variantService->create(
            $product,
            $request->validated(),
            $request->file('image'),
        );

        AuditLogService::recordAdmin('product.variant_created', $product, 'Motif kain ditambahkan', [
            'variant_id' => $variant->id,
            'name' => $variant->name,
            'price' => $variant->price,
            'stock' => $variant->stock,
        ]);

        return back()->with('success', 'Motif berhasil ditambahkan');
    }

    public function update(UpdateVariantRequest $request, Product $product, ProductVariant $variant)
    {
        $this->authorize('manageVariants', $product);
        abort_unless($variant->product_id === $product->id, 404);

        $before = $variant->only(['name', 'price', 'stock', 'is_active']);

        $updated = $this->variantService->update(
            $variant,
            $request->validated(),
            $request->file('image'),
        );

        AuditLogService::recordAdmin('product.variant_updated', $product, 'Motif kain diubah', [
            'variant_id' => $updated->id,
            'before' => $before,
            'after' => $updated->only(['name', 'price', 'stock', 'is_active']),
        ]);

        return back()->with('success', 'Motif berhasil diperbarui');
    }

    public function destroy(Request $request, Product $product, ProductVariant $variant)
    {
        $this->authorize('manageVariants', $product);
        abort_unless($variant->product_id === $product->id, 404);

        $context = [
            'variant_id' => $variant->id,
            'name' => $variant->name,
            'price' => $variant->price,
            'stock' => $variant->stock,
        ];

        $this->variantService->delete($variant);

        AuditLogService::recordAdmin('product.variant_deleted', $product, 'Motif kain dihapus', $context + [
            // Motif yang sudah pernah dibeli tidak dihapus fisik, hanya dinonaktifkan.
            'deactivated_instead' => ! $variant->exists,
        ]);

        return back()->with('success', 'Motif berhasil dihapus');
    }
}
