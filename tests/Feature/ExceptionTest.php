<?php

use App\Exceptions\EmptyCartException;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\User;

test('insufficient stock exception has 422 code', function () {
    $e = new InsufficientStockException('Stok tidak cukup');

    expect($e->getCode())->toBe(422)
        ->and($e->getMessage())->toBe('Stok tidak cukup');
});

test('empty cart exception has 422 code', function () {
    $e = new EmptyCartException('Keranjang kosong');

    expect($e->getCode())->toBe(422)
        ->and($e->getMessage())->toBe('Keranjang kosong');
});

test('exception handler converts stock exceptions to back redirect with errors', function () {
    $user = User::factory()->create(['role' => 'user']);
    $product = Product::create([
        'name' => 'Kemeja',
        'harga' => 150000,
        'description' => 'desc',
    ]);
    ProductSize::create([
        'product_id' => $product->id,
        'size' => 'M',
        'stock' => 5,
    ]);

    $response = $this->actingAs($user)->from('/products')->post('/cart/add', [
        'product_id' => $product->id,
        'size' => 'M',
        'quantity' => 99,
    ]);

    $response->assertRedirect('/products');
    $response->assertSessionHasErrors('stock');
});
