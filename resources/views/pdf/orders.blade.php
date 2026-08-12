<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Export PDF Pesanan</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f4f4f4; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-success { background-color: #d1fae5; color: #065f46; }
        .badge-pending { background-color: #fef3c7; color: #92400e; }
        .badge-failed { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <h2 class="text-center">Laporan Daftar Pesanan</h2>
    <p class="text-center">Tanggal Export: {{ date("d M Y H:i:s") }}</p>

    <table>
        <thead>
            <tr>
                <th>ID Order</th>
                <th>Pelanggan</th>
                <th>Total Harga</th>
                <th>Status</th>
                <th>Tanggal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
            <tr>
                <td>#{{ $payment->id }}</td>
                <td>
                    @if($payment->payment_details->first())
                        {{ $payment->payment_details->first()->name }}<br>
                        <small>{{ $payment->payment_details->first()->phone }}</small>
                    @else
                        {{ $payment->user ? $payment->user->email : "-" }}
                    @endif
                </td>
                <td class="text-right">Rp {{ number_format($payment->total_harga, 0, ",", ".") }}</td>
                <td>
                    @if($payment->status == "success")
                        <span class="badge badge-success">Lunas</span>
                    @elseif($payment->status == "pending")
                        <span class="badge badge-pending">Pending</span>
                    @else
                        <span class="badge badge-failed">Gagal</span>
                    @endif
                </td>
                <td>{{ $payment->created_at->format("d M Y H:i") }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>