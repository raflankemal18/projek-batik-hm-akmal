<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function __construct(
        private ProductService $productService,
        private CartService $cartService,
    ) {}

    public function index()
    {
        $products = $this->productService->getAllPaginated(12)->items();
        $banners = \App\Models\Banner::where('is_active', true)->get();

        return Inertia::render('Welcome', ['products' => $products, 'banners' => $banners]);
    }

    public function products(Request $request)
    {
        $category = (string) $request->query('category', 'all');

        $products = $this->productService->getAllPaginated(
            12,
            $request->query('search'),
            $request->query('sort', 'created_at'),
            $request->query('direction', 'desc'),
            $category,
        );

        return Inertia::render('Products', [
            'products' => $products->items(),
            'activeCategory' => $category,
        ]);
    }

    public function show(Request $request, $id)
    {
        $product = $this->productService->findWithRelations($id);

        $related = Product::with(['sizes', 'images' => fn ($q) => $q->limit(1), 'activeVariants'])
            ->when($product->category, fn ($q) => $q->where('category', $product->category))
            ->where('id', '!=', $product->id)
            ->inRandomOrder()
            ->limit(4)
            ->get();

        if ($related->count() < 4) {
            $extra = Product::with(['sizes', 'images' => fn ($q) => $q->limit(1), 'activeVariants'])
                ->where('id', '!=', $product->id)
                ->whereNotIn('id', $related->pluck('id'))
                ->inRandomOrder()
                ->limit(4 - $related->count())
                ->get();

            $related = $related->concat($extra);
        }

        return Inertia::render('ProductDetail', [
            'products' => $product,
            'relatedProducts' => $related,
        ]);
    }

    public function about()
    {
        return Inertia::render('about');
    }

    public function keranjang()
    {
        $keranjangs = $this->cartService->getCartWithProducts(auth()->user());

        return Inertia::render('Keranjang', ['keranjangs' => $keranjangs]);
    }

    public function checkout()
    {
        $cart = $this->cartService->getCartWithProducts(auth()->user())->first();

        $orders = $cart
            ? [[
                'id' => $cart->id,
                'order_details' => $cart->details->map(fn ($d) => [
                    'id' => $d->id,
                    'product' => $d->product,
                    'size' => $d->size,
                    'quantity' => $d->quantity,
                    'harga' => (float) $d->product->harga,
                ]),
                'total_harga' => (float) $cart->details->sum(fn ($d) => $d->product->harga * $d->quantity),
            ]]
            : [];

        return Inertia::render('Checkout', ['orders' => $orders]);
    }
}
