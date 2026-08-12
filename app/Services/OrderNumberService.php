<?php

namespace App\Services;

use App\Models\Payment;

class OrderNumberService
{
    public static function generate(): string
    {
        $prefix = 'INV-'.now()->format('ymd');
        $last = Payment::where('transaction_id', 'LIKE', $prefix.'%')
            ->orderByDesc('transaction_id')
            ->value('transaction_id');

        if ($last) {
            $sequence = (int) substr($last, -6) + 1;
        } else {
            $sequence = 1;
        }

        return $prefix.'-'.str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }
}
