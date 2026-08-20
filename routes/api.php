<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

Route::middleware('auth:api')->group(function () {
    Route::post('/payment/process', [PaymentController::class, 'storeApi']);
});