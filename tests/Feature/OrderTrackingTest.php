<?php

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function trackingUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

function trackingAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createPaidOrderWithPayment(User $user): array
{
    $product = Product::create([
        'name' => 'Batik Tulis Eksklusif',
        'harga' => 250000,
        'description' => 'Batik tulis sutra',
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'total_harga' => 250000,
        'status' => 'paid',
        'fulfillment_status' => 'processing',
    ]);

    $payment = Payment::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'transaction_id' => 'INV-' . uniqid(),
        'total_harga' => 250000,
        'status' => 'success',
        'paid_at' => now(),
    ]);

    return [$order, $payment, $product];
}

test('admin dapat memperbarui status pengiriman nomor resi dan kurir', function () {
    $admin = trackingAdmin();
    $user = trackingUser();
    [$order, $payment] = createPaidOrderWithPayment($user);

    $response = $this->actingAs($admin)
        ->from('/admin/orders')
        ->patch("/admin/orders/{$order->id}/fulfillment", [
            'fulfillment_status' => 'shipped',
            'resi' => 'JNE123456789',
            'kurir' => 'JNE Express',
        ]);

    $response->assertRedirect('/admin/orders');
    $response->assertSessionHas('success', 'Status pengiriman berhasil diperbarui');

    $order->refresh();
    expect($order->fulfillment_status)->toBe('shipped')
        ->and($order->resi)->toBe('JNE123456789')
        ->and($order->kurir)->toBe('JNE Express');
});

test('user dapat melihat status pengiriman nomor resi dan kurir di halaman pembayaran', function () {
    $user = trackingUser();
    [$order, $payment] = createPaidOrderWithPayment($user);

    $order->update([
        'fulfillment_status' => 'shipped',
        'resi' => 'JNE987654321',
        'kurir' => 'JNE Express',
    ]);

    $response = $this->actingAs($user)->get('/pembayaran');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Payment')
        ->has('payments.data', 1)
        ->where('payments.data.0.id', $payment->id)
        ->where('payments.data.0.order.fulfillment_status', 'shipped')
        ->where('payments.data.0.order.resi', 'JNE987654321')
        ->where('payments.data.0.order.kurir', 'JNE Express')
    );
});

test('user lain tidak dapat melihat order dan resi milik user lain di halaman pembayaran', function () {
    $user1 = trackingUser();
    $user2 = trackingUser();
    [$order1, $payment1] = createPaidOrderWithPayment($user1);
    [$order2, $payment2] = createPaidOrderWithPayment($user2);

    $order1->update([
        'fulfillment_status' => 'shipped',
        'resi' => 'RESI-USER-1',
        'kurir' => 'JNT',
    ]);

    $response = $this->actingAs($user2)->get('/pembayaran');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Payment')
        ->has('payments.data', 1)
        ->where('payments.data.0.id', $payment2->id)
        ->where('payments.data.0.order.resi', null)
    );
});

test('user biasa tidak dapat memperbarui fulfillment dan resi', function () {
    $user = trackingUser();
    [$order] = createPaidOrderWithPayment($user);

    $response = $this->actingAs($user)
        ->patch("/admin/orders/{$order->id}/fulfillment", [
            'fulfillment_status' => 'shipped',
            'resi' => 'RESI-ILEGAL',
            'kurir' => 'JNE',
        ]);

    $response->assertStatus(403);
});

test('tamu tidak dapat memperbarui fulfillment dan resi', function () {
    $user = trackingUser();
    [$order] = createPaidOrderWithPayment($user);

    $response = $this->patch("/admin/orders/{$order->id}/fulfillment", [
        'fulfillment_status' => 'shipped',
        'resi' => 'RESI-GUEST',
        'kurir' => 'JNE',
    ]);

    $response->assertRedirect('/login');
});

test('notifikasi terkirim ke user saat admin mengisi resi dan status shipped', function () {
    Notification::fake();

    $admin = trackingAdmin();
    $user = trackingUser();
    [$order, $payment] = createPaidOrderWithPayment($user);

    $this->actingAs($admin)->patch("/admin/orders/{$order->id}/fulfillment", [
        'fulfillment_status' => 'shipped',
        'resi' => 'SICEPAT12345',
        'kurir' => 'SiCepat',
    ]);

    Notification::assertSentTo(
        $user,
        OrderStatusUpdated::class,
        function (OrderStatusUpdated $notification) {
            return str_contains($notification->message, 'Resi: SICEPAT12345 (SiCepat)')
                && str_contains($notification->message, 'Dikirim');
        }
    );
});

test('update fulfillment mencatat audit log admin', function () {
    $admin = trackingAdmin();
    $user = trackingUser();
    [$order] = createPaidOrderWithPayment($user);

    $this->actingAs($admin)->patch("/admin/orders/{$order->id}/fulfillment", [
        'fulfillment_status' => 'delivered',
        'resi' => 'JNE123',
        'kurir' => 'JNE',
    ]);

    $log = ActivityLog::where('action', 'order.fulfillment_updated')
        ->where('subject_id', (string) $order->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->type)->toBe('admin');
});

test('validasi update fulfillment gagal jika status tidak valid', function () {
    $admin = trackingAdmin();
    $user = trackingUser();
    [$order] = createPaidOrderWithPayment($user);

    $response = $this->actingAs($admin)->patch("/admin/orders/{$order->id}/fulfillment", [
        'fulfillment_status' => 'invalid_status',
    ]);

    $response->assertSessionHasErrors('fulfillment_status');
});
