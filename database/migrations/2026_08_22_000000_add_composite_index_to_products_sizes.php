<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products_sizes', function (Blueprint $table) {
            $table->index(['product_id', 'size'], 'products_sizes_product_id_size_index');
        });
    }

    public function down(): void
    {
        Schema::table('products_sizes', function (Blueprint $table) {
            $table->dropIndex('products_sizes_product_id_size_index');
        });
    }
};
