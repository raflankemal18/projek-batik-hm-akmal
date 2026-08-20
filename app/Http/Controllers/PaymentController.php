<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Services\PaymentService;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function index()
    {
        $payments = Payment::with(['paymentDetails.product', 'user'])
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Payment', ['payments' => $payments]);
    }

    public function storeApi(CheckoutRequest $request, PaymentService $paymentService)
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Token Invalid atau Expired'], 401);
        }

        try {
            $paymentService->createTransaction($user, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Pesanan berhasil dibuat',
                'redirect_url' => route('pembayaran') 
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Gagal memproses pembayaran: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request)
    {
        $payment = Payment::find($request->payment_id);
        
        if ($payment) {
            $payment->status = $request->status;            
            if ($request->status == 'success' || $request->status == 'capture' || $request->status == 'settlement') {
                $payment->status = 'success';
                $payment->snap_token = null;
            } elseif ($request->status == 'expire' || $request->status == 'cancel') {
                 $payment->status = 'failed';
            }

            $payment->save();
            return redirect()->back()->with('success', 'Status pembayaran diperbarui!');
        }

        return redirect()->back()->with('error', 'Transaksi tidak ditemukan.');
    }
}