<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Midtrans\Snap;
use Midtrans\Config;

class PaymentService
{
    public function createTransaction($user, $data)
    {
        return DB::transaction(function () use ($user, $data) {
            $randomPaymentId = random_int(100000, 999999);
            
            $payment = Payment::create([
                'id' => $randomPaymentId,
                'user_id' => $user->id,
                'total_harga' => $data['totalHarga'],
            ]);
            foreach ($data['orders'] as $order) {
                if (isset($order['order_details']) && is_array($order['order_details'])) {
                    foreach ($order['order_details'] as $detail) {
                        PaymentDetail::create([
                            'payment_id' => $payment->id,
                            'product_id' => $detail['product_id'],
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'phone' => $data['phone'],
                            'alamat' => $data['alamat'],
                            'size' => $detail['size'],
                            'quantity' => $detail['quantity'],
                            'harga' => $detail['harga']
                        ]);
                    }
                }
            }

            $this->configureMidtrans();

            $params = [
                'transaction_details' => [
                    'order_id' => $payment->id,
                    'gross_amount' => (int) $payment->total_harga,
                ],
                'customer_details' => [
                    'first_name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                ],
            ];

            $payment->snap_token = Snap::getSnapToken($params);
            $payment->save();

            Order::where('user_id', $user->id)->delete();

            return $payment;
        });
    }

    private function configureMidtrans()
    {
        Config::$serverKey = config('midtrans.serverKey');
        Config::$isProduction = config('midtrans.isProduction');
        Config::$isSanitized = config('midtrans.isSanitized');
        Config::$is3ds = config('midtrans.is3ds');
    }
}