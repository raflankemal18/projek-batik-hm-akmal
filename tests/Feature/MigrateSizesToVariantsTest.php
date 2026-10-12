<?php

use App\Models\Cart;
use App\Models\CartDetail;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Models\User;

/**
 * products:migrate-sizes-to-variants — transformasi data produk lama ke model motif
 * (keputusan 2026-08-13: produk lama dimigrasikan ke motif).
 */
function legacyProductWithSizes(): array
{
    $product = Product::create([
        'name' => 'Batik Cap 2 Warna Biru',
        'category' => 'pria',
        'harga' => 85000,
        'has_variants' => false,
    ]);

    $s = ProductSize::create(['product_id' => $product->id, 'size' => 'S', 'stock' => 4]);
    $m = ProductSize::create(['product_id' => $product->id, 'size' => 'M', 'stock' => 6]);

    return [$product, $s, $m];
}

test('dry-run tidak menulis apa pun ke database', function () {
    [$product] = legacyProductWithSizes();

    $this->artisan('products:migrate-sizes-to-variants', ['--dry-run' => true])->assertSuccessful();

    expect(ProductVariant::count())->toBe(0)
        ->and($product->refresh()->has_variants)->toBeFalse();
});

test('command menolak jalan tanpa konfirmasi eksplisit', function () {
    [$product] = legacyProductWithSizes();

    $this->artisan('products:migrate-sizes-to-variants')
        ->expectsConfirmation('Ukuran lama akan menjadi NAMA MOTIF pada produk yang ditandai. Lanjutkan?', 'no')
        ->assertSuccessful();

    expect(ProductVariant::count())->toBe(0)
        ->and($product->refresh()->has_variants)->toBeFalse();
});

test('bisa dibatasi ke produk tertentu supaya produk bermotif tidak ikut berubah', function () {
    [$produkDipilih] = legacyProductWithSizes();
    [$produkLain] = legacyProductWithSizes();

    $this->artisan('products:migrate-sizes-to-variants', [
        '--force' => true,
        '--product' => [$produkDipilih->id],
    ])->assertSuccessful();

    expect(ProductVariant::where('product_id', $produkDipilih->id)->count())->toBe(2)
        ->and(ProductVariant::where('product_id', $produkLain->id)->count())->toBe(0)
        ->and($produkDipilih->refresh()->has_variants)->toBeTrue()
        ->and($produkLain->refresh()->has_variants)->toBeFalse();
});

test('ukuran lama menjadi motif dengan harga produk & stoknya sendiri', function () {
    [$product, $s, $m] = legacyProductWithSizes();

    $this->artisan('products:migrate-sizes-to-variants', ['--force' => true])->assertSuccessful();

    $variants = ProductVariant::where('product_id', $product->id)->orderBy('id')->get();

    expect($variants)->toHaveCount(2)
        ->and($variants->pluck('name')->all())->toBe(['S', 'M'])
        ->and($variants->pluck('price')->map(fn ($p) => (float) $p)->all())->toBe([85000.0, 85000.0])
        ->and($variants->pluck('stock')->all())->toBe([4, 6])
        ->and($variants->every(fn ($v) => $v->is_active))->toBeTrue();

    expect($product->refresh()->has_variants)->toBeTrue();

    // Tabel products_sizes tetap ada: order_details.product_size_id masih memakainya.
    expect(ProductSize::count())->toBe(2)
        ->and(ProductSize::find($s->id))->not->toBeNull()
        ->and(ProductSize::find($m->id))->not->toBeNull();
});

test('berjalan dua kali tidak membuat motif duplikat', function () {
    [$product] = legacyProductWithSizes();

    $this->artisan('products:migrate-sizes-to-variants', ['--force' => true])->assertSuccessful();
    $this->artisan('products:migrate-sizes-to-variants', ['--force' => true])->assertSuccessful();

    expect(ProductVariant::where('product_id', $product->id)->count())->toBe(2);
});

test('baris keranjang lama dipetakan ke motif dengan nama yang sama', function () {
    $user = User::factory()->create(['role' => 'user']);
    [$product] = legacyProductWithSizes();

    $cart = Cart::create(['user_id' => $user->id]);
    $detail = CartDetail::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'size' => 'M',
        'quantity' => 2,
    ]);

    $this->artisan('products:migrate-sizes-to-variants', ['--force' => true])->assertSuccessful();

    $variantM = ProductVariant::where('product_id', $product->id)->where('name', 'M')->firstOrFail();

    expect($detail->refresh()->product_variant_id)->toBe($variantM->id);
});

test('baris keranjang dengan size yang tak ada di katalog tidak dihapus', function () {
    $user = User::factory()->create(['role' => 'user']);
    [$product] = legacyProductWithSizes();

    $cart = Cart::create(['user_id' => $user->id]);
    $detail = CartDetail::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'size' => 'XL',
        'quantity' => 1,
    ]);

    $this->artisan('products:migrate-sizes-to-variants', ['--force' => true])->assertSuccessful();

    // Barisnya tetap ada (isi keranjang user tidak hilang diam-diam) tapi belum
    // terhubung ke motif mana pun.
    expect(CartDetail::find($detail->id))->not->toBeNull()
        ->and($detail->refresh()->product_variant_id)->toBeNull();
});

test('order lama tetap utuh dan tidak ikut bermigrasi ke motif', function () {
    [$product, $s] = legacyProductWithSizes();

    $order = Order::create([
        'user_id' => User::factory()->create(['role' => 'user'])->id,
        'total_harga' => 85000,
        'status' => 'paid',
    ]);
    $detail = OrderDetail::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_size_id' => $s->id,
        'size' => 'S',
        'quantity' => 1,
        'harga' => 85000,
    ]);

    $this->artisan('products:migrate-sizes-to-variants', ['--force' => true])->assertSuccessful();

    $detail->refresh();

    expect($detail->product_size_id)->toBe($s->id)
        ->and($detail->product_variant_id)->toBeNull()
        ->and($detail->motif_name)->toBeNull()
        ->and($detail->harga)->toBe('85000.00');
});
