<?php

namespace App\Contracts\Repositories;

interface UserRepositoryInterface
{
    public function paginate(int $perPage = 10);

    public function find(int $id);

    public function create(array $data);

    public function delete(int $id);
}
