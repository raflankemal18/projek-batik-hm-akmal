<?php

use App\Contracts\Repositories\CartRepositoryInterface;
use App\Models\Cart;
use App\Models\User;

test('findOwned returns only the owners record', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ownerCart = Cart::create(['user_id' => $owner->id]);
    $otherCart = Cart::create(['user_id' => $other->id]);

    $repo = app(CartRepositoryInterface::class);

    $found = $repo->findOwned($ownerCart->id, $owner);

    expect($found->id)->toBe($ownerCart->id);
});

test('findOwned throws for another users record', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $ownerCart = Cart::create(['user_id' => $owner->id]);

    $repo = app(CartRepositoryInterface::class);

    $repo->findOwned($ownerCart->id, $attacker);
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);

test('findOwned throws when record does not exist', function () {
    $user = User::factory()->create();

    $repo = app(CartRepositoryInterface::class);

    $repo->findOwned(999999, $user);
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);
