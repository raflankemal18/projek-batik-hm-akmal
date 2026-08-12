<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', ActivityLog::class);

        $query = ActivityLog::query();

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->query('date_to').' 23:59:59');
        }
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $logs = $query->latest('created_at')->paginate(20)->withQueryString();

        return Inertia::render('admin/Logs', [
            'logs' => $logs,
            'filters' => $request->only(['type', 'date_from', 'date_to', 'search']),
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', ActivityLog::class);

        $query = ActivityLog::query();

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->query('date_to').' 23:59:59');
        }
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $logs = $query->latest('created_at')->get();

        $format = $request->query('format', 'csv');

        if ($format === 'pdf') {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.logs', compact('logs'));
            return $pdf->download('activity_logs.pdf');
        }

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=activity_logs.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use($logs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Type', 'Action', 'Description', 'User', 'Tanggal']);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->type,
                    $log->action,
                    $log->description,
                    $log->email ?? 'Sistem',
                    $log->created_at->format('Y-m-d H:i:s')
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
