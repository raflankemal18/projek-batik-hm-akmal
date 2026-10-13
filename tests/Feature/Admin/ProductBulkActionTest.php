<?php

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductSize;
use App\Models\User;

function productBulkAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function productBulkUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

function productBulkCreate(string $name = 'Batik Bulk'): Product
{
    $product = Product::create([
        'name' => $name,
        'harga' => 120000,
        'description' => 'deskripsi produk bulk',
    ]);

    ProductSize::create([
        'product_id' => $product->id,
        'size' => 'M',
        'stock' => 10,
    ]);

    ProductImage::create([
        'product_id' => $product->id,
        'image_path' => 'public/product_images/bulk_' . uniqid() . '.jpg',
    ]);

    return $product;
}

test('tamu tidak dapat melakukan bulk delete produk', function () {
    $product = productBulkCreate();

    $this->delete('/admin/products/bulk', ['ids' => [$product->id]])
        ->assertRedirect('/login');

    expect(Product::find($product->id))->not->toBeNull();
});

test('user biasa tidak dapat melakukan bulk delete produk', function () {
    $user = productBulkUser();
    $product = productBulkCreate();

    $this->actingAs($user)
        ->delete('/admin/products/bulk', ['ids' => [$product->id]])
        ->assertStatus(403);

    expect(Product::find($product->id))->not->toBeNull();
});

test('admin dapat bulk delete produk (diarsipkan, bukan dihapus fisik)', function () {
    $admin = productBulkAdmin();
    $p1 = productBulkCreate('Batik A');
    $p2 = productBulkCreate('Batik B');

    $sizeP1 = ProductSize::where('product_id', $p1->id)->first();
    $imageP1 = ProductImage::where('product_id', $p1->id)->first();

    $response = $this->actingAs($admin)
        ->from('/admin/products')
        ->delete('/admin/products/bulk', ['ids' => [$p1->id]]);

    $response->assertRedirect('/admin/products');
    $response->assertSessionHas('success', '1 produk berhasil dihapus');

    // Penghapusan produk sekarang soft delete: produk hilang dari katalog, tapi
    // barisnya tetap ada karena order_details.product_idcascade — kalau hilang,
    // rincian item pada order yang sudah dibayar ikut hilang.
    expect(Product::find($p1->id))->toBeNull()
        ->and(Product::withTrashed()->find($p1->id))->not->toBeNull()
        ->and(ProductSize::find($sizeP1->id))->not->toBeNull()
        ->and(ProductImage::find($imageP1->id))->not->toBeNull()
        ->and(Product::find($p2->id))->not->toBeNull()
        ->and(ProductSize::where('product_id', $p2->id)->exists())->toBeTrue()
        ->and(ProductImage::where('product_id', $p2->id)->exists())->toBeTrue();

    // Dan produknya bisa dipulihkan lagi dari arsip.
    $this->actingAs($admin)->post("/admin/products/{$p1->id}/restore")
        ->assertRedirect(route('admin.products'));

    expect(Product::find($p1->id))->not->toBeNull();
});

test('admin dapat bulk delete banyak produk sekaligus', function () {
    $admin = productBulkAdmin();
    $p1 = productBulkCreate('Batik 1');
    $p2 = productBulkCreate('Batik 2');
    $p3 = productBulkCreate('Batik 3');

    $response = $this->actingAs($admin)
        ->from('/admin/products')
        ->delete('/admin/products/bulk', ['ids' => [$p1->id, $p2->id]]);

    $response->assertRedirect('/admin/products');
    $response->assertSessionHas('success', '2 produk berhasil dihapus');

    expect(Product::find($p1->id))->toBeNull()
        ->and(Product::find($p2->id))->toBeNull()
        ->and(Product::find($p3->id))->not->toBeNull()
        // Baris anak tetap disimpan agar histori transaksi tidak ikut hilang.
        ->and(ProductSize::where('product_id', $p1->id)->exists())->toBeTrue()
        ->and(ProductImage::where('product_id', $p1->id)->exists())->toBeTrue()
        ->and(Product::onlyTrashed()->count())->toBe(2);
});

test('bulk delete mencatat audit log untuk setiap produk yang dihapus', function () {
    $admin = productBulkAdmin();
    $p1 = productBulkCreate('Batik Log 1');
    $p2 = productBulkCreate('Batik Log 2');

    $this->actingAs($admin)->delete('/admin/products/bulk', ['ids' => [$p1->id, $p2->id]]);

    $logs = ActivityLog::where('action', 'product.delete')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs->pluck('subject_id')->toArray())->toContain((string) $p1->id)
        ->and($logs->pluck('subject_id')->toArray())->toContain((string) $p2->id)
        ->and($logs->first()->user_id)->toBe($admin->id);
});

test('bulk delete tidak menghapus produk yang tidak diminta', function () {
    $admin = productBulkAdmin();
    $keep = productBulkCreate('Batik Keep');
    $delete = productBulkCreate('Batik Delete');

    $this->actingAs($admin)->delete('/admin/products/bulk', ['ids' => [$delete->id]]);

    expect(Product::find($keep->id))->not->toBeNull()
        ->and(Product::find($delete->id))->toBeNull();
});

test('validasi bulk delete produk gagal jika ids tidak dikirim', function () {
    $admin = productBulkAdmin();

    $response = $this->actingAs($admin)->delete('/admin/products/bulk', []);

    $response->assertSessionHasErrors('ids');
});

test('validasi bulk delete produk gagal jika ids mengandung id yang tidak ada', function () {
    $admin = productBulkAdmin();
    $p1 = productBulkCreate();

    $response = $this->actingAs($admin)->delete('/admin/products/bulk', [
        'ids' => [$p1->id, 999999],
    ]);

    $response->assertSessionHasErrors('ids.1');
    expect(Product::find($p1->id))->not->toBeNull();
});

test('validasi bulk delete produk gagal jika ids bukan array', function () {
    $admin = productBulkAdmin();

    $response = $this->actingAs($admin)->delete('/admin/products/bulk', [
        'ids' => 'not-array',
    ]);

    $response->assertSessionHasErrors('ids');
});
