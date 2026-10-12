<?php

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\User;
use App\Services\MidtransService;
use Inertia\Testing\AssertableInertia as Assert;

function paymentUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

function draftOrderFor(User $user, int $harga = 200000, string $size = 'L'): Order
{
    $product = Product::create([
        'name' => 'Batik untuk Pembayaran',
        'harga' => $harga,
        'description' => 'desc',
    ]);
    $productSize = ProductSize::create([
        'product_id' => $product->id,
        'size' => $size,
        'stock' => 10,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'total_harga' => $harga * 2,
        'status' => 'draft',
        'expires_at' => now()->addMinutes(10),
        'name' => 'Budi Santoso',
        'email' => 'budi@contoh.test',
        'phone' => '081234567890',
        'alamat' => 'Jl. Pembayaran No. 7',
    ]);

    OrderDetail::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_size_id' => $productSize->id,
        'size' => $size,
        'quantity' => 2,
        'harga' => $harga,
    ]);

    return $order;
}

test('halaman pembayaran mengirim order yang belum punya payment', function () {
    $user = paymentUser();
    $order = draftOrderFor($user);

    $this->actingAs($user)->get('/pembayaran')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Payment')
            ->has('unpaidOrder')
            ->where('unpaidOrder.id', $order->id)
            ->where('unpaidOrder.total_harga', '400000.00')
        );
});

test('halaman pembayaran tidak mengirim order milik user lain', function () {
    $user = paymentUser();
    $other = paymentUser();
    draftOrderFor($other);

    $this->actingAs($user)->get('/pembayaran')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('unpaidOrder', null));
});

test('tamu tidak bisa membuat payment', function () {
    $this->post('/payment')->assertRedirect('/login');
});

test('initiate payment membuat payment, mengisi payment_details, dan memindahkan order ke pending_payment', function () {
    $user = paymentUser();
    $order = draftOrderFor($user);

    $midtrans = $this->mock(MidtransService::class);
    $midtrans->shouldReceive('getSnapToken')
        ->once()
        ->andReturnUsing(fn (array $params) => 'snap-token-' . $params['transaction_details']['order_id']);

    $this->actingAs($user)->post('/payment')->assertRedirect(route('pembayaran'));

    $payment = Payment::where('order_id', $order->id)->first();

    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe('pending')
        ->and($payment->total_harga)->toBe('400000.00')
        ->and($payment->snap_token)->toStartWith('snap-token-')
        ->and($payment->transaction_id)->not->toBeNull();

    // payment_details dulu tidak pernah diisi kode mana pun; sekarang rincian
    // item ikut tersalin dari order_details.
    $details = PaymentDetail::where('payment_id', $payment->id)->get();

    expect($details)->toHaveCount(1)
        ->and($details->first()->product_id)->toBe($order->details->first()->product_id)
        ->and($details->first()->size)->toBe('L')
        ->and($details->first()->quantity)->toBe(2)
        ->and($details->first()->harga)->toBe('200000.00')
        ->and($details->first()->name)->toBe('Budi Santoso')
        ->and($details->first()->phone)->toBe('081234567890');

    $order->refresh();
    expect($order->status)->toBe('pending_payment');
});

test('initiate payment idempoten: payment pending yang sudah ada tidak dibuat ulang', function () {
    $user = paymentUser();
    $order = draftOrderFor($user);

    Payment::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'transaction_id' => 'TX-LAMA',
        'total_harga' => 400000,
        'status' => 'pending',
        'snap_token' => 'token-lama',
    ]);

    // Alur idempoten tidak boleh memanggil API Midtrans sama sekali.
    $this->mock(MidtransService::class)->shouldNotReceive('getSnapToken');

    $this->actingAs($user)->post('/payment')->assertRedirect(route('pembayaran'));

    expect(Payment::where('order_id', $order->id)->count())->toBe(1);
});

test('gagal menghubungi Midtrans tidak meninggalkan payment yatim', function () {
    $user = paymentUser();
    $order = draftOrderFor($user);

    $this->mock(MidtransService::class)
        ->shouldReceive('getSnapToken')
        ->andThrow(new RuntimeException('Midtrans timeout'));

    $this->actingAs($user)->from('/pembayaran')->post('/payment')
        ->assertRedirect('/pembayaran')
        ->assertSessionHasErrors('message');

    expect(Payment::where('order_id', $order->id)->count())->toBe(0);

    $order->refresh();
    expect($order->status)->toBe('draft');
});

test('order yang sudah kedaluwarsa tidak bisa dibayar', function () {
    $user = paymentUser();
    $order = draftOrderFor($user);
    $order->update(['expires_at' => now()->subMinute()]);

    $this->mock(MidtransService::class)->shouldNotReceive('getSnapToken');

    $this->actingAs($user)->from('/pembayaran')->post('/payment')
        ->assertRedirect('/pembayaran')
        ->assertSessionHas('error');

    expect(Payment::where('order_id', $order->id)->count())->toBe(0);
});

test('order milik user lain tidak bisa dibayar oleh user ini', function () {
    $user = paymentUser();
    $other = paymentUser();
    $order = draftOrderFor($other);

    $this->mock(MidtransService::class)->shouldNotReceive('getSnapToken');

    $this->actingAs($user)->from('/pembayaran')->post('/payment')
        ->assertRedirect('/pembayaran')
        ->assertSessionHas('error');

    expect(Payment::where('order_id', $order->id)->count())->toBe(0);
});
