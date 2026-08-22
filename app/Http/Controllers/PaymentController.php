<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\AuditLogService;
use App\Services\MidtransService;
use App\Services\OrderNumberService;
use App\Services\PaymentWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = Payment::with(['paymentDetails.product', 'user', 'order'])
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(10);

        return Inertia::render('Payment', ['payments' => $payments]);
    }

    public function store(Request $request, MidtransService $midtransService)
    {
        $user = $request->user();

        $order = Order::where('user_id', $user->id)
            ->where('status', 'draft')
            ->lockForUpdate()
            ->first();

        if (! $order) {
            return redirect()->back()->with('error', 'Tidak ada order aktif');
        }

        $dbTotal = $order->details->sum(fn ($d) => $d->harga * $d->quantity);
        if ($dbTotal != $order->total_harga) {
            $order->update(['total_harga' => $dbTotal]);
        }

        $existingPayment = Payment::where('order_id', $order->id)
            ->whereIn('status', ['pending', 'success'])
            ->lockForUpdate()
            ->first();

        if ($existingPayment && $existingPayment->status === 'success') {
            return redirect()->route('pembayaran')->with('payment', $existingPayment);
        }

        if ($existingPayment && $existingPayment->snap_token) {
            return redirect()->route('pembayaran')->with('payment', $existingPayment);
        }

        $transactionId = OrderNumberService::generate();

        try {
            DB::beginTransaction();
            $payment = Payment::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'transaction_id' => $transactionId,
                'total_harga' => $order->total_harga,
                'status' => 'pending',
            ]);

            $snapToken = $midtransService->getSnapToken([
                'transaction_details' => [
                    'order_id' => $transactionId,
                    'gross_amount' => (int) $order->total_harga,
                ],
                'customer_details' => [
                    'first_name' => $order->name ?? $user->name,
                    'email' => $order->email ?? $user->email,
                    'phone' => $order->phone ?? '',
                ],
            ]);

            $payment->update(['snap_token' => $snapToken]);
            $order->update(['status' => 'pending_payment']);

            AuditLogService::recordTransaction('payment.initiated', $payment, 'Payment + snap token', [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'total_harga' => $payment->total_harga,
            ]);
            DB::commit();

            return redirect()->route('pembayaran')->with('payment', $payment);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['message' => 'Gagal menginisiasi pembayaran: ' . $e->getMessage()]);
        }
    }

    public function webhook(Request $request, PaymentWebhookService $webhookService)
    {
        $webhookService->handle($request->all());

        return response()->json(['status' => 'ok']);
    }

    public function invoice(Payment $payment)
    {
        if (auth()->user()->role !== 'admin' && $payment->user_id !== auth()->id()) {
            abort(403, 'Unauthorized access to invoice');
        }

        if ($payment->status !== 'success') {
            abort(404, 'Invoice only available for paid payments');
        }

        $payment->load(['user', 'order.details.product']);

        if (!$payment->order) {
            abort(404, 'Order details not found');
        }

        return Inertia::render('Invoice', ['payment' => $payment]);
    }
}
