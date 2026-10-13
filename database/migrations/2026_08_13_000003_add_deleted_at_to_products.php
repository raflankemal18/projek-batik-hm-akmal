<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Produk tidak lagi dihapus fisik.
 *
 * `order_details.product_id` memakai FK cascade ke `products`, jadi hard delete
 * produk ikut menghapus baris rincian item pada order — data transaksi yang
 * sudah dibayar jadi tidak bisa dibaca lagi. Soft delete menjaga baris produk
 * tetap ada (agar riwayat tetap utuh) sekaligus menghilangkan produk dari
 * katalog publik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};