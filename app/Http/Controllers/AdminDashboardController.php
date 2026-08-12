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
        $usersCount = User::count();
        $productCount = Product::count();
        $orderCount = Payment::count();
        $totalPenjualan = Payment::where('status', 'success')->sum('total_harga');
        $topSelling = $this->productService->getTopSelling(5);

        return Inertia::render('admin/Dashboard', [
            'usersCount' => $usersCount,
            'productCount' => $productCount,
            'orderCount' => $orderCount,
            'totalPenjualan' => $totalPenjualan,
            'topSelling' => $topSelling,
        ]);
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

        $payments = Payment::with('paymentDetails.product', 'user')
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
            ? Payment::with('paymentDetails.product', 'user')
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
}
