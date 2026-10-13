<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(?User $user): bool
    {
        return $user?->role === 'admin';
    }

    public function view(?User $user, Product $product): bool
    {
        return true;
    }

    public function create(?User $user): bool
    {
        return $user?->role === 'admin';
    }

    public function update(?User $user, Product $product): bool
    {
        return $user?->role === 'admin';
    }

    public function delete(?User $user, Product $product): bool
    {
        return $user?->role === 'admin';
    }

    public function deleteAny(?User $user): bool
    {
        return $user?->role === 'admin';
    }

    /** Aksi level-kelas untuk produk yang sudah diarsipkan (tidak punya instance). */
    public function updateAny(?User $user): bool
    {
        return $user?->role === 'admin';
    }

    /** Kelola motif kain (CRUD product_variants). */
    public function manageVariants(?User $user, Product $product): bool
    {
        return $user?->role === 'admin';
    }
}
