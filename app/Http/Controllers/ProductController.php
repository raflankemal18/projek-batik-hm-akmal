<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\Product\AddProductRequest;
use App\Http\Requests\Admin\Product\UpdateProductRequest;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(private ProductService $productService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $products = $this->productService->getAllPaginated(
            12,
            $request->query('search'),
            $request->query('sort', 'created_at'),
            $request->query('direction', 'desc'),
        );

        return Inertia::render('admin/Products', ['products' => $products]);
    }

    public function store(AddProductRequest $request)
    {
        $this->authorize('create', Product::class);

        $product = $this->productService->create($request);

        AuditLogService::recordAdmin('product.create', $product, 'Produk baru', [
            'name' => $product->name,
            'harga' => $product->harga,
            'sizes' => $request->input('sizes'),
            'image_count' => count($request->file('images') ?? []),
        ]);

        return redirect()->route('admin.dashboard')->with('success', 'Product added successfully!');
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $this->authorize('update', $product);

        $before = $product->only(['name', 'harga', 'description', 'category']);

        $updated = $this->productService->update($product->id, $request);

        AuditLogService::recordAdmin('product.update', $updated, 'Produk diubah', [
            'before' => $before,
            'after' => $updated->only(['name', 'harga', 'description', 'category']),
            'images_added' => count($request->file('images') ?? []),
        ]);

        return redirect()->back()->with('success', 'Produk berhasil diperbarui');
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $name = $product->name;
        $harga = $product->harga;
        $imageCount = $product->images()->count();

        $this->productService->delete($product->id);

        // $product is still a valid in-memory PHP object here (the DB row is
        // gone, but the instance isn't) — pass it as subject so subject_id
        // stays queryable even after the product itself no longer exists.
        AuditLogService::recordAdmin('product.delete', $product, 'Produk dihapus', [
            'name' => $name,
            'harga' => $harga,
            'image_count' => $imageCount,
        ]);

        return redirect()->back()->with('success', 'Produk berhasil dihapus');
    }
}
