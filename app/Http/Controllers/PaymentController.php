<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentDetail;
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

        // Order yang belum punya payment (baru hasil checkout, status draft) perlu
        // dikirim ke halaman ini: tanpa ini user mendarat di /pembayaran yang
        // tidak menampilkan apa pun untuk dibayar — tidak ada jalur UI lain untuk
        // membuat payment (POST /payment hanya dipanggil dari sini).
        $unpaidOrder = Order::with(['details.product', 'payment'])
            ->where('user_id', auth()->id())
            ->where('status', 'draft')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereDoesntHave('payment', fn ($q) => $q->whereIn('status', ['pending', 'success']))
            ->latest('id')
            ->first();

        return Inertia::render('Payment', [
            'payments' => $payments,
            'unpaidOrder' => $unpaidOrder,
        ]);
    }

    public function store(Request $request, MidtransService $midtransService)
    {
        $user = $request->user();

        $order = Order::with('details')
            ->where('user_id', $user->id)
            ->where('status', 'draft')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
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

        if ($existingPayment) {
            return redirect()->route('pembayaran')->with('payment', $existingPayment);
        }

        $transactionId = OrderNumberService::generate();

        // Panggilan API Midtrans dilakukan DI LUAR transaksi DB. Kalau gagal
        // (timeout, kredensial salah, layanan down), tidak ada payment yatim
        // yang tertinggal di tabel `payments` — dan pesan errornya yang sampai
        // ke user, bukan "Gagal memproses checkout" yang menyesatkan.
        try {
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
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['message' => 'Gagal menghubungi layanan pembayaran. Silakan coba beberapa saat lagi.']);
        }

        DB::transaction(function () use ($order, $user, $transactionId, $snapToken) {
            $payment = Payment::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'transaction_id' => $transactionId,
                'total_harga' => $order->total_harga,
                'status' => 'pending',
                'snap_token' => $snapToken,
            ]);

            // payment_details tidak pernah diisi di mana pun sebelumnya, padahal
            // halaman pembayaran, invoice, dan export admin membacanya. Disalin
            // dari order_details supaya rincian item benar-benar tersedia.
            foreach ($order->details as $detail) {
                PaymentDetail::create([
                    'payment_id' => $payment->id,
                    'product_id' => $detail->product_id,
                    'name' => $order->name,
                    'email' => $order->email,
                    'phone' => $order->phone,
                    'alamat' => $order->alamat,
                    'size' => $detail->size,
                    'quantity' => $detail->quantity,
                    'harga' => $detail->harga,
                ]);
            }

            $order->update(['status' => 'pending_payment']);

            AuditLogService::recordTransaction('payment.initiated', $payment, 'Payment + snap token', [
                'payment_id' => $payment->id,
                'transaction_id' => $transactionId,
                'total_harga' => $payment->total_harga,
            ]);
        });

        return redirect()->route('pembayaran')->with('payment', 'ok');
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
