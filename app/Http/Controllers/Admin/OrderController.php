<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function export(Request $request)
    {
        $format = $request->query('format', 'csv');

        if ($format === 'pdf') {
            $payments = \App\Models\Payment::with("user", "payment_details.product")->get();
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.orders', compact('payments'));
            return $pdf->download("orders_export_" . date("Ymd_His") . ".pdf");
        }

        $response = new StreamedResponse(function() {
            $handle = fopen("php://output", "w");
            
            // CSV Headers
            fputcsv($handle, ["Order ID", "Tanggal", "User Email", "Total Harga", "Status"]);

            // Get all orders (chunked for memory efficiency)
            Payment::with("user")->chunk(100, function ($payments) use ($handle) {
                foreach ($payments as $payment) {
                    fputcsv($handle, [
                        $payment->id,
                        $payment->created_at->format("Y-m-d H:i:s"),
                        $payment->user ? $payment->user->email : "-",
                        $payment->total_harga,
                        $payment->status,
                    ]);
                }
            });

            fclose($handle);
        });

        $response->headers->set("Content-Type", "text/csv");
        $response->headers->set("Content-Disposition", "attachment; filename=\"orders_export_" . date("Ymd_His") . ".csv\"");

        return $response;
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:payments,id'
        ]);

        try {
            DB::beginTransaction();
            Payment::whereIn('id', $request->ids)->delete();
            DB::commit();

            return redirect()->back()->with('success', 'Pesanan terpilih berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['message' => 'Gagal menghapus pesanan: ' . $e->getMessage()]);
        }
    }

    public function bulkUpdateFulfillment(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:payments,id',
            'status' => 'required|in:processing,shipped,delivered'
        ]);

        try {
            DB::beginTransaction();
            $orders = \App\Models\Order::whereIn('payment_id', $request->ids)->with('payment.user')->get();
            foreach ($orders as $order) {
                $order->update(['fulfillment_status' => $request->status]);

                if ($order->payment && $order->payment->user) {
                    $statusLabel = [
                        'processing' => 'Diproses',
                        'shipped' => 'Dikirim',
                        'delivered' => 'Diterima'
                    ][$request->status] ?? $request->status;

                    $msg = "Pesanan #{$order->payment->id} telah diupdate menjadi: {$statusLabel}.";
                    $order->payment->user->notify(new \App\Notifications\OrderStatusUpdated($order, $msg));
                }
            }
            DB::commit();

            return redirect()->back()->with('success', 'Status pengiriman terpilih berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['message' => 'Gagal memperbarui status pengiriman: ' . $e->getMessage()]);
        }
    }
}
