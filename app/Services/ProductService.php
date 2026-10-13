<?php

namespace App\Services;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Http\Requests\Admin\Product\AddProductRequest;
use App\Http\Requests\Admin\Product\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private ImageService $imageService,
    ) {}

    public function getAllPaginated(int $perPage = 12, ?string $search = null, string $sort = 'created_at', string $direction = 'desc', ?string $category = null)
    {
        $isDefault = ! $search && ! $category
            && $sort === 'created_at' && strtolower($direction) === 'desc';

        if (! $isDefault) {
            return $this->productRepository->getAllPaginated($perPage, $search, $sort, $direction, $category);
        }

        return Cache::remember('products.catalog.'.$perPage, 300, function () use ($perPage) {
            return $this->productRepository->getAllPaginated($perPage);
        });
    }

    /** Daftar produk untuk halaman admin (semua motif, tanpa cache katalog). */
    public function getAllPaginatedForAdmin(int $perPage = 12, ?string $search = null, string $sort = 'created_at', string $direction = 'desc')
    {
        return $this->productRepository->getAllPaginatedForAdmin($perPage, $search, $sort, $direction);
    }

    public function getArchivedPaginated(int $perPage = 12, ?string $search = null)
    {
        return $this->productRepository->getArchivedPaginated($perPage, $search);
    }

    public function findWithRelations(int $id, array $relations = ['sizes', 'images', 'activeVariants'])
    {
        return $this->productRepository->findWithRelations($id, $relations);
    }

    public function getTopSelling(int $limit = 5)
    {
        return $this->productRepository->getTopSelling($limit);
    }

    public function create(AddProductRequest $request): Product
    {
        return DB::transaction(function () use ($request) {
            $product = $this->productRepository->create([
                'name' => $request->name,
                'harga' => $request->harga,
                'description' => $request->description,
                'category' => $request->category,
                'fabric_size' => $request->fabric_size,
                'has_variants' => $request->boolean('has_variants'),
            ]);

            if (! $product->has_variants) {
                $this->syncSizes($product, $request->input('sizes'));
            }
            $this->storeImages($product, $request->file('images'));

            $this->forgetCatalogCache();

            return $product;
        });
    }

    public function update(int $id, UpdateProductRequest $request): Product
    {
        return DB::transaction(function () use ($id, $request) {
            $product = $this->productRepository->update($id, [
                'name' => $request->name,
                'harga' => $request->harga,
                'description' => $request->description,
                'category' => $request->category,
                'fabric_size' => $request->fabric_size,
                'has_variants' => $request->boolean('has_variants'),
            ]);

            // Produk bermotif tidak boleh menyentuh products_sizes lagi: baris
            // itu masih dirujuk order_details.product_size_id (histori), dan
            // menghapus/mengganti-nya akan membuat order lama kehilangan rujukan
            // stoknya. Variasi dikelola lewat endpoint motif terpisah.
            if (! $product->has_variants) {
                $product->sizes()->delete();
                $this->syncSizes($product, $request->input('sizes'));
            }

            $this->storeImages($product, $request->file('images'));

            $this->forgetCatalogCache();

            return $product;
        });
    }

    /**
     * MengARSIPKAN produk (soft delete), bukan menghapus barisnya.
     *
     * Kenapa tidak hard delete: `order_details.product_id` memakai FK cascade,
     * jadi produk yang dihapus fisik ikut menarik baris rincian item pada
     * setiap order — termasuk order yang sudah dibayar. Berkas gambar, ukuran,
     * dan motif juga sengaja disimpan supaya produk bisa dipulihkan kembali dan
     * histori tidak kehilangan konteks.
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $product = $this->productRepository->findWithRelations($id, ['images']);

            $this->forgetCatalogCache();

            return $product->delete();
        });
    }

    /** Memulihkan produk dari arsip. */
    public function restore(int $id): Product
    {
        $product = $this->productRepository->findWithTrashed($id);
        $product->restore();

        $this->forgetCatalogCache();

        return $product;
    }

    private function syncSizes(Product $product, array $sizes): void
    {
        collect($sizes)->map(function ($size) {
            return is_string($size) ? json_decode($size, true) : $size;
        })->each(function ($size) use ($product) {
            if ($size && isset($size['size'])) {
                $product->sizes()->create([
                    'size' => $size['size'],
                    'stock' => $size['stock'] ?? 0,
                ]);
            }
        });
    }

    private function storeImages(Product $product, ?array $images): void
    {
        if ($images === null) {
            return;
        }

        foreach ($images as $image) {
            if ($image) {
                $path = $this->imageService->storeUploaded($image, 'public/product_images');
                $product->images()->create(['image_path' => $path]);
            }
        }
    }

    /**
     * Dibuat public supaya service lain (mis. ProductVariantService) bisa
     * memakai invalidasi yang sama: katalog default di-cache 300 detik, jadi
     * perubahan harga/stok motif diam-diam akan tampil basi di katalog.
     */
    public function forgetCatalogCache(): void
    {
        Cache::forget('products.catalog.12');
    }
}
