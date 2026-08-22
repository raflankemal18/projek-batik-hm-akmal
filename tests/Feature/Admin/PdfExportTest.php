<?php

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

function pdfExportAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function pdfExportUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

function createPaymentForPdf(User $user): Payment
{
    $order = Order::create([
        'user_id' => $user->id,
        'total_harga' => 180000,
        'status' => 'paid',
    ]);

    return Payment::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'transaction_id' => 'INV-' . uniqid(),
        'total_harga' => 180000,
        'status' => 'success',
        'paid_at' => now(),
    ]);
}

test('tamu tidak dapat mengakses export pdf orders', function () {
    $this->get('/admin/orders/export?format=pdf')->assertRedirect('/login');
});

test('user biasa tidak dapat mengakses export pdf orders', function () {
    $user = pdfExportUser();

    $this->actingAs($user)->get('/admin/orders/export?format=pdf')->assertStatus(403);
});

test('tamu tidak dapat mengakses export pdf logs', function () {
    $this->get('/admin/logs/export?format=pdf')->assertRedirect('/login');
});

test('user biasa tidak dapat mengakses export pdf logs', function () {
    $user = pdfExportUser();

    $this->actingAs($user)->get('/admin/logs/export?format=pdf')->assertStatus(403);
});

test('admin dapat export orders pdf dengan content-type pdf', function () {
    $admin = pdfExportAdmin();
    $user = pdfExportUser();
    createPaymentForPdf($user);
    createPaymentForPdf($user);

    $response = $this->actingAs($admin)->get('/admin/orders/export?format=pdf');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('.pdf');
    expect($response->getContent())->toContain('%PDF');
});

test('admin dapat export logs pdf dengan content-type pdf', function () {
    $admin = pdfExportAdmin();
    ActivityLog::create([
        'type' => 'admin',
        'action' => 'test.pdf',
        'description' => 'log untuk pdf',
        'user_id' => $admin->id,
        'email' => $admin->email,
        'context' => [],
    ]);

    $response = $this->actingAs($admin)->get('/admin/logs/export?format=pdf');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('.pdf');
    expect($response->getContent())->toContain('%PDF');
});

test('admin dapat export logs csv dengan content-type csv', function () {
    $admin = pdfExportAdmin();
    ActivityLog::create([
        'type' => 'admin',
        'action' => 'test.csv',
        'description' => 'log csv',
        'user_id' => $admin->id,
        'email' => $admin->email,
        'context' => [],
    ]);

    $response = $this->actingAs($admin)->get('/admin/logs/export');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->headers->get('Content-Disposition'))->toContain('activity_logs.csv');

    $content = $response->streamedContent();
    expect($content)->toContain('ID')
        ->and($content)->toContain('Action')
        ->and($content)->toContain('test.csv');
});

test('admin dapat export logs csv kosong tetap header', function () {
    $admin = pdfExportAdmin();

    $response = $this->actingAs($admin)->get('/admin/logs/export');

    $response->assertOk();
    $content = $response->streamedContent();
    expect($content)->toContain('ID')
        ->and($content)->toContain('Type');
});

test('user biasa tidak dapat export logs csv', function () {
    $user = pdfExportUser();

    $this->actingAs($user)->get('/admin/logs/export')->assertStatus(403);
});
