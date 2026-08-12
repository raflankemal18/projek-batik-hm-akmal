<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\SearchController;

// Public
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/products', [HomeController::class, 'products'])->name('products');
Route::get('/products/{id}', [HomeController::class, 'show'])->name('products.show');
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::post('/midtrans/webhook', [PaymentController::class, 'webhook'])->middleware('throttle:60,1');

// Role-based landing after login
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return auth()->user()->role === 'admin'
            ? redirect()->route('admin.dashboard')
            : redirect()->route('home');
    })->name('dashboard');
});

// Auth + admin
Route::middleware('auth', 'role:admin')->group(function () {
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/analytics', [AdminDashboardController::class, 'analytics'])->name('admin.analytics');
    Route::get('/admin/users', [AdminDashboardController::class, 'users'])->name('admin.users');
    Route::get('/admin/orders', [AdminDashboardController::class, 'orders'])->name('admin.orders');
    Route::get('/admin/orders/export', [OrderController::class, 'export'])->name('admin.orders.export');
    Route::get('/admin/products', [ProductController::class, 'index'])->name('admin.products');
    Route::post('/admin/products', [ProductController::class, 'store'])->name('admin.products.store');
    Route::put('/admin/products/{product}', [ProductController::class, 'update'])->name('admin.products.update');
    Route::delete('/admin/products/bulk', [ProductController::class, 'bulkDelete'])->name('admin.products.bulk_destroy');
    Route::delete('/admin/products/{product}', [ProductController::class, 'destroy'])->name('admin.products.destroy');
    Route::delete('/admin/users/bulk', [UserController::class, 'bulkDelete'])->name('admin.users.bulk_destroy');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::patch('/admin/orders/{order}/fulfillment', [AdminDashboardController::class, 'updateFulfillment'])->name('admin.orders.fulfillment');
    Route::delete('/admin/orders/bulk', [OrderController::class, 'bulkDelete'])->name('admin.orders.bulk.delete');
    Route::patch('/admin/orders/bulk-fulfillment', [OrderController::class, 'bulkUpdateFulfillment'])->name('admin.orders.bulk.fulfillment');
    Route::get('/admin/logs/export', [ActivityLogController::class, 'export'])->name('admin.logs.export');
    Route::get('/admin/logs', [ActivityLogController::class, 'index'])->name('admin.logs');
    Route::get('/admin/banners', [\App\Http\Controllers\Admin\BannerController::class, 'index'])->name('admin.banners');
    Route::post('/admin/banners', [\App\Http\Controllers\Admin\BannerController::class, 'store'])->name('admin.banners.store');
    Route::put('/admin/banners/{banner}', [\App\Http\Controllers\Admin\BannerController::class, 'update'])->name('admin.banners.update');
    Route::delete('/admin/banners/{banner}', [\App\Http\Controllers\Admin\BannerController::class, 'destroy'])->name('admin.banners.destroy');
    Route::patch('/admin/banners/{banner}/toggle', [\App\Http\Controllers\Admin\BannerController::class, 'toggle'])->name('admin.banners.toggle');

});

// Auth + user
Route::middleware('auth', 'role:user')->group(function () {
    Route::post('/cart/add', [CartController::class, 'add']);
    Route::post('/cart/update', [CartController::class, 'updateQuantity']);
    Route::post('/cart/remove', [CartController::class, 'remove']);
    Route::get('/cart', [HomeController::class, 'keranjang']);
    Route::get('/co', [HomeController::class, 'checkout'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/pembayaran', [PaymentController::class, 'index'])->name('pembayaran');
    Route::post('/payment', [PaymentController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/wishlist', [\App\Http\Controllers\WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/toggle', [\App\Http\Controllers\WishlistController::class, 'toggle'])->name('wishlist.toggle');
});

// Profile
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/payments/{payment}/invoice', [PaymentController::class, 'invoice'])->name('payments.invoice');
});

require __DIR__.'/auth.php';
