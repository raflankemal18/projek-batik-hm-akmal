<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->id === $payment->user_id || $user->role === 'admin';
    }

    public function create(?User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->role === 'admin';
    }
}
