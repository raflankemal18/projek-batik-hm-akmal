<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Services\CheckoutService;

class CheckoutController extends Controller
{
    public function __construct(private CheckoutService $checkoutService) {}

    public function store(CheckoutRequest $request)
    {
        $this->checkoutService->createDraftOrder($request->validated(), $request->user());

        return redirect()->route('pembayaran')->with('success', 'Pesanan berhasil dibuat!');
    }
}
