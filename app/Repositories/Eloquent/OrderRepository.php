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

    public function getActiveDraftForUserForUpdate(int $userId)
    {
        return $this->query()
            ->where('user_id', $userId)
            ->where('status', 'draft')
            ->lockForUpdate()
            ->first();
    }

    public function getExpiredDraftsForUpdate()
    {
        return $this->query()
            ->where('status', 'draft')
            ->where('expires_at', '<', now())
            ->lockForUpdate()
            ->get();
    }

    // findOwned(int $id, $user): Model is inherited from BaseRepository's
    // OwnedScopes trait — do not redeclare it here. A narrower `int $userId`
    // override is an LSP violation against the trait's untyped `$user` param
    // and is a fatal "Declaration ... must be compatible" error the moment
    // this class is actually instantiated (it was previously masked because
    // nothing in the app injected OrderRepositoryInterface yet).

    public function addDetail(int $orderId, array $data): OrderDetail
    {
        return OrderDetail::create(array_merge(['order_id' => $orderId], $data));
    }
}
