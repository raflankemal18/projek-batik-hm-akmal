<?php

use App\Models\Banner;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function bannerAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function bannerUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

function createBanner(string $title = 'Promo Kemerdekaan', bool $isActive = true): Banner
{
    return Banner::create([
        'title' => $title,
        'image_url' => '/storage/banners/sample.jpg',
        'is_active' => $isActive,
    ]);
}

test('admin dapat melihat halaman manajemen banner', function () {
    $admin = bannerAdmin();
    createBanner('Banner 1');
    createBanner('Banner 2');

    $response = $this->actingAs($admin)->get('/admin/banners');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/Banner')
        ->has('banners', 2)
    );
});

test('non-admin tidak dapat mengakses halaman manajemen banner', function () {
    $user = bannerUser();

    $this->actingAs($user)->get('/admin/banners')->assertStatus(403);
});

test('tamu tidak dapat mengakses halaman manajemen banner', function () {
    $this->get('/admin/banners')->assertRedirect('/login');
});

test('admin dapat membuat banner baru dengan upload gambar', function () {
    Storage::fake('public');
    $admin = bannerAdmin();

    $image = UploadedFile::fake()->create('promo.jpg', 500, 'image/jpeg');

    $response = $this->actingAs($admin)
        ->from('/admin/banners')
        ->post('/admin/banners', [
            'title' => 'Promo Batik Merdeka',
            'image' => $image,
            'is_active' => true,
        ]);

    $response->assertRedirect('/admin/banners');
    $response->assertSessionHas('success', 'Banner created successfully');

    $banner = Banner::where('title', 'Promo Batik Merdeka')->first();
    expect($banner)->not->toBeNull()
        ->and($banner->is_active)->toBeTrue();

    $storedPath = str_replace('/storage/', '', $banner->image_url);
    Storage::disk('public')->assertExists($storedPath);
});

test('admin dapat mengupdate banner beserta mengganti gambar', function () {
    Storage::fake('public');
    $admin = bannerAdmin();

    $oldImage = UploadedFile::fake()->create('old.jpg', 500, 'image/jpeg');
    $oldPath = $oldImage->store('banners', 'public');
    $banner = Banner::create([
        'title' => 'Judul Lama',
        'image_url' => '/storage/' . $oldPath,
        'is_active' => true,
    ]);

    $newImage = UploadedFile::fake()->create('new.jpg', 500, 'image/jpeg');

    $response = $this->actingAs($admin)
        ->from('/admin/banners')
        ->put("/admin/banners/{$banner->id}", [
            'title' => 'Judul Baru',
            'image' => $newImage,
            'is_active' => false,
        ]);

    $response->assertRedirect('/admin/banners');
    $response->assertSessionHas('success', 'Banner updated successfully');

    $banner->refresh();
    expect($banner->title)->toBe('Judul Baru')
        ->and($banner->is_active)->toBeFalse();

    Storage::disk('public')->assertMissing($oldPath);
    $newStoredPath = str_replace('/storage/', '', $banner->image_url);
    Storage::disk('public')->assertExists($newStoredPath);
});

test('admin dapat toggle status aktif nonaktif banner', function () {
    $admin = bannerAdmin();
    $banner = createBanner('Banner Aktif', true);

    $response = $this->actingAs($admin)
        ->from('/admin/banners')
        ->patch("/admin/banners/{$banner->id}/toggle");

    $response->assertRedirect('/admin/banners');
    $response->assertSessionHas('success', 'Banner status updated');

    expect($banner->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->patch("/admin/banners/{$banner->id}/toggle");
    expect($banner->fresh()->is_active)->toBeTrue();
});

test('admin dapat menghapus banner beserta file gambarnya', function () {
    Storage::fake('public');
    $admin = bannerAdmin();

    $image = UploadedFile::fake()->create('delete_me.jpg', 500, 'image/jpeg');
    $path = $image->store('banners', 'public');
    $banner = Banner::create([
        'title' => 'Hapus Saya',
        'image_url' => '/storage/' . $path,
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)
        ->from('/admin/banners')
        ->delete("/admin/banners/{$banner->id}");

    $response->assertRedirect('/admin/banners');
    $response->assertSessionHas('success', 'Banner deleted successfully');

    expect(Banner::find($banner->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('banner nonaktif tidak tampil di homepage', function () {
    $activeBanner = createBanner('Banner Tampil', true);
    $inactiveBanner = createBanner('Banner Tersembunyi', false);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Welcome')
        ->has('banners', 1)
        ->where('banners.0.id', $activeBanner->id)
    );
});

test('validasi gagal saat membuat banner tanpa data yang wajib', function () {
    $admin = bannerAdmin();

    $response = $this->actingAs($admin)->post('/admin/banners', []);

    $response->assertSessionHasErrors(['title', 'image']);
});
