<?php

namespace App\Contracts\Repositories;

interface OrderRepositoryInterface
{
    public function getActiveDraftForUser(int $userId);

    public function findOwned(int $id, int $userId);

    public function create(array $data);

    public function addDetail(int $orderId, array $data);
}
