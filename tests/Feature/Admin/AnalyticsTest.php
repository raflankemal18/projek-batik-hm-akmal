<?php

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\User;

function analyticsAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function analyticsUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

function createAnalyticsProduct(string $category = 'Batik Tulis'): Product
{
    return Product::create([
        'name' => 'Batik ' . uniqid(),
        'harga' => 100000,
        'description' => 'deskripsi',
        'category' => $category,
    ]);
}

function createAnalyticsPayment(User $user, string $status = 'success', int $amount = 100000, ?string $date = null): Payment
{
    $order = Order::create([
        'user_id' => $user->id,
        'total_harga' => $amount,
        'status' => 'paid',
    ]);

    $payment = Payment::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'transaction_id' => 'INV-' . uniqid(),
        'total_harga' => $amount,
        'status' => $status,
        'paid_at' => $status === 'success' ? now() : null,
    ]);

    if ($date) {
        Payment::where('id', $payment->id)->update([
            'created_at' => \Carbon\Carbon::parse($date),
            'updated_at' => \Carbon\Carbon::parse($date),
        ]);
        $payment->refresh();
    }

    return $payment;
}

test('tamu tidak dapat mengakses analytics', function () {
    $this->get('/admin/analytics')->assertRedirect('/login');
});

test('user biasa tidak dapat mengakses analytics', function () {
    $user = analyticsUser();

    $this->actingAs($user)->get('/admin/analytics')->assertStatus(403);
});

test('admin dapat mengakses analytics dengan struktur json yang benar', function () {
    $admin = analyticsAdmin();

    $response = $this->actingAs($admin)->get('/admin/analytics');

    $response->assertOk();
    $response->assertJsonStructure(['sales', 'categories']);
    $data = $response->json();
    expect($data['sales'])->toBeArray();
    expect($data['categories'])->toBeArray();
});

test('analytics sales agregat 7 hari terakhir sesuai data', function () {
    $admin = analyticsAdmin();
    $user = analyticsUser();

    $today = now()->format('Y-m-d');
    $threeDaysAgo = now()->subDays(3)->format('Y-m-d');
    $eightDaysAgo = now()->subDays(8)->format('Y-m-d');

    // success dalam 7 hari — harus masuk agregat
    createAnalyticsPayment($user, 'success', 100000, $today);
    createAnalyticsPayment($user, 'success', 200000, $threeDaysAgo);
    // success di luar 7 hari — tidak masuk
    createAnalyticsPayment($user, 'success', 500000, $eightDaysAgo);
    // pending dalam 7 hari — tidak masuk (hanya success)
    createAnalyticsPayment($user, 'pending', 300000, $today);

    $response = $this->actingAs($admin)->get('/admin/analytics');
    $response->assertOk();

    $data = $response->json();

    // sales hanya 2 tanggal (today + 3 days ago)
    expect($data['sales'])->toHaveCount(2);

    $totals = collect($data['sales'])->pluck('total', 'date');
    // total di hari ini = 100000 (pending tidak ikut)
    expect((int) $totals[$today])->toBe(100000);
    expect((int) $totals[$threeDaysAgo])->toBe(200000);
    // 8 days ago tidak ada
    expect($totals->has($eightDaysAgo))->toBeFalse();
});

test('analytics tanpa data mengembalikan array kosong', function () {
    $admin = analyticsAdmin();

    $response = $this->actingAs($admin)->get('/admin/analytics');

    $response->assertOk();
    $data = $response->json();
    expect($data['sales'])->toBeArray()->toHaveCount(0);
    expect($data['categories'])->toBeArray()->toHaveCount(0);
});

test('analytics categories agregat sesuai order detail', function () {
    $admin = analyticsAdmin();
    $user = analyticsUser();

    $productA = createAnalyticsProduct('Batik Tulis');
    $productB = createAnalyticsProduct('Batik Cap');

    $order = Order::create([
        'user_id' => $user->id,
        'total_harga' => 300000,
        'status' => 'paid',
    ]);
    $sizeA = ProductSize::create(['product_id' => $productA->id, 'size' => 'M', 'stock' => 10]);
    $sizeB = ProductSize::create(['product_id' => $productB->id, 'size' => 'L', 'stock' => 10]);

    // 3 qty Batik Tulis, 2 qty Batik Cap
    OrderDetail::create([
        'order_id' => $order->id,
        'product_id' => $productA->id,
        'product_size_id' => $sizeA->id,
        'size' => 'M',
        'quantity' => 3,
        'harga' => 100000,
    ]);
    OrderDetail::create([
        'order_id' => $order->id,
        'product_id' => $productB->id,
        'product_size_id' => $sizeB->id,
        'size' => 'L',
        'quantity' => 2,
        'harga' => 100000,
    ]);

    Payment::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'transaction_id' => 'INV-' . uniqid(),
        'total_harga' => 500000,
        'status' => 'success',
        'paid_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get('/admin/analytics');
    $response->assertOk();

    $data = $response->json();
    expect($data['categories'])->toHaveCount(2);

    $cats = collect($data['categories'])->pluck('total_sold', 'category');
    expect((int) $cats['Batik Tulis'])->toBe(3);
    expect((int) $cats['Batik Cap'])->toBe(2);
});

test('analytics categories mengabaikan produk tanpa kategori', function () {
    $admin = analyticsAdmin();
    $user = analyticsUser();

    $productNoCat = Product::create([
        'name' => 'Batik Tanpa Kategori',
        'harga' => 100000,
        'description' => 'd',
        'category' => null,
    ]);
    $size = ProductSize::create(['product_id' => $productNoCat->id, 'size' => 'M', 'stock' => 10]);
    $order = Order::create(['user_id' => $user->id, 'total_harga' => 100000, 'status' => 'paid']);
    OrderDetail::create([
        'order_id' => $order->id,
        'product_id' => $productNoCat->id,
        'product_size_id' => $size->id,
        'size' => 'M',
        'quantity' => 5,
        'harga' => 100000,
    ]);

    $response = $this->actingAs($admin)->get('/admin/analytics');
    $data = $response->json();

    // tidak ada kategori yang terhitung karena whereNotNull category
    expect($data['categories'])->toHaveCount(0);
});
