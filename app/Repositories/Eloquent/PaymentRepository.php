<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;

class PaymentRepository extends BaseRepository implements PaymentRepositoryInterface
{
    protected function modelClass(): string
    {
        return Payment::class;
    }

    public function paginateForUser(int $userId, int $perPage = 10)
    {
        return $this->query()
            ->with(['paymentDetails.product', 'user'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findByTransactionId(string $transactionId)
    {
        return $this->query()->where('transaction_id', $transactionId)->first();
    }

    public function getActiveForOrder(int $orderId)
    {
        return $this->query()
            ->where('order_id', $orderId)
            ->whereIn('status', ['pending', 'success'])
            ->lockForUpdate()
            ->first();
    }
}
