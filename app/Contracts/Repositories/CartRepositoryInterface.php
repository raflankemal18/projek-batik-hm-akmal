<?php

namespace App\Contracts\Repositories;

interface CartRepositoryInterface
{
    public function getOrCreateForUser(int $userId);

    public function getWithDetails(int $userId);

    public function getForCheckout(int $userId);

    public function addDetail(int $cartId, array $data);

    public function findDetailForProduct(int $cartId, int $productId, string $size);

    public function findDetailOwned(int $cartDetailId, int $userId);

    public function updateDetail(int $cartDetailId, array $data);

    public function removeDetail(int $cartDetailId);

    public function clearForUser(int $userId);
}
