<?php

namespace App\Contracts\Repositories;

interface PaymentRepositoryInterface
{
    public function paginateForUser(int $userId, int $perPage = 10);

    public function findOwned(int $id, int $userId);

    public function findByTransactionId(string $transactionId);

    public function getActiveForOrder(int $orderId);

    public function create(array $data);
}
