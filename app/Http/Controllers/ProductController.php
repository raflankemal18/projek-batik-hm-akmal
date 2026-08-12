<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\Product\AddProductRequest;
use App\Http\Requests\Admin\Product\UpdateProductRequest;
use App\Models\Product;
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

        $this->productService->create($request);

        return redirect()->route('admin.dashboard')->with('success', 'Product added successfully!');
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $this->authorize('update', $product);

        $this->productService->update($product->id, $request);

        return redirect()->back()->with('success', 'Produk berhasil diperbarui');
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $this->productService->delete($product->id);

        return redirect()->back()->with('success', 'Produk berhasil dihapus');
    }
}
