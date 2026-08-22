<?php

use App\Models\User;

function userBulkAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function userBulkUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

test('tamu tidak dapat melakukan bulk delete user', function () {
    $target = userBulkUser();

    $this->delete('/admin/users/bulk', ['ids' => [$target->id]])
        ->assertRedirect('/login');

    expect(User::find($target->id))->not->toBeNull();
});

test('user biasa tidak dapat melakukan bulk delete user', function () {
    $user = userBulkUser();
    $target = userBulkUser();

    $this->actingAs($user)
        ->delete('/admin/users/bulk', ['ids' => [$target->id]])
        ->assertStatus(403);

    expect(User::find($target->id))->not->toBeNull();
});

test('admin dapat bulk delete beberapa user sekaligus', function () {
    $admin = userBulkAdmin();
    $u1 = userBulkUser();
    $u2 = userBulkUser();
    $u3 = userBulkUser();

    $response = $this->actingAs($admin)
        ->from('/admin/users')
        ->delete('/admin/users/bulk', ['ids' => [$u1->id, $u2->id]]);

    $response->assertRedirect('/admin/users');
    $response->assertSessionHas('success', '2 user berhasil dihapus');

    expect(User::find($u1->id))->toBeNull()
        ->and(User::find($u2->id))->toBeNull()
        ->and(User::find($u3->id))->not->toBeNull()
        ->and(User::find($admin->id))->not->toBeNull();
});

test('admin tidak dapat menghapus dirinya sendiri lewat bulk delete', function () {
    $admin = userBulkAdmin();
    $u1 = userBulkUser();
    $u2 = userBulkUser();

    $response = $this->actingAs($admin)
        ->from('/admin/users')
        ->delete('/admin/users/bulk', ['ids' => [$admin->id, $u1->id]]);

    $response->assertRedirect('/admin/users');
    // controller filters auth id sebelum delete — hanya 1 yang kehapus
    $response->assertSessionHas('success', '1 user berhasil dihapus');

    expect(User::find($admin->id))->not->toBeNull()
        ->and(User::find($u1->id))->toBeNull()
        ->and(User::find($u2->id))->not->toBeNull();
});

test('bulk delete hanya menghapus id yang diminta', function () {
    $admin = userBulkAdmin();
    $keep = userBulkUser();
    $delete = userBulkUser();

    $this->actingAs($admin)
        ->delete('/admin/users/bulk', ['ids' => [$delete->id]]);

    expect(User::find($keep->id))->not->toBeNull()
        ->and(User::find($delete->id))->toBeNull();
});

test('validasi bulk delete gagal jika ids tidak dikirim', function () {
    $admin = userBulkAdmin();

    $response = $this->actingAs($admin)->delete('/admin/users/bulk', []);

    $response->assertSessionHasErrors('ids');
});

test('validasi bulk delete gagal jika ids mengandung id yang tidak ada', function () {
    $admin = userBulkAdmin();
    $u1 = userBulkUser();

    $response = $this->actingAs($admin)->delete('/admin/users/bulk', [
        'ids' => [$u1->id, 999999],
    ]);

    $response->assertSessionHasErrors('ids.1');
    // transaksi validasi gagal sebelum delete — user tetap ada
    expect(User::find($u1->id))->not->toBeNull();
});

test('validasi bulk delete gagal jika ids bukan array', function () {
    $admin = userBulkAdmin();

    $response = $this->actingAs($admin)->delete('/admin/users/bulk', [
        'ids' => 'bukan-array',
    ]);

    $response->assertSessionHasErrors('ids');
});
