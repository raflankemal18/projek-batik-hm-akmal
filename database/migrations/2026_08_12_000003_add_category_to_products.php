<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category', 20)->nullable()->index()->after('name');
        });

        // Backfill kategori: heuristik dari nama, sisanya acak
        $categories = ['pria', 'wanita', 'sarimbit'];

        DB::table('products')->orderBy('id')->chunkById(100, function ($products) use ($categories) {
            foreach ($products as $product) {
                $name = strtolower($product->name);

                if (str_contains($name, 'batik') || str_contains($name, 'kemeja')) {
                    $category = 'pria';
                } elseif (str_contains($name, 'kaos') || str_contains($name, 'hoodie')) {
                    $category = ['pria', 'wanita'][rand(0, 1)];
                } else {
                    $category = $categories[array_rand($categories)];
                }

                DB::table('products')->where('id', $product->id)->update(['category' => $category]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
