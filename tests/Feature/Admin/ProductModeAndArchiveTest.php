<?php

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Models\User;

/**
 * Perbaikan temuan audit 2026-10-13 (#2): mode motif tidak boleh dimatikan
 * selama produk masih punya variasi.
 */
function modeProduct(): Product
{
    return Product::create([
        'name' => 'Batik Cap 2 Warna',
        'harga' => 85000,
        'category' => 'wanita',
        'has_variants' => true,
    ]);
}

test('admin tidak bisa mematikan mode motif selama masih ada variasi', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = modeProduct();
    ProductVariant::create([
        'product_id' => $product->id, 'name' => 'Parang',
        'price' => 85000, 'stock' => 3, 'is_active' => true,
    ]);

    $this->actingAs($admin)->put("/admin/products/{$product->id}", [
        '_method' => 'PUT',
        'name' => 'Batik Cap 2 Warna (revisi)',
        'harga' => 90000,
        'description' => null,
        'category' => 'wanita',
        'has_variants' => '', // mencoba mematikan mode motif
    ])->assertSessionHasErrors('has_variants');

    $product->refresh();

    expect($product->has_variants)->toBeTrue()
        ->and($product->name)->toBe('Batik Cap 2 Warna');
});

test('variasi yang sudah dinonaktifkan pun tetap memblokir mematikan mode motif', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = modeProduct();
    $variant = ProductVariant::create([
        'product_id' => $product->id, 'name' => 'Parang',
        'price' => 85000, 'stock' => 0, 'is_active' => false,
    ]);

    $this->actingAs($admin)->put("/admin/products/{$product->id}", [
        '_method' => 'PUT',
        'name' => 'Batik Cap 2 Warna',
        'harga' => 85000,
        'category' => 'wanita',
        'has_variants' => '',
    ])->assertSessionHasErrors('has_variants');

    expect($product->refresh()->has_variants)->toBeTrue();
});

test('produk tanpa variasi boleh mengganti mode motif', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::create([
        'name' => 'Batik Legacy',
        'harga' => 75000,
        'has_variants' => false,
    ]);
    ProductSize::create(['product_id' => $product->id, 'size' => 'M', 'stock' => 3]);

    $this->actingAs($admin)->put("/admin/products/{$product->id}", [
        '_method' => 'PUT',
        'name' => 'Batik Legacy',
        'harga' => 75000,
        'category' => 'wanita',
        'has_variants' => '',
        'sizes' => [json_encode(['size' => 'M', 'stock' => 3])],
    ])->assertSessionHasNoErrors();

    expect($product->refresh()->has_variants)->toBeFalse();
});

test('produk bermotif tetap boleh diperbarui selama mode motifnya tetap menyala', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = modeProduct();
    ProductVariant::create([
        'product_id' => $product->id, 'name' => 'Parang',
        'price' => 85000, 'stock' => 3, 'is_active' => true,
    ]);

    $this->actingAs($admin)->put("/admin/products/{$product->id}", [
        '_method' => 'PUT',
        'name' => 'Batik Cap 2 Warna (revisi)',
        'harga' => 95000,
        'category' => 'wanita',
        'fabric_size' => '2 m x 1,1 m',
        'has_variants' => '1',
    ])->assertSessionHasNoErrors();

    $product->refresh();

    expect($product->has_variants)->toBeTrue()
        ->and($product->name)->toBe('Batik Cap 2 Warna (revisi)')
        ->and($product->fabric_size)->toBe('2 m x 1,1 m');
});

test('produk terarsip hilang dari katalog tapi invoice & invoice detailnya tetap terbaca', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'user']);
    $product = modeProduct();

    $order = Order::create(['user_id' => $user->id, 'total_harga' => 85000, 'status' => 'paid']);
    OrderDetail::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'size' => 'M',
        'quantity' => 1,
        'harga' => 85000,
    ]);
    $payment = Payment::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'transaction_id' => 'TX-ARCHIVED',
        'total_harga' => 85000,
        'status' => 'success',
        'paid_at' => now(),
    ]);
    PaymentDetail::create([
        'payment_id' => $payment->id,
        'product_id' => $product->id,
        'name' => $user->name,
        'email' => $user->email,
        'phone' => '08123',
        'alamat' => 'Jl. Test',
        'size' => 'M',
        'quantity' => 1,
        'harga' => 85000,
    ]);

    $this->actingAs($admin)->delete("/admin/products/{$product->id}")->assertRedirect();

    // Tidak tampil di katalog publik.
    $this->get('/products')->assertOk()->assertInertia(function ($page) {
        $names = collect($page->toArray()['props']['products'])->pluck('name');
        expect($names)->not->toContain('Batik Cap 2 Warna');
    });

    // Tidak tampil di daftar admin aktif, tapi ada di arsip.
    $this->actingAs($admin)->get('/admin/products')->assertOk()->assertInertia(function ($page) {
        $names = collect($page->toArray()['props']['products']['data'])->pluck('name');
        expect($names)->not->toContain('Batik Cap 2 Warna');
    });

    $this->actingAs($admin)->get('/admin/products-archived')->assertOk()->assertInertia(function ($page) {
        $names = collect($page->toArray()['props']['products']['data'])->pluck('name');
        expect($names)->toContain('Batik Cap 2 Warna');
    });

    // Dan bisa dipulihkan.
    $this->actingAs($admin)->post("/admin/products/{$product->id}/restore")
        ->assertRedirect(route('admin.products'));

    expect(Product::find($product->id))->not->toBeNull();
    $this->actingAs($user)->get("/payments/{$payment->id}/invoice")->assertOk();
});
