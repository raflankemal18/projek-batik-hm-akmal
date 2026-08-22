<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function adminNotifAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function adminNotifUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

function createSuccessPaymentWithFulfillment(User $user, ?string $fulfillmentStatus = 'processing', string $paymentStatus = 'success'): array
{
    $order = Order::create([
        'user_id' => $user->id,
        'total_harga' => 100000,
        'status' => $paymentStatus === 'success' ? 'paid' : 'pending_payment',
        'fulfillment_status' => $fulfillmentStatus,
    ]);

    $payment = Payment::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'transaction_id' => 'INV-' . uniqid(),
        'total_harga' => 100000,
        'status' => $paymentStatus,
        'paid_at' => $paymentStatus === 'success' ? now() : null,
    ]);

    return [$order, $payment];
}

test('admin menerima pendingOrdersCount 0 jika belum ada order baru', function () {
    $admin = adminNotifAdmin();

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('pendingOrdersCount', 0)
    );
});

test('pendingOrdersCount bertambah saat ada order paid dengan fulfillment processing atau null', function () {
    $admin = adminNotifAdmin();
    $user = adminNotifUser();

    createSuccessPaymentWithFulfillment($user, 'processing', 'success');
    createSuccessPaymentWithFulfillment($user, null, 'success');

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('pendingOrdersCount', 2)
    );
});

test('pendingOrdersCount berkurang saat status fulfillment diubah menjadi shipped atau delivered', function () {
    $admin = adminNotifAdmin();
    $user = adminNotifUser();

    [$order1, $payment1] = createSuccessPaymentWithFulfillment($user, 'processing', 'success');
    [$order2, $payment2] = createSuccessPaymentWithFulfillment($user, 'processing', 'success');

    $order1->update(['fulfillment_status' => 'shipped']);

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('pendingOrdersCount', 1)
    );
});

test('payment dengan status pending tidak dihitung ke pendingOrdersCount', function () {
    $admin = adminNotifAdmin();
    $user = adminNotifUser();

    createSuccessPaymentWithFulfillment($user, 'processing', 'pending');

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('pendingOrdersCount', 0)
    );
});

test('non-admin selalu menerima pendingOrdersCount bernilai 0', function () {
    $user = adminNotifUser();
    createSuccessPaymentWithFulfillment($user, 'processing', 'success');

    $response = $this->actingAs($user)->get('/');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('pendingOrdersCount', 0)
    );
});
