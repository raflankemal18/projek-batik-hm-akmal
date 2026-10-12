<?php

namespace App\Console\Commands;

use App\Models\CartDetail;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mengubah model lama "pilihan ukuran" (products_sizes) menjadi model motif
 * (product_variants) — keputusan 2026-08-13, lihat docs/planning-variasi-motif-batik.md.
 *
 * Berjalan di luar Laravel's migrate karena ini transformasi DATA, bukan skema,
 * dan harus bisa dijalankan ulang (idempotent) serta dipratinjau dulu.
 *
 * Yang dilakukan:
 *   1. Setiap baris products_sizes menjadi satu product_variants
 *      (name = nilai size lama, price = products.harga, stock disalin).
 *   2. products.has_variants = true untuk produk yang punya minimal satu baris ukuran.
 *   3. cart_details.product_variant_id diisi dari pasangan (product, size) yang cocok.
 *
 * Yang SENGAJA tidak diubah:
 *   - products_sizes tidak dihapus: order_details.product_size_id dari order lama
 *     masih merujuk tabel itu, dan menghapusnya merusak histori pesanan.
 *   - Baris cart_details yang size-nya sudah tidak ada di katalog tidak dihapus
 *     (isi keranjang user tidak hilang diam-diam), tapi dibiarkan tanpa variasi
 *     sehingga tidak bisa di-checkout sampai admin mencocokkan moturnya.
 */
class MigrateSizesToVariantsCommand extends Command
{
    protected $signature = 'products:migrate-sizes-to-variants
        {--dry-run : Tampilkan rencana perubahan tanpa menulis ke database}
        {--product=* : Batasi migrasi ke produk tertentu (boleh diulang), mis. --product=3 --product=7}
        {--force : Lewati konfirmasi interaktif}';

    protected $description = 'Ubah products_sizes lama menjadi product_variants (motif)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyProductIds = collect($this->option('product'))->map(fn ($id) => (int) $id)->filter()->all();

        if ($dryRun) {
            $this->info('Mode dry-run — tidak ada yang ditulis ke database.');
        }

        if (! empty($onlyProductIds)) {
            $this->info('Dibatasi ke produk: '.implode(', ', $onlyProductIds).' (produk lain dilewati).');
        }

        // Konfirmasi eksplisit: command ini mengubah ARTINYA produk — ukuran S/M/L
        // menjadi nama motif. Salah menjalankannya pada produk yang ukuran itu memang
        // penting membuat nama motifnya tidak masuk akal (mis. produk baju punya
        // motif bernama "XL").
        // Karena itu jalankan per produk lebih dulu dengan --product, atau --dry-run.
        if (! $dryRun && ! $this->option('force') && ! $this->confirm(
            'Ukuran lama akan menjadi NAMA MOTIF pada produk yang ditandai. Lanjutkan?',
            false
        )) {
            $this->warn('Dibatalkan. Tidak ada data yang diubah.');

            return self::SUCCESS;
        }

        $createdVariants = 0;
        $skippedVariants = 0;
        $linkedCartDetails = 0;
        $unlinkedCartDetails = 0;
        $flaggedProducts = 0;

        DB::transaction(function () use ($dryRun, $onlyProductIds, &$createdVariants, &$skippedVariants, &$linkedCartDetails, &$unlinkedCartDetails, &$flaggedProducts) {
            $products = Product::query()->with('sizes')
                ->when($onlyProductIds, fn ($q) => $q->whereIn('id', $onlyProductIds))
                ->get();

            $this->info('Produk diperiksa: '.$products->count());

            foreach ($products as $product) {
                $existingVariantNames = ProductVariant::where('product_id', $product->id)
                    ->pluck('name')
                    ->map(fn ($name) => mb_strtolower(trim($name)))
                    ->all();

                foreach ($product->sizes as $size) {
                    $key = mb_strtolower(trim($size->size));

                    if (in_array($key, $existingVariantNames, true)) {
                        // Sudah pernah dimigrasikan — lewati supaya aman dijalankan ulang.
                        $skippedVariants++;
                        continue;
                    }

                    $this->line(sprintf(
                        '  %s: motif "%s" (harga %s, stok %d)',
                        $dryRun ? 'akan dibuat' : 'dibuat',
                        $size->size,
                        $product->harga,
                        $size->stock,
                    ));

                    if (! $dryRun) {
                        ProductVariant::create([
                            'product_id' => $product->id,
                            'name' => $size->size,
                            'price' => $product->harga,
                            'stock' => $size->stock,
                            'is_active' => true,
                        ]);
                        $existingVariantNames[] = $key;
                    }

                    $createdVariants++;
                }

                if ($product->sizes->isNotEmpty() && ! $product->has_variants) {
                    if (! $dryRun) {
                        $product->update(['has_variants' => true]);
                    }
                    $flaggedProducts++;
                }
            }

            // Pasangkan baris keranjang lama ke variasi yang namanya sama.
            if (! $dryRun) {
                $variantsByProduct = ProductVariant::query()
                    ->get()
                    ->groupBy('product_id');

                CartDetail::query()->whereNull('product_variant_id')->chunkById(200, function ($details) use ($variantsByProduct, &$linkedCartDetails, &$unlinkedCartDetails) {
                    foreach ($details as $detail) {
                        $match = collect($variantsByProduct->get($detail->product_id, collect()))
                            ->first(fn ($variant) => mb_strtolower(trim($variant->name)) === mb_strtolower(trim($detail->size)));

                        if ($match) {
                            $detail->update(['product_variant_id' => $match->id]);
                            $linkedCartDetails++;
                        } else {
                            $unlinkedCartDetails++;
                        }
                    }
                });
            }
        });

        $this->newLine();
        $this->info(sprintf(
            '%s: %d motif dibuat, %d dilewati (sudah ada), %d produk ditandai has_variants.',
            $dryRun ? 'Rencana' : 'Selesai',
            $createdVariants,
            $skippedVariants,
            $flaggedProducts,
        ));

        if ($dryRun) {
            $this->line('Baris keranjang lama akan dipetakan ke variasi dengan nama yang sama; sisanya dibiarkan (tidak dihapus).');
        } else {
            $this->line(sprintf(
                'Keranjang: %d baris dipetakan ke motif, %d baris tanpa pasangan (tidak dihapus, perlu dicocokkan admin).',
                $linkedCartDetails,
                $unlinkedCartDetails,
            ));
        }

        $this->comment('Catatan: tabel products_sizes tetap dipakai untuk order lama (order_details.product_size_id).');

        return self::SUCCESS;
    }
}