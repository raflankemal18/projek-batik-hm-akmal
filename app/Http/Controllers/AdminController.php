<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentDetail;
use App\Models\Products;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function index()
    {

        $usersCount = User::count();
        $productCount = Products::count();
        $orderCount = Payment::count();
        $totalPenjualan = Payment::where('status', 'success')->sum('total_harga');
        $topSelling = PaymentDetail::with(['payment', 'product'])
            ->whereHas('payment', function ($query) {
                $query->where('status', 'success');
            })
            ->get()
            ->groupBy('product.name') // Group by product name
            ->map(function ($group) {
                return [
                    'product_name' => $group->first()->product->name,
                    'total_quantity' => $group->sum('quantity'),
                    'harga_baju' => $group->first()->product->harga
                ];
            });

        return Inertia::render('admin/Dashboard', ['usersCount' => $usersCount, 'productCount' => $productCount, 'orderCount' => $orderCount, 'totalPenjualan' => $totalPenjualan, 'topSelling' => $topSelling]);
    }

    public function users()
    {
        $users = User::paginate(2);

        // Kirim data users ke halaman React melalui Inertia
        return Inertia::render('admin/User', [
            'users' => $users
        ]);
    }

    public function products()
    {
        $products = Products::with(['sizes', 'images'])->get();
        // $products = Products::all();
        return Inertia::render('admin/Products', ['products' => $products]);
    }

    public function order()
    {


        $payments = Payment::with('paymentDetails.product', 'user')->get();

        return Inertia::render('admin/Order', ['payments' => $payments]);
    }

    public function addProduct(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:25',
            'harga' => 'required|numeric',
            'description' => 'nullable|string',
            'sizes' => 'required|array',
            'images' => 'sometimes|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        DB::beginTransaction();
        try {
            $product = Products::create([
                'name' => $request->name,
                'harga' => $request->harga,
                'description' => $request->description,
            ]);

            // Mengurai JSON string untuk setiap size
            $sizes = collect($request->input('sizes'))->map(function ($size) {
                return json_decode($size, true);
            });

            // Menyimpan setiap size
            foreach ($sizes as $size) {
                $product->sizes()->create([
                    'size' => $size['size'],
                    'stock' => $size['stock'],
                ]);
            }

            // Menangani upload gambar
            if ($request->has('images') && is_array($request->file('images'))) {
                foreach ($request->file('images') as $image) {
                    if ($image) {  // Pastikan $image valid
                        $path = $image->store('public/product_images');
                        $product->images()->create(['image_path' => $path]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('admin.dashboard')->with('success', 'Product added successfully!');
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error adding product: ' . $th->getMessage());
            return redirect()->back()->withErrors(['error' => $th->getMessage()]);
        }
    }

    public function datauser($id)
    {
        $user = Auth::user();  // User yang melakukan aksi
        $datauser = User::findOrFail($id);  // User yang akan dihapus


        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized, only admins can perform deletions'], 403);
        }


        if ($datauser->role === 'admin') {
            return response()->json(['message' => 'Deletion failed, admin users cannot be deleted'], 405);
        }


        $datauser->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }

    public function updateProduct(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'harga' => 'required|numeric',
            'description' => 'nullable|string',
            'sizes' => 'required|array', // JSON string
            'images' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $product = Products::findOrFail($id);
            
            $product->update([
                'name' => $request->name,
                'harga' => $request->harga,
                'description' => $request->description,
            ]);

            $product->sizes()->delete();
            $sizes = collect($request->input('sizes'))->map(function ($size) {
                return is_string($size) ? json_decode($size, true) : $size;
            });

            foreach ($sizes as $size) {
                $product->sizes()->create([
                    'size' => $size['size'],
                    'stock' => $size['stock'],
                ]);
            }

            if ($request->has('images') && is_array($request->file('images'))) {
                foreach ($request->file('images') as $image) {
                    if ($image) {
                        $path = $image->store('public/product_images');
                        $product->images()->create(['image_path' => $path]);
                    }
                }
            }

            DB::commit();
            return redirect()->back()->with('success', 'Produk berhasil diperbarui');
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $th->getMessage()]);
        }
    }

    public function deleteProduct($id)
    {
        try {
            $product = Products::findOrFail($id);
            
            foreach ($product->images as $image) {
                Storage::delete($image->image_path); 
            }
            
            $product->delete(); 
            
            return redirect()->back()->with('success', 'Produk berhasil dihapus');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus produk');
        }
    }
}
