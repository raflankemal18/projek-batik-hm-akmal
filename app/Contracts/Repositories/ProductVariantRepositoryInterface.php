<?php

namespace App\Contracts\Repositories;

interface ProductVariantRepositoryInterface
{
    public function findOrNull(int $id);

    /** Baris variasi terkunci untuk update — dipakai checkout agar pengurangan stok aman saat konkuren. */
    public function findForUpdate(int $id);

    /** Variasi aktif milik produk tertentu; dipakai validasi "motif benar-benar milik produk ini". */
    public function findActiveForProduct(int $variantId, int $productId);

    public function findActiveByName(int $productId, string $name);

    /** Dikurangi hanya bila stok cukup; mengembalikan jumlah baris yang terpengaruh (0 = gagal). */
    public function decrementIfAvailable(int $id, int $qty): int;

    public function increment(int $id, int $qty): void;
}
