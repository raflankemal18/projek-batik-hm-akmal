<?php

use App\Models\Cart;
use App\Models\CartDetail;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Models\User;

/**
 * Perbaikan dari audit 2026-10-13:
 *   1. Dua sumber stok (produk bermotif masih bisa dibeli lewat jalur ukuran lama).
 *   2. Webhook pembayaran belum idempoten terhadap request yang datang bersamaan.
 *   5. Soft-delete variasi tidak memeriksa payment_details.
 */

/** Produk yang sudah bermotif TAPI masih punya products_sizes (situasi setelah migrasi). */
function dualStockProduct(): array
{
    $product = Product::create([
        'name' => 'Batik Cap 2 Warna',
        'harga' => 85000,
        'has_variants' => true,
    ]);

    $size = ProductSize::create(['product_id' => $product->id, 'size' => 'M', 'stock' => 99]);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'M',
        'price' => 85000,
        'stock' => 2,
        'is_active' => true,
    ]);

    return [$product, $size, $variant];
}

// ---------- Temuan 1: satu sumber stok ----------

test('produk bermotif tidak bisa dibeli lewat jalur ukuran: stok ukuran tidak ikut berkurang', function () {
    [$product, $size] = dualStockProduct();
    $user = User::factory()->create(['role' => 'user']);

    // Permintaan gaya lama: hanya mengirim size, tanpa product_variant_id.
    $this->actingAs($user)->post('/cart/add', [
        'product_id' => $product->id,
        'size' => 'M',
        'quantity' => 1,
    ]);

    // Alih-alih memakai stok products_sizes (99), permintaan ini diarahkan ke motif
    // bernama sama supaya TETAP ADA satu sumber stok: product_variants.
    expect(CartDetail::count())->toBe(1)
        ->and(CartDetail::first()->product_variant_id)->not->toBeNull()
        ->and($size->refresh()->stock)->toBe(99);

    $variant = ProductVariant::where('product_id', $product->id)->first();

    // Membeli sebanyak 5 padahal motifnya stok 2 harus ditolak meski products_sizes
    // masih({\'stock\': 99}) — inilah risiko yang dilaporkan audit.
    $this->actingAs($user)->post('/cart/add', [
        'product_id' => $product->id,
        'size' => 'M',
        'quantity' => 5,
    ]);

    expect($variant->refresh()->stock)->toBe(2);
});

test('produk bermotif dengan ukuran yang tidak punya motif ditolak, bukan jatuh ke stok ukuran', function () {
    [$product, $size] = dualStockProduct();
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user)->from('/products')->post('/cart/add', [
        'product_id' => $product->id,
        'size' => 'XL',
        'quantity' => 1,
    ])->assertRedirect('/products')->assertSessionHasErrors('stock');

    expect(CartDetail::count())->toBe(0)
        ->and($size->refresh()->stock)->toBe(99);
});

test('checkout baris lama pada produk bermotif memakai stok motif, bukan stok ukuran', function () {
    [$product, $size, $variant] = dualStockProduct();
    $user = User::factory()->create(['role' => 'user']);

    // Baris keranjang dibuat langsung (mis. dibuat sebelum migrasi / oleh frontend lama).
    $cart = Cart::create(['user_id' => $user->id]);
    CartDetail::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'size' => 'M',
        'quantity' => 2,
    ]);

    $this->actingAs($user)->post('/checkout', [
        'name' => 'Budi',
        'email' => 'budi@contoh.test',
        'phone' => '08123',
        'alamat' => 'Jl. Test',
    ])->assertRedirect(route('pembayaran'));

    $detail = OrderDetail::firstOrFail();

    expect($detail->product_variant_id)->toBe($variant->id)
        ->and($detail->motif_name)->toBe('M')
        ->and($variant->refresh()->stock)->toBe(0)
        // products_sizes tidak boleh tersentuh untuk produk bermotif.
        ->and($size->refresh()->stock)->toBe(99);
});

test('baris keranjang tanpa motif yang sudah tidak ada ditolak saat checkout dengan pesan jelas', function () {
    [$product, $size] = dualStockProduct();
    $user = User::factory()->create(['role' => 'user']);

    $cart = Cart::create(['user_id' => $user->id]);
    CartDetail::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'size' => 'XL',
        'quantity' => 1,
    ]);

    $this->actingAs($user)->from('/co')->post('/checkout', [
        'name' => 'Budi',
        'email' => 'budi@contoh.test',
        'phone' => '08123',
        'alamat' => 'Jl. Test',
    ])->assertRedirect('/co')->assertSessionHasErrors('stock');

    expect(OrderDetail::count())->toBe(0)
        ->and($size->refresh()->stock)->toBe(99);
});

test('updateQuantity pada baris lama produk bermotif divalidasi terhadap stok motif', function () {
    [$product, $size, $variant] = dualStockProduct();
    $user = User::factory()->create(['role' => 'user']);

    $cart = Cart::create(['user_id' => $user->id]);
    $detail = CartDetail::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'size' => 'M',
        'quantity' => 1,
    ]);

    $this->actingAs($user)->post('/cart/update', [
        'cart_detail_id' => $detail->id,
        'quantity' => 5,
    ])->assertSessionHasErrors('stock');

    expect($detail->refresh()->quantity)->toBe(1);

    $this->actingAs($user)->post('/cart/update', [
        'cart_detail_id' => $detail->id,
        'quantity' => 2,
    ])->assertSessionHasNoErrors();

    expect($detail->refresh()->quantity)->toBe(2)
        ->and($detail->product_variant_id)->toBe($variant->id);
});

test('produk tanpa motif tetap memakai stok products_sizes seperti sebelumnya', function () {
    $product = Product::create(['name' => 'Batik Legacy', 'harga' => 120000, 'has_variants' => false]);
    $size = ProductSize::create(['product_id' => $product->id, 'size' => 'L', 'stock' => 4]);
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user)->post('/cart/add', [
        'product_id' => $product->id,
        'size' => 'L',
        'quantity' => 2,
    ])->assertRedirect();

    $this->actingAs($user)->post('/checkout', [
        'name' => 'Budi', 'email' => 'budi@contoh.test', 'phone' => '08123', 'alamat' => 'Jl. Test',
    ])->assertRedirect(route('pembayaran'));

    // Checkout menghapus keranjang, jadi yang diperiksa adalah order detail-nya.
    expect(OrderDetail::firstOrFail()->product_variant_id)->toBeNull()
        ->and($size->refresh()->stock)->toBe(2);
});

test('admin mengedit produk bermotif tidak menghapus products_sizes (histori order aman)', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [$product, $size] = dualStockProduct();

    $order = Order::create(['user_id' => User::factory()->create(['role' => 'user'])->id, 'total_harga' => 85000, 'status' => 'paid']);
    OrderDetail::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_size_id' => $size->id,
        'size' => 'M',
        'quantity' => 1,
        'harga' => 85000,
    ]);

    $this->actingAs($admin)->post("/admin/products/{$product->id}", [
        '_method' => 'PUT',
        'name' => 'Batik Cap 2 Warna (rev)',
        'harga' => 95000,
        'description' => 'deskripsi',
        'category' => 'pria',
        'sizes' => [], // form lama tetap mengirim sizes[]
    ]);

    // products_sizes tetap ada karena order_details.product_size_id masih merujuknya.
    expect(ProductSize::where('product_id', $product->id)->count())->toBe(1)
        ->and(OrderDetail::first()->product_size_id)->toBe($size->id);
});

// ---------- Temuan 2: webhook idempoten ----------

function webhookPaymentWithVariant(string $status = 'pending'): array
{
    $product = Product::create(['name' => 'Batik Webhook', 'harga' => 100000, 'has_variants' => true]);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Parang',
        'price' => 100000,
        'stock' => 5,
        'is_active' => true,
    ]);

    $user = User::factory()->create(['role' => 'user']);
    $order = Order::create([
        'user_id' => $user->id,
        'total_harga' => 100000,
        'status' => 'pending_payment',
    ]);
    OrderDetail::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'motif_name' => 'Parang',
        'size' => 'Parang',
        'quantity' => 2,
        'harga' => 100000,
    ]);

    $payment = Payment::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'transaction_id' => 'TX-WEBHOOK-IDEMPOTEN',
        'total_harga' => 100000,
        'status' => $status,
        'snap_token' => 'snap',
    ]);

    return [$payment, $variant, $order];
}

function motifWebhookPayload(string $transactionStatus): array
{
    return webhookPayload('TX-WEBHOOK-IDEMPOTEN', '100000.00', $transactionStatus, '200');
}

test('notifikasi gagal berulang hanya mengembalikan stok sekali', function () {
    [$payment, $variant] = webhookPaymentWithVariant('pending');
    $variant->update(['stock' => 1]); // stok setelah checkout: 5 - 2 = 3 → disetel 1 untuk uji

    foreach (range(1, 3) as $ignored) {
        $this->postJson('/midtrans/webhook', motifWebhookPayload('expire'))->assertOk();
    }

    // Satu pengembalian saja: 1 + 2 = 3, bukan 1 + 2*3 = 7.
    expect($variant->refresh()->stock)->toBe(3)
        ->and($payment->refresh()->status)->toBe('failed');
});

test('notifikasi sukses berulang tidak menggandakan paid_at/log', function () {
    [$payment] = webhookPaymentWithVariant('pending');

    $this->postJson('/midtrans/webhook', motifWebhookPayload('settlement'))->assertOk();
    $paidAt = $payment->refresh()->paid_at;

    $this->postJson('/midtrans/webhook', motifWebhookPayload('settlement'))->assertOk();

    expect($payment->refresh()->paid_at->toDateTimeString())->toBe($paidAt->toDateTimeString())
        ->and($payment->status)->toBe('success');
});

// ---------- Temuan 5: soft-delete memeriksa payment_details ----------

test('motif yang hanya dipakai payment_details ikut dinonaktifkan, tidak dihapus fisik', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::create(['name' => 'Batik Soft Delete', 'harga' => 100000, 'has_variants' => true]);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Kawung',
        'price' => 100000,
        'stock' => 5,
        'is_active' => true,
    ]);

    $user = User::factory()->create(['role' => 'user']);
    $order = Order::create(['user_id' => $user->id, 'total_harga' => 100000, 'status' => 'paid']);
    $payment = Payment::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'transaction_id' => 'TX-SOFTDELETE',
        'total_harga' => 100000,
        'status' => 'success',
        'paid_at' => now(),
    ]);
    // Order detail sengaja TIDAK ada: hanya payment_details yang menunjuk variasi.
    PaymentDetail::create([
        'payment_id' => $payment->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'motif_name' => 'Kawung',
        'name' => 'Budi',
        'email' => 'budi@contoh.test',
        'phone' => '08123',
        'alamat' => 'Jl. Test',
        'size' => 'Kawung',
        'quantity' => 1,
        'harga' => 100000,
    ]);

    $this->actingAs($admin)
        ->delete("/admin/products/{$product->id}/variants/{$variant->id}")
        ->assertRedirect();

    expect(ProductVariant::where('id', $variant->id)->exists())->toBeTrue()
        ->and($variant->refresh()->is_active)->toBeFalse();
});
