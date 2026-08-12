<?php

use App\Models\Cart;
use App\Models\CartDetail;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

function checkoutUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

function checkoutProduct(): array
{
    $product = Product::create([
        'name' => 'Batik',
        'harga' => 100000,
        'description' => 'desc',
    ]);
    $size = ProductSize::create([
        'product_id' => $product->id,
        'size' => 'L',
        'stock' => 10,
    ]);

    return [$product, $size];
}

function fillCart(User $user, Product $product, string $size = 'L', int $qty = 2): void
{
    $cart = Cart::create(['user_id' => $user->id]);
    CartDetail::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => $qty,
        'size' => $size,
    ]);
}

test('checkout ignores client-provided harga and totals from DB', function () {
    $user = checkoutUser();
    [$product, $size] = checkoutProduct();
    fillCart($user, $product, 'L', 2);

    $this->actingAs($user)->post('/checkout', [
        'name' => 'Test User',
        'email' => 'test@test.com',
        'phone' => '08123',
        'alamat' => 'Jl. Test 1',
        'totalHarga' => 1,
        'orders' => [['fake' => true]],
    ]);

    $order = Order::where('user_id', $user->id)->where('status', 'draft')->first();

    expect($order)->not->toBeNull()
        ->and($order->total_harga)->toBe('200000.00')
        ->and($order->details->first()->harga)->toBe('100000.00')
        ->and($order->details->first()->product_size_id)->toBe($size->id)
        ->and($size->fresh()->stock)->toBe(8);
});

test('checkout with empty cart throws EmptyCartException and redirects with errors', function () {
    $user = checkoutUser();

    $response = $this->actingAs($user)->from('/cart')->post('/checkout', [
        'name' => 'Test',
        'email' => 't@t.com',
        'phone' => '08',
        'alamat' => 'Jl X',
    ]);

    $response->assertSessionHasErrors('stock');
});

test('double checkout returns the existing draft order', function () {
    $user = checkoutUser();
    [$product] = checkoutProduct();
    fillCart($user, $product, 'L', 2);

    $this->actingAs($user)->post('/checkout', [
        'name' => 'Test', 'email' => 't@t.com', 'phone' => '08', 'alamat' => 'Jl X',
    ]);
    $this->actingAs($user)->post('/checkout', [
        'name' => 'Test', 'email' => 't@t.com', 'phone' => '08', 'alamat' => 'Jl X',
    ]);

    $drafts = Order::where('user_id', $user->id)->where('status', 'draft')->get();

    expect($drafts)->toHaveCount(1);
});

test('checkout decrements stock exactly once', function () {
    $user = checkoutUser();
    [$product, $size] = checkoutProduct();
    fillCart($user, $product, 'L', 3);

    $this->actingAs($user)->post('/checkout', [
        'name' => 'Test', 'email' => 't@t.com', 'phone' => '08', 'alamat' => 'Jl X',
    ]);

    expect($size->fresh()->stock)->toBe(7);
});

test('release expired orders restores stock and cancels draft', function () {
    $user = checkoutUser();
    [$product, $size] = checkoutProduct();

    $order = Order::create([
        'user_id' => $user->id,
        'total_harga' => 200000,
        'status' => 'draft',
        'expires_at' => now()->subMinutes(1),
    ]);
    \App\Models\OrderDetail::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_size_id' => $size->id,
        'size' => 'L',
        'quantity' => 2,
        'harga' => 100000,
    ]);
    $size->decrement('stock', 2);

    Artisan::call('orders:release-expired');

    expect($order->fresh()->status)->toBe('cancelled')
        ->and($size->fresh()->stock)->toBe(10);
});

test('idempotent release does not double restore stock', function () {
    $user = checkoutUser();
    [$product, $size] = checkoutProduct();

    $order = Order::create([
        'user_id' => $user->id,
        'total_harga' => 100000,
        'status' => 'draft',
        'expires_at' => now()->subMinutes(1),
    ]);
    \App\Models\OrderDetail::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_size_id' => $size->id,
        'size' => 'L',
        'quantity' => 1,
        'harga' => 100000,
    ]);
    $size->decrement('stock', 1);

    Artisan::call('orders:release-expired');
    Artisan::call('orders:release-expired');

    expect($size->fresh()->stock)->toBe(10);
});

test('cart idor: another user cannot update or remove others cart detail', function () {
    $owner = checkoutUser();
    $attacker = User::factory()->create(['role' => 'user']);
    [$product] = checkoutProduct();
    $cart = Cart::create(['user_id' => $owner->id]);
    $detail = CartDetail::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'size' => 'L',
    ]);

    $this->actingAs($attacker)->post('/cart/update', [
        'cart_detail_id' => $detail->id,
        'quantity' => 5,
    ])->assertStatus(404);

    $this->actingAs($attacker)->post('/cart/remove', [
        'cart_detail_id' => $detail->id,
    ])->assertStatus(404);

    expect($detail->fresh())->not->toBeNull();
});
