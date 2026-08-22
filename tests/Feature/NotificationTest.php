<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\OrderStatusUpdated;
use Inertia\Testing\AssertableInertia as Assert;

function notifUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

function notifAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function createOrderForNotification(User $user): array
{
    $order = Order::create([
        'user_id' => $user->id,
        'total_harga' => 150000,
        'status' => 'paid',
        'fulfillment_status' => 'processing',
    ]);

    $payment = Payment::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'transaction_id' => 'INV-' . uniqid(),
        'total_harga' => 150000,
        'status' => 'success',
        'paid_at' => now(),
    ]);

    return [$order, $payment];
}

test('notifikasi tersimpan di database saat status order diperbarui oleh admin', function () {
    $admin = notifAdmin();
    $user = notifUser();
    [$order, $payment] = createOrderForNotification($user);

    $this->actingAs($admin)->patch("/admin/orders/{$order->id}/fulfillment", [
        'fulfillment_status' => 'shipped',
        'resi' => 'RESI123456',
        'kurir' => 'JNE',
    ]);

    expect($user->notifications()->count())->toBe(1);

    $notification = $user->notifications()->first();
    expect($notification->data['order_id'])->toBe($order->id)
        ->and($notification->data['fulfillment_status'])->toBe('shipped')
        ->and($notification->data['resi'])->toBe('RESI123456')
        ->and($notification->data['kurir'])->toBe('JNE')
        ->and($notification->read_at)->toBeNull();
});

test('notifikasi belum dibaca dibagikan lewat shared props inertia', function () {
    $user = notifUser();
    [$order, $payment] = createOrderForNotification($user);

    $user->notify(new OrderStatusUpdated($order, 'Pesanan Anda sedang diproses.'));

    $response = $this->actingAs($user)->get('/');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->has('notifications', 1)
        ->where('notifications.0.data.order_id', $order->id)
    );
});

test('pemilik notifikasi dapat menandai notifikasi miliknya sebagai sudah dibaca', function () {
    $user = notifUser();
    [$order, $payment] = createOrderForNotification($user);

    $user->notify(new OrderStatusUpdated($order, 'Pesanan Anda telah dikirim.'));
    $notification = $user->unreadNotifications()->first();

    $response = $this->actingAs($user)
        ->from('/')
        ->post("/notifications/{$notification->id}/mark-as-read");

    $response->assertRedirect('/');

    expect($notification->fresh()->read_at)->not->toBeNull()
        ->and($user->unreadNotifications()->count())->toBe(0);
});

test('user lain tidak dapat menandai notifikasi milik orang lain sebagai sudah dibaca', function () {
    $owner = notifUser();
    $attacker = notifUser();
    [$order, $payment] = createOrderForNotification($owner);

    $owner->notify(new OrderStatusUpdated($order, 'Pesanan Anda telah tiba.'));
    $notification = $owner->unreadNotifications()->first();

    $response = $this->actingAs($attacker)
        ->post("/notifications/{$notification->id}/mark-as-read");

    $response->assertStatus(404);
    expect($notification->fresh()->read_at)->toBeNull();
});

test('tamu tidak dapat menandai notifikasi sebagai sudah dibaca', function () {
    $user = notifUser();
    [$order, $payment] = createOrderForNotification($user);

    $user->notify(new OrderStatusUpdated($order, 'Notifikasi test.'));
    $notification = $user->unreadNotifications()->first();

    $response = $this->post("/notifications/{$notification->id}/mark-as-read");

    $response->assertRedirect('/login');
});
