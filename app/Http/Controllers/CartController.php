<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\CartAddRequest;
use App\Http\Requests\Cart\CartQuantityRequest;
use App\Http\Requests\Cart\CartRemoveRequest;
use App\Services\CartService;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

    public function add(CartAddRequest $request)
    {
        $this->cartService->add($request, $request->user());

        return redirect()->back();
    }

    public function updateQuantity(CartQuantityRequest $request)
    {
        $this->cartService->updateQuantity($request, $request->user());

        return redirect()->back();
    }

    public function remove(CartRemoveRequest $request)
    {
        $this->cartService->remove($request->cart_detail_id, $request->user());

        return redirect()->back();
    }
}
