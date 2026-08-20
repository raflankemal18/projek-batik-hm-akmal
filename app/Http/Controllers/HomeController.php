<?php

namespace App\Http\Controllers;

use App\Models\Carts;
use App\Models\Order;
use App\Models\Products;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $products = Products::with(['sizes', 'images'])->get();
        return Inertia::render('Welcome', ['products' => $products]);
    }

    public function products()
    {
        $products = Products::with(['sizes', 'images'])->get();
        return Inertia::render('Products', ['products' => $products]);
    }

    public function show($id)
    {
        $products = Products::with(['sizes', 'images'])->findOrFail($id);
        return Inertia::render('Test', ['products' => $products]);
    }
    public function keranjang()
    {
        try {
            $keranjangs = Carts::with(['details.product.images', 'user'])
                ->where('user_id', auth()->user()->id)
                ->get();
            return Inertia::render('Keranjang', ['keranjangs' => $keranjangs]);
        } catch (\Exception $e) {
            Log::error('Failed to load cart:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to retrieve cart', 'error' => $e->getMessage()], 409);
        }
    }

    public function co()
    {
        $orders = Order::with(['orderDetails.product.images', 'user'])->where('user_id', auth()->user()->id)->get();
        Log::info('orders:', ['orders' => $orders->toArray()]);
        return Inertia::render('Checkout', ['orders' => $orders]);
    }
}
