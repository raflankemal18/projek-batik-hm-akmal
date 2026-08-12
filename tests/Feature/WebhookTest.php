<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\User;

function makePendingPayment(string $transactionId): array
{
    $user = User::factory()->create(['role' => 'user']);
    $product = Product::create(['name' => 'Batik', 'harga' => 100000, 'description' => 'd']);
    $size = ProductSize::create(['product_id' => $product->id, 'size' => 'L', 'stock' => 10]);
    $order = Order::create([
        'user_id' => $user->id,
        'total_harga' => 100000,
        'status' => 'pending_payment',
    ]);
    \App\Models\OrderDetail::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_size_id' => $size->id,
        'size' => 'L',
        'quantity' => 1,
        'harga' => 100000,
    ]);
    $payment = Payment::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'transaction_id' => $transactionId,
        'total_harga' => 100000,
        'status' => 'pending',
    ]);
    $size->decrement('stock', 1);

    return [$payment, $order, $size];
}

function webhookPayload(string $transactionId, string $grossAmount, string $status, string $statusCode): array
{
    $serverKey = config('midtrans.serverKey');

    return [
        'order_id' => $transactionId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'transaction_status' => $status,
        'signature_key' => hash('sha512', $transactionId.$statusCode.$grossAmount.$serverKey),
    ];
}

test('webhook with valid settlement signature marks payment and order paid', function () {
    $transactionId = 'INV-260812-000001';
    [$payment, $order] = makePendingPayment($transactionId);

    $response = $this->postJson('/midtrans/webhook', webhookPayload($transactionId, '100000', 'settlement', '200'));

    $response->assertOk();
    expect($payment->fresh()->status)->toBe('success')
        ->and($payment->fresh()->paid_at)->not->toBeNull()
        ->and($order->fresh()->status)->toBe('paid');
});

test('webhook with valid capture signature marks payment paid', function () {
    $transactionId = 'INV-260812-000002';
    [$payment, $order] = makePendingPayment($transactionId);

    $response = $this->postJson('/midtrans/webhook', webhookPayload($transactionId, '100000', 'capture', '200'));

    $response->assertOk();
    expect($payment->fresh()->status)->toBe('success');
});

test('webhook with invalid signature returns 403 and does not change status', function () {
    $transactionId = 'INV-260812-000003';
    [$payment] = makePendingPayment($transactionId);

    $response = $this->postJson('/midtrans/webhook', [
        'order_id' => $transactionId,
        'status_code' => '200',
        'gross_amount' => '100000',
        'transaction_status' => 'settlement',
        'signature_key' => 'invalid-signature',
    ]);

    $response->assertStatus(403);
    expect($payment->fresh()->status)->toBe('pending');
});

test('webhook is idempotent on success', function () {
    $transactionId = 'INV-260812-000004';
    [$payment, $order, $size] = makePendingPayment($transactionId);

    $this->postJson('/midtrans/webhook', webhookPayload($transactionId, '100000', 'settlement', '200'))->assertOk();
    $this->postJson('/midtrans/webhook', webhookPayload($transactionId, '100000', 'settlement', '200'))->assertOk();

    expect($payment->fresh()->status)->toBe('success')
        ->and($size->fresh()->stock)->toBe(9);
});

test('webhook failure restores stock and cancels order', function () {
    $transactionId = 'INV-260812-000005';
    [$payment, $order, $size] = makePendingPayment($transactionId);

    $response = $this->postJson('/midtrans/webhook', webhookPayload($transactionId, '100000', 'expire', '201'));

    $response->assertOk();
    expect($payment->fresh()->status)->toBe('failed')
        ->and($order->fresh()->status)->toBe('cancelled')
        ->and($size->fresh()->stock)->toBe(10)
        ->and($payment->fresh()->snap_token)->toBeNull();
});
