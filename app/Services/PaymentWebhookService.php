<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentWebhookService
{
    public function __construct(
        private MidtransService $midtransService,
        private InventoryManager $inventoryManager,
    ) {}

    public function handle(array $payload): void
    {
        $signatureValid = $this->midtransService->verifySignature($payload);

        AuditLogService::recordTransaction('webhook.received', null, 'Notifikasi Midtrans', [
            'transaction_id' => $payload['order_id'] ?? null,
            'status_code' => $payload['status_code'] ?? null,
            'transaction_status' => $payload['transaction_status'] ?? null,
            'signature_valid' => $signatureValid,
        ]);

        if (! $signatureValid) {
            Log::warning('Invalid Midtrans webhook signature', ['order_id' => $payload['order_id'] ?? null]);
            abort(403, 'Invalid signature');
        }

        // Pembacaan payment, pengecekan status terminal, dan perubahan statusnya
        // terjadi DALAM satu transaksi dengan baris payment dikunci (lockForUpdate).
        // Sebelumnya payment dibaca sebelum transaksi, sehingga dua notifikasi
        // gagal yang tiba bersamaan bisa sama-sama melihat status 'pending' dan
        // sama-sama mengembalikan stok — stok naik dua kali.
        $processed = DB::transaction(function () use ($payload) {
            $payment = Payment::where('transaction_id', $payload['order_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($payment->status, ['success', 'failed'])) {
                return false;
            }

            $status = $payload['transaction_status'];

            if (in_array($status, ['capture', 'settlement', 'success'])) {
                $payment->update([
                    'status' => 'success',
                    'paid_at' => now(),
                ]);
                $payment->order?->update(['status' => 'paid']);

                AuditLogService::recordTransaction('payment.paid', $payment, 'Pembayaran sukses', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                    'total_harga' => $payment->total_harga,
                ]);

            } elseif (in_array($status, ['expire', 'cancel', 'deny'])) {
                $payment->update([
                    'status' => 'failed',
                    'snap_token' => null,
                ]);
                $payment->order?->update(['status' => 'cancelled']);

                if ($status === 'expire') {
                    AuditLogService::recordTransaction('payment.expired', $payment, 'Pembayaran expired', [
                        'payment_id' => $payment->id,
                        'transaction_id' => $payment->transaction_id,
                    ]);
                } else {
                    AuditLogService::recordTransaction('payment.failed', $payment, 'Pembayaran gagal', [
                        'payment_id' => $payment->id,
                        'transaction_id' => $payment->transaction_id,
                        'reason' => $status,
                    ]);
                }

                if ($payment->order) {
                    AuditLogService::recordTransaction('order.cancelled', $payment->order, 'Order dibatalkan', [
                        'order_id' => $payment->order->id,
                        'reason' => "payment.{$status}",
                    ]);
                }

                foreach ($payment->order?->details ?? [] as $detail) {
                    if ($detail->product_variant_id) {
                        $this->inventoryManager->restoreVariant($detail->product_variant_id, $detail->quantity, "payment.{$status}");
                    } elseif ($detail->product_size_id) {
                        $this->inventoryManager->restore($detail->product_size_id, $detail->quantity, "payment.{$status}");
                    }
                }
            }

            return true;
        });

        if (! $processed) {
            Log::info('Ignored Midtrans webhook for already-settled payment', [
                'order_id' => $payload['order_id'] ?? null,
            ]);
        }
    }
}
