<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ProductVariantService
{
    public function __construct(
        private ImageService $imageService,
        private ProductService $productService,
    ) {}

    public function create(Product $product, array $data, ?UploadedFile $image = null): ProductVariant
    {
        $variant = DB::transaction(function () use ($product, $data, $image) {
            $variant = $product->variants()->create([
                'name' => $data['name'],
                'price' => $data['price'],
                'stock' => $data['stock'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            if ($image) {
                $variant->update([
                    'image_path' => $this->imageService->storeUploaded($image, 'public/product_variant_images'),
                ]);
            }

            // Produk yang punya motif wajib memakai model motif, supaya frontend
            // menampilkan pilihan motif dan bukan blok ukuran.
            if (! $product->has_variants) {
                $product->update(['has_variants' => true]);
            }

            return $variant;
        });

        $this->productService->forgetCatalogCache();

        return $variant;
    }

    public function update(ProductVariant $variant, array $data, ?UploadedFile $image = null): ProductVariant
    {
        DB::transaction(function () use ($variant, $data, $image) {
            $variant->update([
                'name' => $data['name'],
                'price' => $data['price'],
                'stock' => $data['stock'],
                'is_active' => $data['is_active'] ?? $variant->is_active,
            ]);

            if ($image) {
                $old = $variant->image_path;
                $variant->update([
                    'image_path' => $this->imageService->storeUploaded($image, 'public/product_variant_images'),
                ]);
                $this->imageService->delete($old);
            }
        });

        $this->productService->forgetCatalogCache();

        return $variant->refresh();
    }

    /**
     * Motif yang pernah dipakai order ATAU pembayaran tidak dihapus fisik:
     * order_details dan payment_details masih menunjuk baris itu, dan histori
     * pesanan harus tetap bisa dibaca. Motif seperti ini hanya dinonaktifkan supaya
     * tidak bisa dipilih lagi.
     */
    public function delete(ProductVariant $variant): void
    {
        DB::transaction(function () use ($variant) {
            $isUsed = $variant->orderDetails()->exists()
                || $variant->cartDetails()->exists()
                || $variant->paymentDetails()->exists();

            if ($isUsed) {
                $variant->update(['is_active' => false]);

                return;
            }

            $imagePath = $variant->image_path;
            $variant->delete();
            $this->imageService->delete($imagePath);
        });

        $this->productService->forgetCatalogCache();
    }
}
