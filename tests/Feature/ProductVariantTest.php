<?php

use App\Models\ActivityLog;
use App\Models\Cart;
use App\Models\CartDetail;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function motifAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function motifUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

/** Produk bermotif: sudah punya has_variants = true dan minimal satu motif. */
function motifProduct(array $attributes = []): Product
{
    $product = Product::create(array_merge([
        'name' => 'Batik Cap 2 Warna',
        'category' => 'wanita',
        'harga' => 200000,
        'description' => 'Produk uji motif',
        'has_variants' => true,
    ], $attributes));

    return $product;
}

function motif(Product $product, string $name, int $price = 150000, int $stock = 5): ProductVariant
{
    return ProductVariant::create([
        'product_id' => $product->id,
        'name' => $name,
        'price' => $price,
        'stock' => $stock,
        'is_active' => true,
    ]);
}

function motifCart(User $user, Product $product, ProductVariant $variant, int $quantity = 1): Cart
{
    $cart = Cart::create(['user_id' => $user->id]);

    CartDetail::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'size' => $variant->name,
        'quantity' => $quantity,
    ]);

    return $cart;
}

test('admin dapat membuat motif lengkap dengan foto', function () {
    Storage::fake('public');
    $admin = motifAdmin();
    $product = Product::create(['name' => 'Batik Baru', 'harga' => 100000]);

    $this->actingAs($admin)
        ->post("/admin/products/{$product->id}/variants", [
            'name' => 'Parang',
            'price' => 135000,
            'stock' => 7,
            // fake()->create (bukan ->image()) mengikuti pola BannerTest: GD di
            // container tidak dikompilasi dengan dukungan JPEG.
            'image' => UploadedFile::fake()->create('motif.jpg', 120, 'image/jpeg'),
        ])
        ->assertRedirect();

    $variant = ProductVariant::where('product_id', $product->id)->firstOrFail();

    expect($variant->name)->toBe('Parang')
        ->and($variant->price)->toBe('135000.00')
        ->and($variant->stock)->toBe(7)
        ->and($variant->image_path)->not->toBeNull();

    // Produk otomatis ditandai memakai model motif supaya frontend menampilkan
    // pilihan motif (bukan blok ukuran).
    expect($product->refresh()->has_variants)->toBeTrue();

    expect(ActivityLog::where('action', 'product.variant_created')->exists())->toBeTrue();
});

test('motif boleh dibuat tanpa foto (foto menyusul dari toko)', function () {
    $admin = motifAdmin();
    $product = motifProduct();

    $this->actingAs($admin)->post("/admin/products/{$product->id}/variants", [
        'name' => 'Kawung',
        'price' => 90000,
        'stock' => 3,
    ])->assertRedirect();

    $variant = ProductVariant::where('product_id', $product->id)->firstOrFail();

    expect($variant->image_path)->toBeNull()->and($variant->is_active)->toBeTrue();
});

test('motif divalidasi: nama wajib, harga positif, stok non-negatif, nama unik per produk', function () {
    $admin = motifAdmin();
    $product = motifProduct();
    motif($product, 'Parang');

    $this->actingAs($admin)->post("/admin/products/{$product->id}/variants", [])
        ->assertSessionHasErrors(['name', 'price', 'stock']);

    $this->actingAs($admin)->post("/admin/products/{$product->id}/variants", [
        'name' => 'Parang',
        'price' => 90000,
        'stock' => 1,
    ])->assertSessionHasErrors('name');

    $this->actingAs($admin)->post("/admin/products/{$product->id}/variants", [
        'name' => 'Kawung',
        'price' => 0,
        'stock' => -1,
    ])->assertSessionHasErrors(['price', 'stock']);
});

test('user biasa tidak bisa mengelola motif', function () {
    $product = motifProduct();

    $this->actingAs(motifUser())->post("/admin/products/{$product->id}/variants", [
        'name' => 'Kawung', 'price' => 90000, 'stock' => 1,
    ])->assertForbidden();
});

test('tamu tidak bisa mengelola motif', function () {
    $product = motifProduct();

    $this->post("/admin/products/{$product->id}/variants", [
        'name' => 'Kawung', 'price' => 90000, 'stock' => 1,
    ])->assertRedirect('/login');
});

test('admin dapat mengubah motif', function () {
    $admin = motifAdmin();
    $product = motifProduct();
    $variant = motif($product, 'Parang', 100000, 5);

    $this->actingAs($admin)
        ->put("/admin/products/{$product->id}/variants/{$variant->id}", [
            'name' => 'Parang Sinden',
            'price' => 175000,
            'stock' => 9,
        ])
        ->assertRedirect();

    $variant->refresh();

    expect($variant->name)->toBe('Parang Sinden')
        ->and($variant->price)->toBe('175000.00')
        ->and($variant->stock)->toBe(9);

    expect(ActivityLog::where('action', 'product.variant_updated')->exists())->toBeTrue();
});

test('motif milik produk lain tidak bisa diubah lewat produk ini', function () {
    $admin = motifAdmin();
    $productA = motifProduct(['name' => 'Produk A']);
    $productB = motifProduct(['name' => 'Produk B']);
    $motifB = motif($productB, 'Milik B');

    $this->actingAs($admin)
        ->put("/admin/products/{$productA->id}/variants/{$motifB->id}", [
            'name' => 'Dibajak', 'price' => 1, 'stock' => 1,
        ])
        ->assertNotFound();
});

test('motif yang belum dipakai bisa dihapus, motif yang sudah dipakai hanya dinonaktifkan', function () {
    $admin = motifAdmin();
    $product = motifProduct();
    $motifBaru = motif($product, 'Kawung');
    $motifTerpakai = motif($product, 'Parang');

    $order = Order::create([
        'user_id' => motifUser()->id,
        'total_harga' => 150000,
        'status' => 'paid',
    ]);
    OrderDetail::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $motifTerpakai->id,
        'motif_name' => 'Parang',
        'size' => 'Parang',
        'quantity' => 1,
        'harga' => 150000,
    ]);

    $this->actingAs($admin)
        ->delete("/admin/products/{$product->id}/variants/{$motifBaru->id}")
        ->assertRedirect();

    expect(ProductVariant::where('id', $motifBaru->id)->exists())->toBeFalse();

    $this->actingAs($admin)
        ->delete("/admin/products/{$product->id}/variants/{$motifTerpakai->id}")
        ->assertRedirect();

    // Motif yang sudah pernah dibeli tetap ada (histori), tapi tidak bisa dipilih lagi.
    expect(ProductVariant::where('id', $motifTerpakai->id)->exists())->toBeTrue()
        ->and($motifTerpakai->refresh()->is_active)->toBeFalse();

    $detail = OrderDetail::where('order_id', $order->id)->first();
    expect($detail->motif_name)->toBe('Parang');
});

test('dua motif berbeda dari produk yang sama menjadi dua baris keranjang', function () {
    $user = motifUser();
    $product = motifProduct();
    $parang = motif($product, 'Parang', 150000, 5);
    $kawung = motif($product, 'Kawung', 175000, 3);

    $this->actingAs($user)->post('/cart/add', [
        'product_id' => $product->id,
        'product_variant_id' => $parang->id,
        'quantity' => 2,
    ])->assertRedirect();

    $this->actingAs($user)->post('/cart/add', [
        'product_id' => $product->id,
        'product_variant_id' => $kawung->id,
        'quantity' => 1,
    ])->assertRedirect();

    expect(CartDetail::whereHas('cart', fn ($q) => $q->where('user_id', $user->id))->count())->toBe(2);
});

test('motif yang sama ditambahkan lagi menambah kuantitas, bukan baris baru', function () {
    $user = motifUser();
    $product = motifProduct();
    $parang = motif($product, 'Parang', 150000, 5);

    foreach ([1, 2] as $qty) {
        $this->actingAs($user)->post('/cart/add', [
            'product_id' => $product->id,
            'product_variant_id' => $parang->id,
            'quantity' => $qty,
        ])->assertRedirect();
    }

    $details = CartDetail::whereHas('cart', fn ($q) => $q->where('user_id', $user->id))->get();

    expect($details)->toHaveCount(1)->and($details->first()->quantity)->toBe(3);
});

test('motif milik produk lain tidak bisa dimasukkan ke keranjang', function () {
    $user = motifUser();
    $productA = motifProduct(['name' => 'Produk A']);
    $productB = motifProduct(['name' => 'Produk B']);
    $motifB = motif($productB, 'Milik B');

    $this->actingAs($user)->post('/cart/add', [
        'product_id' => $productA->id,
        'product_variant_id' => $motifB->id,
        'quantity' => 1,
    ])->assertSessionHasErrors('product_variant_id');

    expect(CartDetail::count())->toBe(0);
});

test('motif nonaktif tidak bisa dipilih', function () {
    $user = motifUser();
    $product = motifProduct();
    $motif = motif($product, 'Parang', 150000, 5);
    $motif->update(['is_active' => false]);

    $this->actingAs($user)->post('/cart/add', [
        'product_id' => $product->id,
        'product_variant_id' => $motif->id,
        'quantity' => 1,
    ])->assertSessionHasErrors('product_variant_id');
});

test('stok motif diverifikasi server-side saat tambah ke keranjang', function () {
    $user = motifUser();
    $product = motifProduct();
    $motif = motif($product, 'Parang', 150000, 1);

    $this->actingAs($user)->post('/cart/add', [
        'product_id' => $product->id,
        'product_variant_id' => $motif->id,
        'quantity' => 3,
    ]);

    expect(CartDetail::count())->toBe(0);
});

test('checkout memakai harga motif dan mengunci stok variasi', function () {
    $user = motifUser();
    $product = motifProduct(['harga' => 200000]);
    $motif = motif($product, 'Parang', 150000, 5);
    motifCart($user, $product, $motif, 2);

    $this->actingAs($user)->post('/checkout', [
        'name' => 'Budi',
        'email' => 'budi@contoh.test',
        'phone' => '08123',
        'alamat' => 'Jl. Test',
    ])->assertRedirect(route('pembayaran'));

    $detail = OrderDetail::whereHas('order', fn ($q) => $q->where('user_id', $user->id))->firstOrFail();

    expect($detail->product_variant_id)->toBe($motif->id)
        ->and($detail->motif_name)->toBe('Parang')
        ->and($detail->harga)->toBe('150000.00')
        ->and($detail->quantity)->toBe(2)
        ->and($motif->refresh()->stock)->toBe(3);

    $order = Order::where('id', $detail->order_id)->firstOrFail();
    expect($order->total_harga)->toBe('300000.00');
});

test('motif dengan stok kurang ditolak saat checkout dengan pesan menyebut motif', function () {
    $user = motifUser();
    $product = motifProduct();
    $motif = motif($product, 'Kawung', 150000, 1);
    motifCart($user, $product, $motif, 5);

    $this->actingAs($user)->from('/co')->post('/checkout', [
        'name' => 'Budi',
        'email' => 'budi@contoh.test',
        'phone' => '08123',
        'alamat' => 'Jl. Test',
    ])->assertRedirect()->assertSessionHasErrors('stock');

    expect($motif->refresh()->stock)->toBe(1);
    expect(OrderDetail::count())->toBe(0);
});

test('pembayaran menyimpan snapshot motif ke payment_details', function () {
    $user = motifUser();
    $product = motifProduct();
    $motif = motif($product, 'Parang', 150000, 5);
    motifCart($user, $product, $motif, 1);

    $this->actingAs($user)->post('/checkout', [
        'name' => 'Budi',
        'email' => 'budi@contoh.test',
        'phone' => '08123',
        'alamat' => 'Jl. Test',
    ]);

    $order = Order::where('user_id', $user->id)->where('status', 'draft')->firstOrFail();

    $this->mock(App\Services\MidtransService::class)
        ->shouldReceive('getSnapToken')
        ->andReturn('snap-token-test');

    $this->actingAs($user)->post('/payment')->assertRedirect(route('pembayaran'));

    $payment = Payment::where('order_id', $order->id)->firstOrFail();
    $paymentDetail = PaymentDetail::where('payment_id', $payment->id)->firstOrFail();

    expect($paymentDetail->product_variant_id)->toBe($motif->id)
        ->and($paymentDetail->motif_name)->toBe('Parang');
});

test('motif yang diganti namanya tidak mengubah motif pada order & invoice lama', function () {
    $user = motifUser();
    $product = motifProduct();
    $motif = motif($product, 'Parang', 150000, 5);
    motifCart($user, $product, $motif, 1);

    $this->actingAs($user)->post('/checkout', [
        'name' => 'Budi',
        'email' => 'budi@contoh.test',
        'phone' => '08123',
        'alamat' => 'Jl. Test',
    ]);

    $detail = OrderDetail::firstOrFail();

    $motif->update(['name' => 'Parang Sinden', 'price' => 999000]);

    expect($detail->refresh()->motif_name)->toBe('Parang')
        ->and($detail->harga)->toBe('150000.00');
});

test('produk lama tanpa motif tetap bisa dibeli lewat alur ukuran', function () {
    $user = motifUser();
    $product = Product::create([
        'name' => 'Batik Legacy',
        'harga' => 120000,
        'has_variants' => false,
    ]);
    $size = App\Models\ProductSize::create([
        'product_id' => $product->id,
        'size' => 'L',
        'stock' => 5,
    ]);

    $this->actingAs($user)->post('/cart/add', [
        'product_id' => $product->id,
        'size' => 'L',
        'quantity' => 2,
    ])->assertRedirect();

    $this->actingAs($user)->post('/checkout', [
        'name' => 'Budi',
        'email' => 'budi@contoh.test',
        'phone' => '08123',
        'alamat' => 'Jl. Test',
    ])->assertRedirect(route('pembayaran'));

    $detail = OrderDetail::firstOrFail();

    expect($detail->product_variant_id)->toBeNull()
        ->and($detail->product_size_id)->toBe($size->id)
        ->and($detail->harga)->toBe('120000.00')
        ->and($size->refresh()->stock)->toBe(3);
});
