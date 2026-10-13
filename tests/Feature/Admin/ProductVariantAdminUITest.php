<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * P5 — UI admin: backend yang menopangnya.
 *
 * Halaman admin harus melihat SEMUA motif (aktif & nonaktif), katalog publik
 * hanya boleh melihat motif aktif, dan produk bisa menyimpan ukuran kain +
 * penanda "produk bermotif" tanpa harus mengirim sizes[].
 */
function adminForP5(): User
{
    return User::factory()->create(['role' => 'admin']);
}

test('halaman admin memuat semua motif termasuk yang nonaktif', function () {
    $admin = adminForP5();
    $product = Product::create(['name' => 'Batik Cap', 'harga' => 100000, 'has_variants' => true]);

    ProductVariant::create(['product_id' => $product->id, 'name' => 'Parang', 'price' => 100000, 'stock' => 5, 'is_active' => true]);
    $off = ProductVariant::create(['product_id' => $product->id, 'name' => 'Kawung', 'price' => 120000, 'stock' => 0, 'is_active' => false]);

    $this->actingAs($admin)->get('/admin/products')
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($product, $off) {
            $page->component('admin/Products');

            $rows = collect($page->toArray()['props']['products']['data']);

            $row = $rows->firstWhere('id', $product->id);
            expect($row)->not->toBeNull()
                ->and($row['has_variants'])->toBeTrue()
                ->and($row['variants'])->toHaveCount(2);

            $names = collect($row['variants'])->pluck('name');
            expect($names)->toContain('Parang')->toContain('Kawung');
        });
});

test('katalog publik hanya memuat motif yang aktif', function () {
    $product = Product::create(['name' => 'Batik Publik', 'harga' => 100000, 'has_variants' => true]);
    ProductVariant::create(['product_id' => $product->id, 'name' => 'Aktif', 'price' => 100000, 'stock' => 5, 'is_active' => true]);
    ProductVariant::create(['product_id' => $product->id, 'name' => 'Nonaktif', 'price' => 110000, 'stock' => 5, 'is_active' => false]);

    $this->get('/products')->assertOk()->assertInertia(function (Assert $page) {
        $row = collect($page->toArray()['props']['products'])->firstWhere('name', 'Batik Publik');

        expect($row)->not->toBeNull();

        $variantNames = collect($row['active_variants'] ?? [])->pluck('name');
        expect($variantNames)->toContain('Aktif')->not->toContain('Nonaktif');
    });
});

test('produk bisa disimpan dengan ukuran kain tanpa daftar ukuran', function () {
    $admin = adminForP5();

    $this->actingAs($admin)->post('/admin/products', [
        'name' => 'Batik Cap 2 Warna Biru',
        'harga' => 85000,
        'description' => 'Kain tulis motif cap',
        'category' => 'pria',
        'fabric_size' => '2 m × 1,1 m',
        'has_variants' => '1',
        // sizes[] sengaja tidak dikirim — produk ini memakai motif.
    ])->assertRedirect();

    $product = Product::where('name', 'Batik Cap 2 Warna Biru')->firstOrFail();

    expect($product->fabric_size)->toBe('2 m × 1,1 m')
        ->and($product->has_variants)->toBeTrue()
        ->and($product->sizes()->count())->toBe(0);
});

test('produk tanpa motif tetap wajib punya daftar ukuran', function () {
    $admin = adminForP5();

    $this->actingAs($admin)->post('/admin/products', [
        'name' => 'Batik Tanpa Motif',
        'harga' => 75000,
        'category' => 'pria',
    ])->assertSessionHasErrors('sizes');
});

test('ukuran kain terlalu panjang ditolak', function () {
    $admin = adminForP5();

    $this->actingAs($admin)->post('/admin/products', [
        'name' => 'Batik Kain Panjang',
        'harga' => 75000,
        'category' => 'pria',
        'fabric_size' => str_repeat('x', 61),
        'sizes' => [json_encode(['size' => 'M', 'stock' => 1])],
    ])->assertSessionHasErrors('fabric_size');
});
