<?php

namespace App\Contracts\Repositories;

interface ProductSizeRepositoryInterface
{
    public function findOrNull(int $id);

    public function findByProductAndSizeForUpdate(int $productId, string $size);

    public function decrementIfAvailable(int $id, int $qty): int;

    public function increment(int $id, int $qty): void;
}
