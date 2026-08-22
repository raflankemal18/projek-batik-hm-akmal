<?php

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Support\Facades\Notification;

function fulfillAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function fulfillUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

function createPaidOrderForFulfillment(User $user, string $fulfillmentStatus = 'processing'): array
{
    $order = Order::create([
        'user_id' => $user->id,
        'total_harga' => 200000,
        'status' => 'paid',
        'fulfillment_status' => $fulfillmentStatus,
    ]);

    $payment = Payment::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'transaction_id' => 'INV-' . uniqid(),
        'total_harga' => 200000,
        'status' => 'success',
        'paid_at' => now(),
    ]);

    return [$order, $payment];
}

test('admin dapat memperbarui status fulfillment order tunggal', function () {
    $admin = fulfillAdmin();
    $user = fulfillUser();
    [$order, $payment] = createPaidOrderForFulfillment($user, 'processing');

    $response = $this->actingAs($admin)
        ->from('/admin/orders')
        ->patch("/admin/orders/{$order->id}/fulfillment", [
            'fulfillment_status' => 'shipped',
            'resi' => 'RESI-FULFILL-001',
            'kurir' => 'SiCepat',
        ]);

    $response->assertRedirect('/admin/orders');
    $response->assertSessionHas('success', 'Status pengiriman berhasil diperbarui');

    $order->refresh();
    expect($order->fulfillment_status)->toBe('shipped')
        ->and($order->resi)->toBe('RESI-FULFILL-001')
        ->and($order->kurir)->toBe('SiCepat');
});

test('single fulfillment update mengirim notifikasi dan mencatat audit log', function () {
    Notification::fake();
    $admin = fulfillAdmin();
    $user = fulfillUser();
    [$order, $payment] = createPaidOrderForFulfillment($user, 'processing');

    $this->actingAs($admin)->patch("/admin/orders/{$order->id}/fulfillment", [
        'fulfillment_status' => 'delivered',
    ]);

    Notification::assertSentTo($user, OrderStatusUpdated::class, function ($n) {
        return str_contains($n->message, 'Diterima');
    });

    $log = ActivityLog::where('action', 'order.fulfillment_updated')
        ->where('subject_id', (string) $order->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($admin->id);
});

test('admin dapat melakukan bulk update fulfillment status untuk banyak pesanan sekaligus', function () {
    $admin = fulfillAdmin();
    $user1 = fulfillUser();
    $user2 = fulfillUser();

    [$order1, $payment1] = createPaidOrderForFulfillment($user1, 'processing');
    [$order2, $payment2] = createPaidOrderForFulfillment($user2, 'processing');

    $response = $this->actingAs($admin)
        ->from('/admin/orders')
        ->patch('/admin/orders/bulk-fulfillment', [
            'ids' => [$payment1->id, $payment2->id],
            'status' => 'shipped',
        ]);

    $response->assertRedirect('/admin/orders');
    $response->assertSessionHas('success', 'Status pengiriman terpilih berhasil diperbarui.');

    expect($order1->fresh()->fulfillment_status)->toBe('shipped')
        ->and($order2->fresh()->fulfillment_status)->toBe('shipped');
});

test('bulk fulfillment update mengirimkan notifikasi ke setiap pemilik order terkait', function () {
    Notification::fake();
    $admin = fulfillAdmin();
    $user1 = fulfillUser();
    $user2 = fulfillUser();

    [$order1, $payment1] = createPaidOrderForFulfillment($user1, 'processing');
    [$order2, $payment2] = createPaidOrderForFulfillment($user2, 'processing');

    $this->actingAs($admin)->patch('/admin/orders/bulk-fulfillment', [
        'ids' => [$payment1->id, $payment2->id],
        'status' => 'delivered',
    ]);

    Notification::assertSentTo($user1, OrderStatusUpdated::class);
    Notification::assertSentTo($user2, OrderStatusUpdated::class);
});

test('non-admin tidak dapat mengakses endpoint single maupun bulk fulfillment', function () {
    $user = fulfillUser();
    [$order, $payment] = createPaidOrderForFulfillment($user);

    $this->actingAs($user)
        ->patch("/admin/orders/{$order->id}/fulfillment", ['fulfillment_status' => 'shipped'])
        ->assertStatus(403);

    $this->actingAs($user)
        ->patch('/admin/orders/bulk-fulfillment', ['ids' => [$payment->id], 'status' => 'shipped'])
        ->assertStatus(403);
});

test('tamu diarahkan ke login saat mengakses endpoint single maupun bulk fulfillment', function () {
    $user = fulfillUser();
    [$order, $payment] = createPaidOrderForFulfillment($user);

    $this->patch("/admin/orders/{$order->id}/fulfillment", ['fulfillment_status' => 'shipped'])
        ->assertRedirect('/login');

    $this->patch('/admin/orders/bulk-fulfillment', ['ids' => [$payment->id], 'status' => 'shipped'])
        ->assertRedirect('/login');
});

test('validasi bulk update gagal jika parameter tidak valid', function () {
    $admin = fulfillAdmin();

    $response = $this->actingAs($admin)->patch('/admin/orders/bulk-fulfillment', [
        'ids' => [999999],
        'status' => 'invalid_status',
    ]);

    $response->assertSessionHasErrors(['ids.0', 'status']);
});
