<?php

namespace App\Contracts\Repositories;

interface ProductRepositoryInterface
{
    public function getAllPaginated(int $perPage = 12);

    /** Versi halaman admin: semua motif ikut dimuat dan cache katalog dilewati. */
    public function getAllPaginatedForAdmin(int $perPage, ?string $search = null, string $sort = 'created_at', string $direction = 'desc');

    public function findWithRelations(int $id, array $relations = []);

    public function findWithTrashed(int $id);

    public function getArchivedPaginated(int $perPage = 12, ?string $search = null);

    public function create(array $data);

    public function update(int $id, array $data);

    public function delete(int $id);

    public function getTopSelling(int $limit = 5);
}
