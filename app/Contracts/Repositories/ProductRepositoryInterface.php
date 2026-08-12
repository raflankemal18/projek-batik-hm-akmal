<?php

namespace App\Contracts\Repositories;

interface ProductRepositoryInterface
{
    public function getAllPaginated(int $perPage = 12);

    public function findWithRelations(int $id, array $relations = []);

    public function create(array $data);

    public function update(int $id, array $data);

    public function delete(int $id);

    public function getTopSelling(int $limit = 5);
}
