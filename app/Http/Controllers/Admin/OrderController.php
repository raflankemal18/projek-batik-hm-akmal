<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
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

        Payment::whereIn('id', $request->ids)->delete();

        return redirect()->back()->with('success', 'Pesanan terpilih berhasil dihapus.');
    }

    public function bulkUpdateFulfillment(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:payments,id',
            'status' => 'required|in:processing,shipped,delivered'
        ]);

        \App\Models\Order::whereIn('payment_id', $request->ids)->update([
            'fulfillment_status' => $request->status
        ]);

        return redirect()->back()->with('success', 'Status pengiriman terpilih berhasil diperbarui.');
    }
}
