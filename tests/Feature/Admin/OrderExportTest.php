<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

function orderExportAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function orderExportUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

function createPaymentForExport(User $user, string $status = 'success', string $total = '150000'): Payment
{
    $order = Order::create([
        'user_id' => $user->id,
        'total_harga' => $total,
        'status' => 'paid',
    ]);

    return Payment::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'transaction_id' => 'INV-' . uniqid(),
        'total_harga' => $total,
        'status' => $status,
        'paid_at' => $status === 'success' ? now() : null,
    ]);
}

test('tamu tidak dapat mengakses export orders csv', function () {
    $this->get('/admin/orders/export')->assertRedirect('/login');
});

test('user biasa tidak dapat mengakses export orders csv', function () {
    $user = orderExportUser();

    $this->actingAs($user)->get('/admin/orders/export')->assertStatus(403);
});

test('admin dapat export orders csv dengan content-type dan header yang benar', function () {
    $admin = orderExportAdmin();
    $user = orderExportUser();
    $payment = createPaymentForExport($user, 'success', '200000');

    $response = $this->actingAs($admin)->get('/admin/orders/export');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->headers->get('Content-Disposition'))->toContain('orders_export_');
    expect($response->headers->get('Content-Disposition'))->toContain('.csv');

    $content = $response->streamedContent();
    expect($content)->toContain('Order ID')
        ->and($content)->toContain('Tanggal')
        ->and($content)->toContain('User Email')
        ->and($content)->toContain('Total Harga')
        ->and($content)->toContain('Status')
        ->and($content)->toContain((string) $payment->id)
        ->and($content)->toContain($user->email)
        ->and($content)->toContain('200000')
        ->and($content)->toContain('success');
});

test('isi csv export sesuai data payments yang ada', function () {
    $admin = orderExportAdmin();
    $user1 = orderExportUser();
    $user2 = orderExportUser();

    $p1 = createPaymentForExport($user1, 'success', '100000');
    $p2 = createPaymentForExport($user2, 'pending', '250000');

    $response = $this->actingAs($admin)->get('/admin/orders/export');

    $content = $response->streamedContent();
    // header + 2 rows = 3 lines (trim trailing newline)
    $lines = array_filter(explode("\n", trim($content)));
    expect($lines)->toHaveCount(3);

    // cek kedua payment muncul
    expect($content)->toContain((string) $p1->id)
        ->and($content)->toContain((string) $p2->id)
        ->and($content)->toContain($user1->email)
        ->and($content)->toContain($user2->email)
        ->and($content)->toContain('100000')
        ->and($content)->toContain('250000');
});

test('export csv kosong tetap mengembalikan header', function () {
    $admin = orderExportAdmin();

    $response = $this->actingAs($admin)->get('/admin/orders/export');

    $response->assertOk();
    $content = $response->streamedContent();
    $lines = array_filter(explode("\n", trim($content)));
    expect($lines)->toHaveCount(1);
    expect($content)->toContain('Order ID');
});

test('admin dapat export orders pdf via query format=pdf', function () {
    $admin = orderExportAdmin();
    $user = orderExportUser();
    createPaymentForExport($user);

    $response = $this->actingAs($admin)->get('/admin/orders/export?format=pdf');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('.pdf');
    // dompdf output dimulai dengan %PDF
    expect($response->getContent())->toContain('%PDF');
});

test('tamu tidak dapat mengakses export orders pdf', function () {
    $this->get('/admin/orders/export?format=pdf')->assertRedirect('/login');
});

test('user biasa tidak dapat mengakses export orders pdf', function () {
    $user = orderExportUser();

    $this->actingAs($user)->get('/admin/orders/export?format=pdf')->assertStatus(403);
});
