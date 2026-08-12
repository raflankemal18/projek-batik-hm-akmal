<?php

use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Policies\CartPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\UserPolicy;

test('product create is admin only', function () {
    $user = User::factory()->create(['role' => 'user']);
    $admin = User::factory()->create(['role' => 'admin']);

    expect((new ProductPolicy)->create($user))->toBeFalse()
        ->and((new ProductPolicy)->create($admin))->toBeTrue();
});

test('product delete is admin only', function () {
    $user = User::factory()->create(['role' => 'user']);
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::create(['name' => 'Batik', 'harga' => 100000, 'description' => 'd']);

    expect((new ProductPolicy)->delete($user, $product))->toBeFalse()
        ->and((new ProductPolicy)->delete($admin, $product))->toBeTrue();
});

test('payment view is owner or admin only', function () {
    $owner = User::factory()->create(['role' => 'user']);
    $other = User::factory()->create(['role' => 'user']);
    $admin = User::factory()->create(['role' => 'admin']);
    $payment = Payment::create([
        'user_id' => $owner->id,
        'total_harga' => 100000,
        'status' => 'pending',
    ]);

    expect((new PaymentPolicy)->view($owner, $payment))->toBeTrue()
        ->and((new PaymentPolicy)->view($other, $payment))->toBeFalse()
        ->and((new PaymentPolicy)->view($admin, $payment))->toBeTrue();
});

test('cart update is owner only', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $cart = \App\Models\Cart::create(['user_id' => $owner->id]);

    expect((new CartPolicy)->update($owner, $cart))->toBeTrue()
        ->and((new CartPolicy)->update($other, $cart))->toBeFalse();
});

test('admin cannot delete another admin', function () {
    $adminA = User::factory()->create(['role' => 'admin']);
    $adminB = User::factory()->create(['role' => 'admin']);
    $regular = User::factory()->create(['role' => 'user']);

    expect((new UserPolicy)->delete($adminA, $adminB))->toBeFalse()
        ->and((new UserPolicy)->delete($adminA, $regular))->toBeTrue();
});

test('non-admin cannot delete any user', function () {
    $user = User::factory()->create(['role' => 'user']);
    $target = User::factory()->create(['role' => 'user']);

    expect((new UserPolicy)->delete($user, $target))->toBeFalse();
});
