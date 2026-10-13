<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ukuran kain jadi informasi produk (mis. "2 m × 1,1 m"), bukan pilihan ukuran
 * seperti S/M/L — dipakai di P5 untuk menampilkan spesifikasi pada halaman detail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('fabric_size', 60)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('fabric_size');
        });
    }
};