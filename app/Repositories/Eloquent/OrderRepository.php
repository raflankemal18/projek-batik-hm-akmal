<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Models\Order;
use App\Models\OrderDetail;

class OrderRepository extends BaseRepository implements OrderRepositoryInterface
{
    protected function modelClass(): string
    {
        return Order::class;
    }

    public function getActiveDraftForUser(int $userId)
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('status', 'draft')
            ->first();
    }

    public function findOwned(int $id, int $userId)
    {
        return $this->model->where('id', $id)->where('user_id', $userId)->firstOrFail();
    }

    public function addDetail(int $orderId, array $data): OrderDetail
    {
        return OrderDetail::create(array_merge(['order_id' => $orderId], $data));
    }
}
