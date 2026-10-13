<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\Product\AddProductRequest;
use App\Http\Requests\Admin\Product\UpdateProductRequest;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(private ProductService $productService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        // Halaman admin butuh SEMUA motif (aktif & nonaktif); katalog publik tidak
        // boleh memakai cache karena stok motif berubah setiap kali motif diedit.
        $products = $this->productService->getAllPaginatedForAdmin(
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

        try {
            DB::beginTransaction();
            $product = $this->productService->create($request);

            AuditLogService::recordAdmin('product.create', $product, 'Produk baru', [
                'name' => $product->name,
                'harga' => $product->harga,
                'sizes' => $request->input('sizes'),
                'image_count' => count($request->file('images') ?? []),
            ]);
            DB::commit();

            return redirect()->route('admin.dashboard')->with('success', 'Product added successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['message' => 'Gagal menambahkan produk: ' . $e->getMessage()]);
        }
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $this->authorize('update', $product);

        $before = $product->only(['name', 'harga', 'description', 'category']);

        try {
            DB::beginTransaction();
            $updated = $this->productService->update($product->id, $request);

            AuditLogService::recordAdmin('product.update', $updated, 'Produk diubah', [
                'before' => $before,
                'after' => $updated->only(['name', 'harga', 'description', 'category']),
                'images_added' => count($request->file('images') ?? []),
            ]);
            DB::commit();

            return redirect()->back()->with('success', 'Produk berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['message' => 'Gagal memperbarui produk: ' . $e->getMessage()]);
        }
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        try {
            DB::beginTransaction();
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
            DB::commit();

            return redirect()->back()->with('success', 'Produk berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['message' => 'Gagal menghapus produk: ' . $e->getMessage()]);
        }
    }

    /**
     * Memulihkan produk dari arsip. Penghapusan produk sekarang berupa soft
     * delete (agar rincian order tidak ikut terhapus), jadi harus ada jalan
     * membatalkannya.
     */
    public function restore(Request $request, int $product)
    {
        // authorize dengan class (bukan instance) karena produk yang diarsipkan
        // tidak bisa diambil lewat route-model binding biasa.
        $this->authorize('updateAny', Product::class);

        $restored = $this->productService->restore($product);

        AuditLogService::recordAdmin('product.restore', $restored, 'Produk dipulihkan dari arsip', [
            'name' => $restored->name,
        ]);

        return redirect()->route('admin.products')->with('success', 'Produk berhasil dipulihkan');
    }

    /**
     * Layar arsip: produk yang di-soft delete. query `archived=1` untuk melihatnya.
     */
    public function archived(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $products = $this->productService->getArchivedPaginated(12, $request->query('search'));

        return Inertia::render('admin/Products', [
            'products' => $products,
            'archived' => true,
        ]);
    }

    public function bulkDelete(Request $request)
    {
        $this->authorize('deleteAny', Product::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:products,id'
        ]);

        $ids = $request->ids;
        
        try {
            DB::beginTransaction();
            foreach ($ids as $id) {
                $product = Product::find($id);
                if ($product) {
                    $name = $product->name;
                    $harga = $product->harga;
                    $imageCount = $product->images()->count();

                    $this->productService->delete($product->id);

                    AuditLogService::recordAdmin('product.delete', $product, 'Produk dihapus via bulk action', [
                        'name' => $name,
                        'harga' => $harga,
                        'image_count' => $imageCount,
                    ]);
                }
            }
            DB::commit();

            return redirect()->back()->with('success', count($ids) . ' produk berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['message' => 'Gagal menghapus produk: ' . $e->getMessage()]);
        }
    }
}
