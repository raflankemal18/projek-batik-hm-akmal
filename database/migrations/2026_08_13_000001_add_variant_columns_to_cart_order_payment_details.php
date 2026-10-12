<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyambungkan keranjang, order, dan payment ke motif terpilih.
     *
     * `motif_name` sengaja disimpan terpisah dari relasi: kalau nama motif di
     * katalog nanti diganti/dihapus, transaksi lama tetap harus menampilkan motif
     * dan harga yang benar-benar dibeli (kriteria "data transaksi tidak berubah").
     */
    public function up(): void
    {
        // Sengaja TIDAK ditambah index gabungan (cart_id, product_variant_id):
        // MySQL akan memakai ulang/mendekati index `cart_id` yang sudah ada untuk
        // index gabungan itu, sehingga begitu kolom product_variant_id di-drop,
        // index-nya berubah jadi (cart_id) dan tidak bisa dihapus lagi (error 1553,
        // karena jadi satu-satunya penopang FK cart_id). Pencarian baris keranjang
        // per variasi cukup memakai index cart_id + filter, tabelnya kecil
        // (satu keranjang per user).
        Schema::table('cart_details', function (Blueprint $table) {
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->nullOnDelete();
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_size_id')
                ->constrained('product_variants')
                ->nullOnDelete();
            $table->string('motif_name', 60)->nullable()->after('product_variant_id');
        });

        Schema::table('payment_details', function (Blueprint $table) {
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variants')
                ->nullOnDelete();
            $table->string('motif_name', 60)->nullable()->after('product_variant_id');
        });

        // Penanda produk yang wajib memakai model motif. Produk lama nilainya
        // false sampai command products:migrate-sizes-to-variants mengubahnya.
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('has_variants')->default(false)->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('has_variants');
        });

        Schema::table('payment_details', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn(['product_variant_id', 'motif_name']);
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn(['product_variant_id', 'motif_name']);
        });

        Schema::table('cart_details', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn('product_variant_id');
        });
    }
};