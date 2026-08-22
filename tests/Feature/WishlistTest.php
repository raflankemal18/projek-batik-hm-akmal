<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Inertia\Testing\AssertableInertia as Assert;

function wishlistUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

function wishlistAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function wishlistProduct(string $name = 'Batik Tulis'): Product
{
    return Product::create([
        'name' => $name,
        'harga' => 150000,
        'description' => 'Deskripsi batik premium',
    ]);
}

test('tamu tidak dapat mengakses halaman wishlist', function () {
    $this->get('/wishlist')->assertRedirect('/login');
});

test('tamu tidak dapat melakukan toggle wishlist', function () {
    $product = wishlistProduct();

    $this->post('/wishlist/toggle', ['product_id' => $product->id])
        ->assertRedirect('/login');
});

test('admin tidak dapat mengakses halaman wishlist user', function () {
    $admin = wishlistAdmin();

    $this->actingAs($admin)->get('/wishlist')->assertStatus(403);
});

test('admin tidak dapat melakukan toggle wishlist', function () {
    $admin = wishlistAdmin();
    $product = wishlistProduct();

    $this->actingAs($admin)
        ->post('/wishlist/toggle', ['product_id' => $product->id])
        ->assertStatus(403);
});

test('user dapat melihat halaman wishlist dengan produk miliknya', function () {
    $user = wishlistUser();
    $product = wishlistProduct();
    Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);

    $response = $this->actingAs($user)->get('/wishlist');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Wishlist')
        ->has('wishlists', 1)
        ->where('wishlists.0.product_id', $product->id)
    );
});

test('wishlist milik user lain tidak muncul di halaman wishlist user saat ini', function () {
    $user1 = wishlistUser();
    $user2 = wishlistUser();
    $product1 = wishlistProduct('Batik User 1');
    $product2 = wishlistProduct('Batik User 2');

    Wishlist::create(['user_id' => $user1->id, 'product_id' => $product1->id]);
    Wishlist::create(['user_id' => $user2->id, 'product_id' => $product2->id]);

    $response = $this->actingAs($user1)->get('/wishlist');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Wishlist')
        ->has('wishlists', 1)
        ->where('wishlists.0.product_id', $product1->id)
    );
});

test('toggle menambahkan produk ke wishlist jika belum ada', function () {
    $user = wishlistUser();
    $product = wishlistProduct();

    $response = $this->actingAs($user)
        ->from('/products')
        ->post('/wishlist/toggle', ['product_id' => $product->id]);

    $response->assertRedirect('/products');
    $response->assertSessionHas('success', 'Produk ditambahkan ke wishlist');

    expect(Wishlist::where('user_id', $user->id)->where('product_id', $product->id)->exists())->toBeTrue();
});

test('toggle menghapus produk dari wishlist jika sudah ada', function () {
    $user = wishlistUser();
    $product = wishlistProduct();
    Wishlist::create(['user_id' => $user->id, 'product_id' => $product->id]);

    $response = $this->actingAs($user)
        ->from('/wishlist')
        ->post('/wishlist/toggle', ['product_id' => $product->id]);

    $response->assertRedirect('/wishlist');
    $response->assertSessionHas('success', 'Produk dihapus dari wishlist');

    expect(Wishlist::where('user_id', $user->id)->where('product_id', $product->id)->exists())->toBeFalse();
});

test('toggle validasi gagal jika product_id tidak valid', function () {
    $user = wishlistUser();

    $response = $this->actingAs($user)
        ->post('/wishlist/toggle', ['product_id' => 999999]);

    $response->assertSessionHasErrors('product_id');
});
