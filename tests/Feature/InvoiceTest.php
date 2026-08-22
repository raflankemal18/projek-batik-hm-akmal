<?php

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function invoiceUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

function invoiceAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createInvoicePayment(User $user, string $status = 'success', bool $withOrder = true): Payment
{
    $product = Product::create([
        'name' => 'Batik Sutra Tulis',
        'harga' => 300000,
        'description' => 'Batik halus',
    ]);
    $size = ProductSize::create([
        'product_id' => $product->id,
        'size' => 'XL',
        'stock' => 10,
    ]);

    $order = null;
    if ($withOrder) {
        $order = Order::create([
            'user_id' => $user->id,
            'total_harga' => 300000,
            'status' => $status === 'success' ? 'paid' : 'pending_payment',
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_size_id' => $size->id,
            'size' => 'XL',
            'quantity' => 1,
            'harga' => 300000,
        ]);
    }

    return Payment::create([
        'user_id' => $user->id,
        'order_id' => $order ? $order->id : null,
        'transaction_id' => 'INV-' . uniqid(),
        'total_harga' => 300000,
        'status' => $status,
        'paid_at' => $status === 'success' ? now() : null,
    ]);
}

test('tamu tidak dapat mengakses invoice dan diarahkan ke login', function () {
    $user = invoiceUser();
    $payment = createInvoicePayment($user, 'success');

    $response = $this->get("/payments/{$payment->id}/invoice");

    $response->assertRedirect('/login');
});

test('pemilik pembayaran dapat mengakses invoice miliknya yang sudah sukses', function () {
    $user = invoiceUser();
    $payment = createInvoicePayment($user, 'success');

    $response = $this->actingAs($user)->get("/payments/{$payment->id}/invoice");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Invoice')
        ->where('payment.id', $payment->id)
        ->where('payment.transaction_id', $payment->transaction_id)
        ->has('payment.order.details', 1)
    );
});

test('admin dapat mengakses invoice milik user manapun yang sudah sukses', function () {
    $admin = invoiceAdmin();
    $user = invoiceUser();
    $payment = createInvoicePayment($user, 'success');

    $response = $this->actingAs($admin)->get("/payments/{$payment->id}/invoice");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Invoice')
        ->where('payment.id', $payment->id)
        ->where('payment.user.id', $user->id)
    );
});

test('user lain yang bukan pemilik dan bukan admin mendapat 403 saat mengakses invoice', function () {
    $owner = invoiceUser();
    $otherUser = invoiceUser();
    $payment = createInvoicePayment($owner, 'success');

    $response = $this->actingAs($otherUser)->get("/payments/{$payment->id}/invoice");

    $response->assertStatus(403);
});

test('invoice untuk pembayaran yang belum sukses (pending) mengembalikan 404', function () {
    $user = invoiceUser();
    $payment = createInvoicePayment($user, 'pending');

    $response = $this->actingAs($user)->get("/payments/{$payment->id}/invoice");

    $response->assertStatus(404);
});

test('invoice untuk pembayaran tanpa relasi order mengembalikan 404', function () {
    $user = invoiceUser();
    $payment = createInvoicePayment($user, 'success', withOrder: false);

    $response = $this->actingAs($user)->get("/payments/{$payment->id}/invoice");

    $response->assertStatus(404);
});
