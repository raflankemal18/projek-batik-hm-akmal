<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Variasi motif kain: satu produk punya banyak motif, tiap motif punya harga
     * dan stok sendiri (keputusan 2026-08-13, lihat docs/planning-variasi-motif-batik.md).
     *
     * Sengaja tabel BARU, bukan memakai ulang `products_sizes`: kolom `size`
     * hanya teks dan sudah dipakai order lama (order_details.product_size_id),
     * sedangkan pemilihan motif butuh identitas yang stabiel (ID) supaya
     * keranjang bisa membedakan dua motif dari produk yang sama.
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 60);
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'name'], 'product_variants_product_id_name_unique');
            $table->index(['product_id', 'is_active'], 'product_variants_product_id_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};