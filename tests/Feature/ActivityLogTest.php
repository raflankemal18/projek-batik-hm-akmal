<?php

use App\Models\ActivityLog;
use App\Models\Cart;
use App\Models\CartDetail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\User;
use App\Services\AuditLogService;

function logUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

function logAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function logProduct(int $stock = 10): array
{
    $product = Product::create(['name' => 'Batik', 'harga' => 100000, 'description' => 'd']);
    $size = ProductSize::create(['product_id' => $product->id, 'size' => 'L', 'stock' => $stock]);

    return [$product, $size];
}

test('login gagal tercatat sebagai login.failed dengan email', function () {
    $user = User::factory()->create(['role' => 'user', 'password' => bcrypt('correct-password')]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $log = ActivityLog::where('action', 'login.failed')->first();

    expect($log)->not->toBeNull()
        ->and($log->context['email'])->toBe($user->email)
        ->and($log->type)->toBe('user');
});

test('product update tercatat dengan before dan after', function () {
    $admin = logAdmin();
    $product = Product::create(['name' => 'Batik Lama', 'harga' => 100000, 'description' => 'd']);
    ProductSize::create(['product_id' => $product->id, 'size' => 'M', 'stock' => 5]);

    $this->actingAs($admin)->put("/admin/products/{$product->id}", [
        'name' => 'Batik Baru',
        'harga' => 150000,
        'description' => 'd2',
        'sizes' => [['size' => 'M', 'stock' => 5]],
    ]);

    $log = ActivityLog::where('action', 'product.update')->first();

    expect($log)->not->toBeNull()
        ->and($log->context['before']['name'])->toBe('Batik Lama')
        ->and($log->context['after']['name'])->toBe('Batik Baru')
        ->and($log->subject_id)->toBe((string) $product->id);
});

test('checkout draft tercatat sebagai checkout.create dengan detail item', function () {
    $user = logUser();
    [$product, $size] = logProduct();
    $cart = Cart::create(['user_id' => $user->id]);
    CartDetail::create([
        'cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2, 'size' => 'L',
    ]);

    $this->actingAs($user)->post('/checkout', [
        'name' => 'Test', 'email' => 't@t.com', 'phone' => '08', 'alamat' => 'Jl X',
    ]);

    $log = ActivityLog::where('action', 'checkout.create')->first();

    expect($log)->not->toBeNull()
        ->and($log->context['total'])->toBe(200000)
        ->and($log->context['items'])->toHaveCount(1)
        ->and($log->context['items'][0]['qty'])->toBe(2);
});

test('stock reject tercatat sebagai stock.rejected walau transaksi checkout rollback', function () {
    $user = logUser();
    [$product, $size] = logProduct(1);
    $cart = Cart::create(['user_id' => $user->id]);
    CartDetail::create([
        'cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 5, 'size' => 'L',
    ]);

    $this->actingAs($user)->post('/checkout', [
        'name' => 'Test', 'email' => 't@t.com', 'phone' => '08', 'alamat' => 'Jl X',
    ]);

    $log = ActivityLog::where('action', 'stock.rejected')->first();

    expect($log)->not->toBeNull()
        ->and($log->context['requested_qty'])->toBe(5)
        ->and($log->context['available'])->toBe(1)
        ->and(Order::where('user_id', $user->id)->exists())->toBeFalse();
});

test('webhook paid tercatat sebagai webhook.received dan payment.paid', function () {
    $user = logUser();
    [$product, $size] = logProduct();
    $order = Order::create(['user_id' => $user->id, 'total_harga' => 100000, 'status' => 'pending_payment']);
    \App\Models\OrderDetail::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'product_size_id' => $size->id,
        'size' => 'L', 'quantity' => 1, 'harga' => 100000,
    ]);
    $payment = Payment::create([
        'user_id' => $user->id, 'order_id' => $order->id, 'transaction_id' => 'INV-LOG-001',
        'total_harga' => 100000, 'status' => 'pending',
    ]);

    $serverKey = config('midtrans.serverKey');
    $this->postJson('/midtrans/webhook', [
        'order_id' => 'INV-LOG-001',
        'status_code' => '200',
        'gross_amount' => '100000',
        'transaction_status' => 'settlement',
        'signature_key' => hash('sha512', 'INV-LOG-001'.'200'.'100000'.$serverKey),
    ]);

    expect(ActivityLog::where('action', 'webhook.received')->exists())->toBeTrue()
        ->and(ActivityLog::where('action', 'payment.paid')->first()?->context['payment_id'])->toBe($payment->id);
});

test('non-admin akses admin logs mendapat 403', function () {
    $user = logUser();

    $this->actingAs($user)->get('/admin/logs')->assertStatus(403);
});

test('password dan token tidak pernah tersimpan mentah di context', function () {
    $user = logUser();

    AuditLogService::recordUser('test.event', null, [
        'password' => 'super-secret',
        'nested' => ['token' => 'abc123', 'snap_token' => 'xyz'],
        'safe' => 'ok',
    ]);

    $log = ActivityLog::where('action', 'test.event')->first();

    expect($log->context['password'])->toBe('***REDACTED***')
        ->and($log->context['nested']['token'])->toBe('***REDACTED***')
        ->and($log->context['nested']['snap_token'])->toBe('***REDACTED***')
        ->and($log->context['safe'])->toBe('ok');
});

test('log user delete tetap ada walau user-nya sudah dihapus', function () {
    $admin = logAdmin();
    $target = logUser();
    $targetId = $target->id;
    $targetEmail = $target->email;

    $this->actingAs($admin)->delete("/admin/users/{$target->id}");

    expect(User::find($targetId))->toBeNull();

    $log = ActivityLog::where('action', 'user.delete')->first();

    expect($log)->not->toBeNull()
        ->and($log->context['deleted_user_id'])->toBe($targetId)
        ->and($log->context['deleted_email'])->toBe($targetEmail);
});
