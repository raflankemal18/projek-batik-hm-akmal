<?php

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * P6 & P7 — sisi pembeli: halaman detail harus menampilkan motif yang tersedia
 * (hanya yang aktif), ukuran kain sebagai spesifikasi, dan motif harus ikut
 * terbawa ke halaman pembayaran/invoice lewat snapshot.
 */
function publicMotifProduct(): Product
{
    $product = Product::create([
        'name' => 'Batik Cap 2 Warna',
        'harga' => 85000,
        'category' => 'wanita',
        'has_variants' => true,
        'fabric_size' => '2 m x 1,1 m',
    ]);

    ProductVariant::create([
        'product_id' => $product->id, 'name' => 'Parang',
        'price' => 85000, 'stock' => 5, 'is_active' => true,
    ]);
    ProductVariant::create([
        'product_id' => $product->id, 'name' => 'Kawung',
        'price' => 105000, 'stock' => 0, 'is_active' => false,
    ]);

    return $product;
}

test('halaman detail produk mengirim motif aktif saja beserta ukuran kain', function () {
    $product = publicMotifProduct();

    $this->get("/products/{$product->id}")->assertOk()->assertInertia(function (Assert $page) use ($product) {
        $page->component('ProductDetail');

        $payload = $page->toArray()['props']['products'];

        expect($payload['has_variants'])->toBeTrue()
            ->and($payload['fabric_size'])->toBe('2 m x 1,1 m')
            ->and(collect($payload['active_variants'])->pluck('name')->all())->toBe(['Parang']);
    });
});

test('produk tanpa motif tetap mengirim ukuran untuk tombol Pilih Ukuran', function () {
    $product = Product::create([
        'name' => 'Batik Legacy',
        'harga' => 75000,
        'has_variants' => false,
        'fabric_size' => '2 m x 1,1 m',
    ]);
    App\Models\ProductSize::create(['product_id' => $product->id, 'size' => 'L', 'stock' => 4]);

    $this->get("/products/{$product->id}")->assertOk()->assertInertia(function (Assert $page) {
        $payload = $page->toArray()['props']['products'];

        expect($payload['has_variants'])->toBeFalse()
            ->and($payload['sizes'])->toHaveCount(1);
    });
});

test('keranjang memakai harga motif dan menandai baris bermotif', function () {
    $user = User::factory()->create(['role' => 'user']);
    $product = publicMotifProduct();
    $parang = ProductVariant::where('product_id', $product->id)->where('name', 'Parang')->firstOrFail();

    App\Models\Cart::create(['user_id' => $user->id]);
    App\Models\CartDetail::create([
        'cart_id' => App\Models\Cart::where('user_id', $user->id)->value('id'),
        'product_id' => $product->id,
        'product_variant_id' => $parang->id,
        'size' => 'Parang',
        'quantity' => 2,
    ]);

    $this->actingAs($user)->get('/cart')->assertOk()->assertInertia(function (Assert $page) use ($parang) {
        $detail = $page->toArray()['props']['keranjangs'][0]['details'][0];

        expect($detail['variant']['name'])->toBe('Parang')
            ->and($detail['variant']['price'])->toBe('85000.00');
    });

    // Halaman checkout membawa motif + harga motif, bukan harga produk.
    $this->actingAs($user)->get('/co')->assertOk()->assertInertia(function (Assert $page) {
        $detail = $page->toArray()['props']['orders'][0]['order_details'][0];

        // Dikirim sebagai angka bulat (JSON 85000), bukan float.
        expect($detail['motif_name'])->toBe('Parang')
            ->and((float) $detail['harga'])->toBe(85000.0)
            ->and($detail['is_variant'])->toBeTrue();
    });
});

test('invoice menampilkan snapshot motif, bukan nama motif katalog', function () {
    $user = User::factory()->create(['role' => 'user']);
    $product = publicMotifProduct();
    $parang = ProductVariant::where('product_id', $product->id)->where('name', 'Parang')->firstOrFail();

    $order = Order::create([
        'user_id' => $user->id,
        'total_harga' => 85000,
        'status' => 'paid',
    ]);
    OrderDetail::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $parang->id,
        'motif_name' => 'Parang',
        'size' => 'Parang',
        'quantity' => 1,
        'harga' => 85000,
    ]);

    $payment = Payment::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'transaction_id' => 'TX-INVOICE-MOTIF',
        'total_harga' => 85000,
        'status' => 'success',
        'paid_at' => now(),
    ]);
    PaymentDetail::create([
        'payment_id' => $payment->id,
        'product_id' => $product->id,
        'product_variant_id' => $parang->id,
        'motif_name' => 'Parang',
        'name' => $user->name,
        'email' => $user->email,
        'phone' => '08123',
        'alamat' => 'Jl. Test',
        'size' => 'Parang',
        'quantity' => 1,
        'harga' => 85000,
    ]);

    // Motif diganti namanya di katalog; transaksi lama tidak boleh ikut berubah.
    $parang->update(['name' => 'Parang Sinden']);

    $this->actingAs($user)->get("/payments/{$payment->id}/invoice")
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $detail = $page->toArray()['props']['payment']['order']['details'][0];

            expect($detail['motif_name'])->toBe('Parang');
        });
});

test('admin melihat motif di detail pembayaran, bukan ukuran lama', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'user']);
    $product = publicMotifProduct();
    $parang = ProductVariant::where('product_id', $product->id)->where('name', 'Parang')->firstOrFail();

    $order = Order::create(['user_id' => $user->id, 'total_harga' => 85000, 'status' => 'paid']);
    OrderDetail::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $parang->id,
        'motif_name' => 'Parang',
        'size' => 'Parang',
        'quantity' => 1,
        'harga' => 85000,
    ]);

    $payment = Payment::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'transaction_id' => 'TX-ADMIN-MOTIF',
        'total_harga' => 85000,
        'status' => 'success',
        'paid_at' => now(),
    ]);
    PaymentDetail::create([
        'payment_id' => $payment->id,
        'product_id' => $product->id,
        'product_variant_id' => $parang->id,
        'motif_name' => 'Parang',
        'name' => $user->name,
        'email' => $user->email,
        'phone' => '08123',
        'alamat' => 'Jl. Test',
        'size' => 'Parang',
        'quantity' => 1,
        'harga' => 85000,
    ]);

    $this->actingAs($admin)->get('/admin/orders')->assertOk()->assertInertia(function (Assert $page) {
        $detail = $page->toArray()['props']['payments']['data'][0]['payment_details'][0];

        expect($detail['motif_name'])->toBe('Parang');
    });
});
