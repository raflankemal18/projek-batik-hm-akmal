<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    private bool $configured = false;

    public function configure(): void
    {
        if ($this->configured) {
            return;
        }

        Config::$serverKey = config('midtrans.serverKey');
        Config::$isProduction = config('midtrans.isProduction');
        Config::$isSanitized = config('midtrans.isSanitized');
        Config::$is3ds = config('midtrans.is3ds');

        $this->configured = true;
    }

    public function getSnapToken(array $params): string
    {
        $this->configure();

        return Snap::getSnapToken($params);
    }

    public function verifySignature(array $payload): bool
    {
        $serverKey = config('midtrans.serverKey');
        $signatureKey = $payload['signature_key'] ?? '';
        $expected = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$serverKey
        );

        return hash_equals($expected, $signatureKey);
    }
}
