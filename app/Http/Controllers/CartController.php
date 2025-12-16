<?php

namespace App\Http\Controllers;

use App\Models\CartDetail;
use App\Models\Carts;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CartController extends Controller
{
    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'size' => 'required|string',
        ]);

        if (!Auth::check()) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $userId = Auth::id();

        try {
            DB::beginTransaction();


            $cart = Carts::firstOrCreate(['user_id' => $userId]);


            $cartDetail = CartDetail::where([
                'cart_id' => $cart->id,
                'product_id' => $request->input('product_id'),
                'size' => $request->input('size'), // Memastikan ukuran juga diperiksa
            ])->first();

            if ($cartDetail) {

                $cartDetail->quantity += $request->input('quantity');
            } else {

                $cartDetail = new CartDetail([
                    'cart_id' => $cart->id,
                    'product_id' => $request->input('product_id'),
                    'quantity' => $request->input('quantity'),
                    'size' => $request->input('size'),
                ]);
            }
            $cartDetail->save();

            DB::commit();
            return redirect()->back();
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to add or update the product in the cart', 'exception' => $th->getMessage()], 500);
        }
    }


    public function updateQuantity(Request $request)
    {
        $request->validate([
            'cart_detail_id' => 'required|integer|exists:cart_details,id',
            'quantity' => 'required|integer|min:1'
        ]);

        if (!Auth::check()) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        try {
            $cartDetail = CartDetail::findOrFail($request->cart_detail_id);
            $cartDetail->quantity = $request->quantity;
            $cartDetail->save();

            return redirect()->back();
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function checkout(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.size' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.harga' => 'required|numeric',
            'totalHarga' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            dd($validator->errors(), $request->all());
        }

        $data = $validator->validated();

        DB::beginTransaction(); 
        try {
            Order::where('user_id', $user->id)->delete();
            $order = new Order;
            $order->user_id = $user->id;
            $order->total_harga = $data['totalHarga'];
            $order->save();

            foreach ($data['items'] as $item) {
                $orderDetail = new OrderDetail([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'size' => $item['size'],
                    'quantity' => $item['quantity'],
                    'harga' => $item['harga']
                ]);
                $orderDetail->save();
            }

            $cart = Carts::where('user_id', $user->id);
            $cart->delete();

            DB::commit();
            return redirect()->route('co');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan saat checkout.');
        }
    }

    public function remove(Request $request)
    {
        $request->validate([
            'cart_detail_id' => 'required|integer|exists:cart_details,id',
        ]);

        try {
            $cartDetail = CartDetail::findOrFail($request->cart_detail_id);
            
            $cartDetail->delete();

            return redirect()->back();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Gagal menghapus item: ' . $e->getMessage()], 500);
        }
    }
}
