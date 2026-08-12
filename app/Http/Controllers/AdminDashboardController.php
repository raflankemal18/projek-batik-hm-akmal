<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function __construct(private ProductService $productService) {}

    public function index()
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        // 1. Users
        $usersCount = User::count();
        $usersThisMonth = User::where('created_at', '>=', $startOfMonth)->count();
        $usersLastMonth = User::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();
        $usersDelta = $this->calculatePercentage($usersThisMonth, $usersLastMonth);

        // 2. Products (stok produk mungkin tidak perlu vs bulan lalu, tapi mari kita cek produk baru)
        $productCount = Product::count();
        $productThisMonth = Product::where('created_at', '>=', $startOfMonth)->count();
        $productLastMonth = Product::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();
        $productDelta = $this->calculatePercentage($productThisMonth, $productLastMonth);

        // 3. Orders
        $orderCount = Payment::count();
        $ordersThisMonth = Payment::where('created_at', '>=', $startOfMonth)->count();
        $ordersLastMonth = Payment::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();
        $ordersDelta = $this->calculatePercentage($ordersThisMonth, $ordersLastMonth);

        // 4. Sales
        $totalPenjualan = Payment::where('status', 'success')->sum('total_harga');
        $salesThisMonth = Payment::where('status', 'success')->where('created_at', '>=', $startOfMonth)->sum('total_harga');
        $salesLastMonth = Payment::where('status', 'success')->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->sum('total_harga');
        $salesDelta = $this->calculatePercentage($salesThisMonth, $salesLastMonth);

        $topSelling = $this->productService->getTopSelling(5);

        return Inertia::render('admin/Dashboard', [
            'usersCount' => $usersCount,
            'usersDelta' => $usersDelta,
            'productCount' => $productCount,
            'productDelta' => $productDelta,
            'orderCount' => $orderCount,
            'ordersDelta' => $ordersDelta,
            'totalPenjualan' => $totalPenjualan,
            'salesDelta' => $salesDelta,
            'topSelling' => $topSelling,
        ]);
    }

    private function calculatePercentage($thisMonth, $lastMonth)
    {
        if ($lastMonth == 0) {
            return $thisMonth > 0 ? 100 : 0;
        }
        return round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1);
    }

    public function users(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $sort = in_array($request->query('sort'), ['name', 'email', 'created_at'], true)
            ? $request->query('sort')
            : 'created_at';
        $direction = strtolower($request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'created_at'])
            ->when($search, fn ($q) => $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy($sort, $direction)
            ->paginate(10);

        return Inertia::render('admin/User', ['users' => $users]);
    }

    public function orders(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $sort = in_array($request->query('sort'), ['total_harga', 'status', 'created_at'], true)
            ? $request->query('sort')
            : 'created_at';
        $direction = strtolower($request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $payments = Payment::with('paymentDetails.product', 'user', 'order')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('transaction_id', 'like', "%{$search}%")
                        ->orWhere('id', $search)
                        ->orWhereHas('paymentDetails', function ($detail) use ($search) {
                            $detail->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('alamat', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(10);

        return Inertia::render('admin/Order', ['payments' => $payments]);
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $products = $q
            ? Product::with(['sizes', 'images' => fn ($qq) => $qq->limit(1)])
                ->where('name', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%")
                ->limit(8)
                ->get()
            : [];

        $users = $q
            ? User::select(['id', 'name', 'email', 'role'])
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->limit(8)
                ->get()
            : [];

        $payments = $q
            ? Payment::with('paymentDetails.product', 'user', 'order')
                ->where('transaction_id', 'like', "%{$q}%")
                ->orWhere('id', $q)
                ->orWhereHas('paymentDetails', function ($qq) use ($q) {
                    $qq->where('name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('alamat', 'like', "%{$q}%");
                })
                ->orderByDesc('created_at')
                ->limit(8)
                ->get()
            : [];

        return Inertia::render('admin/Search', [
            'q' => $q,
            'products' => $products,
            'users' => $users,
            'payments' => $payments,
        ]);
    }

    public function updateFulfillment(\App\Models\Order $order, Request $request)
    {
        $validated = $request->validate([
            'fulfillment_status' => 'required|in:processing,shipped,delivered',
        ]);

        $order->update(['fulfillment_status' => $validated['fulfillment_status']]);

        \App\Services\AuditLogService::recordAdmin('order.fulfillment_updated', $order, "Fulfillment updated to {$validated['fulfillment_status']}");

        return redirect()->back()->with('success', 'Status pengiriman berhasil diperbarui');
    }

    public function analytics()
    {
        // 7 Hari Terakhir Sales
        $salesData = Payment::where('status', 'success')
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, SUM(total_harga) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top Categories (if category field exists, else fallback to product sizes or generic)
        $topCategories = \App\Models\OrderDetail::join('products', 'order_details.product_id', '=', 'products.id')
            ->selectRaw('products.category, SUM(order_details.quantity) as total_sold')
            ->whereNotNull('products.category')
            ->groupBy('products.category')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        return response()->json([
            'sales' => $salesData,
            'categories' => $topCategories,
        ]);
    }
}
