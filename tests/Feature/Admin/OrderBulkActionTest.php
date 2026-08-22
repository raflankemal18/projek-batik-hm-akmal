<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Support\Facades\Notification;

function orderBulkAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function orderBulkUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

function createOrderWithPaymentForBulk(User $user, string $fulfillment = 'processing'): array
{
    $order = Order::create([
        'user_id' => $user->id,
        'total_harga' => 150000,
        'status' => 'paid',
        'fulfillment_status' => $fulfillment,
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

test('tamu tidak dapat mengakses bulk delete orders', function () {
    $user = orderBulkUser();
    [, $payment] = createOrderWithPaymentForBulk($user);

    $this->delete('/admin/orders/bulk', ['ids' => [$payment->id]])
        ->assertRedirect('/login');

    expect(Payment::find($payment->id))->not->toBeNull();
});

test('user biasa tidak dapat mengakses bulk delete orders', function () {
    $user = orderBulkUser();
    [, $payment] = createOrderWithPaymentForBulk($user);

    $this->actingAs($user)
        ->delete('/admin/orders/bulk', ['ids' => [$payment->id]])
        ->assertStatus(403);

    expect(Payment::find($payment->id))->not->toBeNull();
});

test('tamu tidak dapat mengakses bulk fulfillment update', function () {
    $user = orderBulkUser();
    [, $payment] = createOrderWithPaymentForBulk($user);

    $this->patch('/admin/orders/bulk-fulfillment', ['ids' => [$payment->id], 'status' => 'shipped'])
        ->assertRedirect('/login');
});

test('user biasa tidak dapat mengakses bulk fulfillment update', function () {
    $user = orderBulkUser();
    [, $payment] = createOrderWithPaymentForBulk($user);

    $this->actingAs($user)
        ->patch('/admin/orders/bulk-fulfillment', ['ids' => [$payment->id], 'status' => 'shipped'])
        ->assertStatus(403);
});

test('admin dapat bulk delete payments terpilih', function () {
    $admin = orderBulkAdmin();
    $user1 = orderBulkUser();
    $user2 = orderBulkUser();

    [, $pay1] = createOrderWithPaymentForBulk($user1);
    [, $pay2] = createOrderWithPaymentForBulk($user2);
    [, $payKeep] = createOrderWithPaymentForBulk($user1);

    $response = $this->actingAs($admin)
        ->from('/admin/orders')
        ->delete('/admin/orders/bulk', ['ids' => [$pay1->id, $pay2->id]]);

    $response->assertRedirect('/admin/orders');
    $response->assertSessionHas('success', 'Pesanan terpilih berhasil dihapus.');

    expect(Payment::find($pay1->id))->toBeNull()
        ->and(Payment::find($pay2->id))->toBeNull()
        ->and(Payment::find($payKeep->id))->not->toBeNull();
});

test('bulk delete hanya menghapus payment yang diminta', function () {
    $admin = orderBulkAdmin();
    $user = orderBulkUser();
    [, $payDelete] = createOrderWithPaymentForBulk($user);
    [, $payKeep] = createOrderWithPaymentForBulk($user);

    $this->actingAs($admin)->delete('/admin/orders/bulk', ['ids' => [$payDelete->id]]);

    expect(Payment::find($payDelete->id))->toBeNull()
        ->and(Payment::find($payKeep->id))->not->toBeNull();
});

test('admin dapat bulk update fulfillment status untuk banyak pesanan', function () {
    $admin = orderBulkAdmin();
    $user1 = orderBulkUser();
    $user2 = orderBulkUser();

    [$order1, $pay1] = createOrderWithPaymentForBulk($user1, 'processing');
    [$order2, $pay2] = createOrderWithPaymentForBulk($user2, 'processing');

    $response = $this->actingAs($admin)
        ->from('/admin/orders')
        ->patch('/admin/orders/bulk-fulfillment', [
            'ids' => [$pay1->id, $pay2->id],
            'status' => 'shipped',
        ]);

    $response->assertRedirect('/admin/orders');
    $response->assertSessionHas('success', 'Status pengiriman terpilih berhasil diperbarui.');

    expect($order1->fresh()->fulfillment_status)->toBe('shipped')
        ->and($order2->fresh()->fulfillment_status)->toBe('shipped');
});

test('bulk fulfillment mengirim notifikasi ke setiap pemilik order', function () {
    Notification::fake();
    $admin = orderBulkAdmin();
    $user1 = orderBulkUser();
    $user2 = orderBulkUser();

    [, $pay1] = createOrderWithPaymentForBulk($user1);
    [, $pay2] = createOrderWithPaymentForBulk($user2);

    $this->actingAs($admin)->patch('/admin/orders/bulk-fulfillment', [
        'ids' => [$pay1->id, $pay2->id],
        'status' => 'delivered',
    ]);

    Notification::assertSentTo($user1, OrderStatusUpdated::class);
    Notification::assertSentTo($user2, OrderStatusUpdated::class);
});

test('bulk fulfillment validasi gagal jika ids atau status tidak valid', function () {
    $admin = orderBulkAdmin();

    $response = $this->actingAs($admin)->patch('/admin/orders/bulk-fulfillment', [
        'ids' => [999999],
        'status' => 'invalid_status',
    ]);

    $response->assertSessionHasErrors(['ids.0', 'status']);
});

test('bulk delete validasi gagal jika ids tidak dikirim', function () {
    $admin = orderBulkAdmin();

    $response = $this->actingAs($admin)->delete('/admin/orders/bulk', []);

    $response->assertSessionHasErrors('ids');
});

test('bulk delete validasi gagal jika ids bukan array', function () {
    $admin = orderBulkAdmin();

    $response = $this->actingAs($admin)->delete('/admin/orders/bulk', [
        'ids' => 'not-array',
    ]);

    $response->assertSessionHasErrors('ids');
});

test('bulk fulfillment validasi gagal jika ids bukan array', function () {
    $admin = orderBulkAdmin();

    $response = $this->actingAs($admin)->patch('/admin/orders/bulk-fulfillment', [
        'ids' => 'not-array',
        'status' => 'shipped',
    ]);

    $response->assertSessionHasErrors('ids');
});
