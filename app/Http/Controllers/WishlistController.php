<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Product;
use App\Models\Wishlist;
use Inertia\Inertia;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $wishlists = Wishlist::with(['product.images'])
            ->where('user_id', $request->user()->id)
            ->get();
            
        return Inertia::render('Wishlist', [
            'wishlists' => $wishlists
        ]);
    }

    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $userId = $request->user()->id;
        $productId = $validated['product_id'];

        $wishlist = Wishlist::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();

        if ($wishlist) {
            $wishlist->delete();
            return redirect()->back()->with('success', 'Produk dihapus dari wishlist');
        } else {
            Wishlist::create([
                'user_id' => $userId,
                'product_id' => $productId
            ]);
            return redirect()->back()->with('success', 'Produk ditambahkan ke wishlist');
        }
    }
}
