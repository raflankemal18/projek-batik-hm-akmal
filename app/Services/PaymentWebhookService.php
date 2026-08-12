<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentWebhookService
{
    public function __construct(private MidtransService $midtransService) {}

    public function handle(array $payload): void
    {
        if (! $this->midtransService->verifySignature($payload)) {
            Log::warning('Invalid Midtrans webhook signature', ['order_id' => $payload['order_id'] ?? null]);
            abort(403, 'Invalid signature');
        }

        $payment = Payment::where('transaction_id', $payload['order_id'])->firstOrFail();

        if (in_array($payment->status, ['success', 'failed'])) {
            return;
        }

        DB::transaction(function () use ($payment, $payload) {
            $status = $payload['transaction_status'];

            if (in_array($status, ['capture', 'settlement', 'success'])) {
                $payment->update([
                    'status' => 'success',
                    'paid_at' => now(),
                ]);
                $payment->order?->update(['status' => 'paid']);

            } elseif (in_array($status, ['expire', 'cancel', 'deny'])) {
                $payment->update([
                    'status' => 'failed',
                    'snap_token' => null,
                ]);
                $payment->order?->update(['status' => 'cancelled']);

                foreach ($payment->order?->details ?? [] as $detail) {
                    if ($detail->product_size_id) {
                        InventoryManager::restore($detail->product_size_id, $detail->quantity);
                    }
                }
            }
        });
    }
}
