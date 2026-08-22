<?php

use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function searchProduct(string $name = 'Batik Mega Mendung', string $desc = 'Motif awan khas Cirebon'): Product
{
    return Product::create([
        'name' => $name,
        'harga' => 175000,
        'description' => $desc,
    ]);
}

test('pencarian dapat diakses oleh tamu tanpa login', function () {
    $response = $this->get('/search');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Search')
        ->has('products')
        ->where('query', '')
    );
});

test('pencarian dapat diakses oleh user yang login', function () {
    $user = User::factory()->create(['role' => 'user']);

    $response = $this->actingAs($user)->get('/search');

    $response->assertOk();
});

test('pencarian mencocokkan nama produk', function () {
    $batikMendung = searchProduct('Batik Mega Mendung', 'Motif klasik');
    $batikParang = searchProduct('Batik Parang Rusak', 'Motif lereng');

    $response = $this->get('/search?q=Mendung');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Search')
        ->where('query', 'Mendung')
        ->has('products', 1)
        ->where('products.0.id', $batikMendung->id)
    );
});

test('pencarian mencocokkan deskripsi produk', function () {
    $batikA = searchProduct('Batik Kawung', 'Motif geometris khas Solo');
    $batikB = searchProduct('Batik Lasem', 'Motif akulturasi Tionghoa');

    $response = $this->get('/search?q=Solo');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Search')
        ->where('query', 'Solo')
        ->has('products', 1)
        ->where('products.0.id', $batikA->id)
    );
});

test('pencarian tanpa query mengembalikan semua produk', function () {
    searchProduct('Batik 1', 'Deskripsi 1');
    searchProduct('Batik 2', 'Deskripsi 2');

    $response = $this->get('/search');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Search')
        ->has('products', 2)
        ->where('query', '')
    );
});

test('pencarian dengan query yang tidak cocok mengembalikan daftar kosong', function () {
    searchProduct('Batik Pekalongan', 'Deskripsi Pekalongan');

    $response = $this->get('/search?q=NonExistentKeywordXYZ');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Search')
        ->has('products', 0)
        ->where('query', 'NonExistentKeywordXYZ')
    );
});
