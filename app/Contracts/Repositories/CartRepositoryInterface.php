<?php

namespace App\Contracts\Repositories;

interface CartRepositoryInterface
{
    public function getOrCreateForUser(int $userId);

    public function getWithDetails(int $userId);

    public function addDetail(int $cartId, array $data);

    public function findDetailOwned(int $cartDetailId, int $userId);

    public function updateDetail(int $cartDetailId, array $data);

    public function removeDetail(int $cartDetailId);

    public function clearForUser(int $userId);
}
