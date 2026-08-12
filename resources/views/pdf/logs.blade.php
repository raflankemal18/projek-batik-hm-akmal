<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Log Aktivitas</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
            font-weight: bold;
        }
        h2 {
            text-align: center;
        }
    </style>
</head>
<body>
    <h2>Laporan Log Aktivitas</h2>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Tipe</th>
                <th>Action</th>
                <th>Deskripsi</th>
                <th>User</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
                <tr>
                    <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->type }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->descripsion ?: '-' }}</td>
                    <td>{{ $log->email ?? 'Sistem' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>