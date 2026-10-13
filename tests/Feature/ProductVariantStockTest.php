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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * P8 — penutup daftar skenario Tahap 5 di dokumen planning.
 *
 * Fokus: perilaku yang belum dijamin test sebelumnya — motif lain tetap bisa
 * dibeli saat satu motif habis, berkas foto motif ikut terhapus, dan histori
 * transaksi tetap terbaca setelah produk/motif dihapus.
 */

function p8Product(): Product
{
    $product = Product::create([
        'name' => 'Batik Cap 2 Warna',
        'harga' => 85000,
        'has_variants' => true,
        'fabric_size' => '2 m x 1,1 m',
    ]);

    return $product;
}

function p8Variant(Product $product, string $name, int $price, int $stock): ProductVariant
{
    return ProductVariant::create([
        'product_id' => $product->id,
        'name' => $name,
        'price' => $price,
        'stock' => $stock,
        'is_active' => true,
    ]);
}

function p8CartWithVariant(User $user, Product $product, ProductVariant $variant, int $qty = 1): void
{
    // Satu keranjang per user: kalau dibuat dua, CheckoutService hanya
    // mengambil carts pertama dan item di keranjang kedua diam-diam tak terbawa.
    $cart = Cart::firstOrCreate(['user_id' => $user->id]);
    CartDetail::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'size' => $variant->name,
        'quantity' => $qty,
    ]);
}

test('motif yang habis tidak memblokir motif lain dari produk yang sama', function () {
    $user = User::factory()->create(['role' => 'user']);
    $product = p8Product();
    $habis = p8Variant($product, 'Kawung', 95000, 0);
    $tersedia = p8Variant($product, 'Parang', 85000, 3);

    // Motif habis ditolak...
    p8CartWithVariant($user, $product, $habis, 1);
    $this->actingAs($user)->from('/co')->post('/checkout', [
        'name' => 'Budi', 'email' => 'budi@contoh.test', 'phone' => '08123', 'alamat' => 'Jl. Test',
    ])->assertSessionHasErrors('stock');

    // Checkout yang gagal TIDAK menghapus keranjang (transaksinya rollback),
    // jadi baris motif habis dibuang dulu sebelum membeli motif lain.
    CartDetail::whereHas('cart', fn ($q) => $q->where('user_id', $user->id))->delete();

    // ...tapi motif lain tetap bisa dibeli.
    p8CartWithVariant($user, $product, $tersedia, 2);
    $this->actingAs($user)->from('/co')->post('/checkout', [
        'name' => 'Budi', 'email' => 'budi@contoh.test', 'phone' => '08123', 'alamat' => 'Jl. Test',
    ])->assertRedirect(route('pembayaran'));

    $detail = OrderDetail::whereHas('order', fn ($q) => $q->where('user_id', $user->id))->firstOrFail();

    expect($detail->motif_name)->toBe('Parang')
        ->and($tersedia->refresh()->stock)->toBe(1)
        ->and($habis->refresh()->stock)->toBe(0);
});

test('updateQuantity tidak boleh melebihi stok motif', function () {
    $user = User::factory()->create(['role' => 'user']);
    $product = p8Product();
    $variant = p8Variant($product, 'Parang', 85000, 3);
    p8CartWithVariant($user, $product, $variant, 1);

    $detail = CartDetail::firstOrFail();

    $this->actingAs($user)->post('/cart/update', [
        'cart_detail_id' => $detail->id,
        'quantity' => 4,
    ])->assertSessionHasErrors('stock');

    expect($detail->refresh()->quantity)->toBe(1);
});

test('hapus motif ikut menghapus berkas fotonya', function () {
    // ImageService memakai disk default (FILESYSTEM_DISK), bukan disk 'public'.
    Storage::fake(config('filesystems.default'));
    $admin = User::factory()->create(['role' => 'admin']);
    $product = p8Product();
    $motif = p8Variant($product, 'Parang', 85000, 3);

    $this->actingAs($admin)->post("/admin/products/{$product->id}/variants", [
        'name' => 'Kawung',
        'price' => 95000,
        'stock' => 2,
        'image' => UploadedFile::fake()->create('kawung.jpg', 120, 'image/jpeg'),
    ]);

    $denganFoto = ProductVariant::where('product_id', $product->id)->where('name', 'Kawung')->firstOrFail();
    $path = $denganFoto->image_path;
    expect($path)->not->toBeNull();
    Storage::disk(config('filesystems.default'))->assertExists($path);

    $this->actingAs($admin)->delete("/admin/products/{$product->id}/variants/{$denganFoto->id}")
        ->assertRedirect();

    expect(ProductVariant::find($denganFoto->id))->toBeNull();
    Storage::disk(config('filesystems.default'))->assertMissing($path);

    // Motif tanpa foto (belum ada dari toko) tetap bisa dihapus tanpa error.
    $this->actingAs($admin)->delete("/admin/products/{$product->id}/variants/{$motif->id}")
        ->assertRedirect();

    expect(ProductVariant::find($motif->id))->toBeNull();
});

test('riwayat order & invoice tetap utuh setelah produk diarsipkan', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'user']);
    $product = p8Product();
    $variant = p8Variant($product, 'Parang', 85000, 3);
    p8CartWithVariant($user, $product, $variant, 1);

    $this->actingAs($user)->post('/checkout', [
        'name' => 'Budi', 'email' => 'budi@contoh.test', 'phone' => '08123', 'alamat' => 'Jl. Test',
    ])->assertRedirect(route('pembayaran'));

    $order = Order::where('user_id', $user->id)->where('status', 'draft')->firstOrFail();

    $payment = Payment::create([
        'user_id' => $user->id,
        'order_id' => $order->id,
        'transaction_id' => 'TX-PRODUK-ARCHIVED',
        'total_harga' => 85000,
        'status' => 'success',
        'paid_at' => now(),
    ]);
    PaymentDetail::create([
        'payment_id' => $payment->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'motif_name' => 'Parang',
        'name' => $user->name,
        'email' => $user->email,
        'phone' => '08123',
        'alamat' => 'Jl. Test',
        'size' => 'Parang',
        'quantity' => 1,
        'harga' => 85000,
    ]);
    $order->update(['status' => 'paid']);

    // Admin mengarsipkan produknya.
    $this->actingAs($admin)->delete("/admin/products/{$product->id}")->assertRedirect();

    expect(Product::find($product->id))->toBeNull()
        ->and(Product::withTrashed()->find($product->id))->not->toBeNull();

    // Rincian item order TIDAK ikut hilang, dan nama produknya masih terbaca.
    $detail = OrderDetail::where('order_id', $order->id)->firstOrFail();
    expect($detail->motif_name)->toBe('Parang')
        ->and($detail->harga)->toBe('85000.00')
        ->and($detail->product->name)->toBe('Batik Cap 2 Warna');

    // Invoice masih bisa dibuka dan memuat nama produk.
    $this->actingAs($user)->get("/payments/{$payment->id}/invoice")
        ->assertOk()
        ->assertInertia(function ($page) {
            $payload = $page->toArray()['props']['payment'];

            expect($payload['order']['details'][0]['product']['name'])->toBe('Batik Cap 2 Warna')
                ->and($payload['order']['details'][0]['motif_name'])->toBe('Parang');
        });
});

test('produk terarsip tidak bisa dibeli lagi dari keranjang', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'user']);
    $product = p8Product();
    $variant = p8Variant($product, 'Parang', 85000, 3);
    p8CartWithVariant($user, $product, $variant, 1);

    $this->actingAs($admin)->delete("/admin/products/{$product->id}")->assertRedirect();

    $this->actingAs($user)->from('/co')->post('/checkout', [
        'name' => 'Budi', 'email' => 'budi@contoh.test', 'phone' => '08123', 'alamat' => 'Jl. Test',
    ])->assertRedirect('/co')->assertSessionHasErrors('stock');

    expect(Order::count())->toBe(0)
        ->and($variant->refresh()->stock)->toBe(3);
});

test('motif yang sudah dibeli tetap punya snapshot nama & harga di order (tidak ikut berubah)', function () {
    $user = User::factory()->create(['role' => 'user']);
    $product = p8Product();
    $variant = p8Variant($product, 'Parang', 85000, 3);
    p8CartWithVariant($user, $product, $variant, 2);

    $this->actingAs($user)->post('/checkout', [
        'name' => 'Budi', 'email' => 'budi@contoh.test', 'phone' => '08123', 'alamat' => 'Jl. Test',
    ])->assertRedirect(route('pembayaran'));

    $detail = OrderDetail::firstOrFail();

    // Nama motif diganti dua kali setelah pembelian.
    $variant->update(['name' => 'Parang Sinden', 'price' => 999000]);
    $variant->update(['is_active' => false]);

    $detail->refresh();

    expect($detail->motif_name)->toBe('Parang')
        ->and($detail->harga)->toBe('85000.00')
        ->and($detail->product_variant_id)->toBe($variant->id);
});

test('harga motif dipakai walaupun harga produknya berbeda', function () {
    $user = User::factory()->create(['role' => 'user']);
    $product = Product::create([
        'name' => 'Batik Harga Produk Berbeda',
        'harga' => 500000, // harga produk sengaja dibuat tidak masuk akal
        'has_variants' => true,
    ]);
    $variant = p8Variant($product, 'Parang', 85000, 3);
    p8CartWithVariant($user, $product, $variant, 2);

    $this->actingAs($user)->post('/checkout', [
        'name' => 'Budi', 'email' => 'budi@contoh.test', 'phone' => '08123', 'alamat' => 'Jl. Test',
    ])->assertRedirect(route('pembayaran'));

    $order = Order::where('user_id', $user->id)->where('status', 'draft')->firstOrFail();

    expect($order->total_harga)->toBe('170000.00')
        ->and(OrderDetail::firstOrFail()->harga)->toBe('85000.00');
});

test('motif nonaktif disembunyikan dari katalog publik tapi tetap bisa dibaca admin', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = p8Product();
    p8Variant($product, 'Parang', 85000, 3);
    $off = p8Variant($product, 'Kawung', 95000, 0);
    $off->update(['is_active' => false]);

    $this->get("/products/{$product->id}")->assertOk()->assertInertia(function ($page) {
        $names = collect($page->toArray()['props']['products']['active_variants'])->pluck('name')->all();
        expect($names)->toBe(['Parang']);
    });

    $this->actingAs($admin)->get('/admin/products')->assertOk()->assertInertia(function ($page) use ($off) {
        $row = collect($page->toArray()['props']['products']['data'])->firstWhere('id', $off->product_id);
        expect(collect($row['variants'])->pluck('name'))->toContain('Kawung');
    });
});

test('produk bermotif tanpa motif sama sekali tidak bisa dibeli', function () {
    $user = User::factory()->create(['role' => 'user']);
    $product = p8Product();
    $cart = Cart::create(['user_id' => $user->id]);

    // Baris tanpa variasi dan tanpa size yang cocok di katalog.
    CartDetail::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'size' => 'XL',
        'quantity' => 1,
    ]);

    $this->actingAs($user)->from('/co')->post('/checkout', [
        'name' => 'Budi', 'email' => 'budi@contoh.test', 'phone' => '08123', 'alamat' => 'Jl. Test',
    ])->assertRedirect('/co')->assertSessionHasErrors('stock');

    expect(Order::count())->toBe(0);
});

test('produk lama yang sudah dimigrasi ke motif tidak bisa dibeli lewat ukuran yang tidak ada motifnya', function () {
    $user = User::factory()->create(['role' => 'user']);
    $product = p8Product();
    // Ukuran lama masih ada (dipakai order lama) tapi tidak ada motif "XL".
    ProductSize::create(['product_id' => $product->id, 'size' => 'XL', 'stock' => 50]);
    p8Variant($product, 'Parang', 85000, 3);

    $cart = Cart::create(['user_id' => $user->id]);
    CartDetail::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'size' => 'XL',
        'quantity' => 1,
    ]);

    $this->actingAs($user)->from('/co')->post('/checkout', [
        'name' => 'Budi', 'email' => 'budi@contoh.test', 'phone' => '08123', 'alamat' => 'Jl. Test',
    ])->assertRedirect('/co')->assertSessionHasErrors('stock');

    // Stok products_sizes yang berjumlah 50 sama sekali tidak tersentuh.
    expect(ProductSize::where('product_id', $product->id)->first()->stock)->toBe(50);
});
