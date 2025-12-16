<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/about', function () {
    return Inertia::render('about');
});

Route::get('/invoice', function () {
    return Inertia::render('Invoice');
});

Route::get('/test', function () {
    return Inertia::render('Test');
});





// Route::get('/test', function () {
//     return 'Laravel test is successful.';
// });

Route::get('/products', [HomeController::class, 'products'])->name('products');
Route::get('/products/{id}', [HomeController::class, 'show'])->name('products.show');

Route::get('admin/data/user/{id}', [AdminController::class, 'datauser'])->name('admin.data.user');

Route::middleware('auth', 'role:admin')->group(function () {
    Route::get('admin/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');
    Route::get('admin/user', [AdminController::class, 'users'])->name('admin.user');
    Route::get('admin/order', [AdminController::class, 'order'])->name('admin.order');
    Route::get('admin/products', [AdminController::class, 'products'])->name('admin.products');
    Route::post('/add-product', [AdminController::class, 'addProduct']);
    Route::post('/admin/product/{id}/update', [AdminController::class, 'updateProduct'])->name('admin.product.update');
    Route::delete('/admin/product/{id}/delete', [AdminController::class, 'deleteProduct'])->name('admin.product.delete');
});

Route::middleware('auth', 'role:user')->group(function () {
    Route::get('user/dashboard', [UserController::class, 'index'])->name('user.dashboard');
    Route::post('/cart/add', [CartController::class, 'add']);
    Route::post('/cart/update', [CartController::class, 'updateQuantity']);
    Route::post('/cart/remove', [CartController::class, 'remove']);
    Route::get('/cart', [HomeController::class, 'keranjang']);
    Route::post('/checkout', [CartController::class, 'checkout']);
    Route::get('/co', [HomeController::class, 'co'])->name('co');
    Route::post('/payment', [PaymentController::class, 'store'])->name('payment');
    // Route::get('/pembayaran', [PaymentController::class, 'index'])->name('pembayaran');
    Route::post('/update-payment-status', [PaymentController::class, 'updateStatus'])->name('update-payment-status');
    Route::get('/pembayaran', [PaymentController::class, 'index'])->name('pembayaran')->middleware('auth');
});



// Route::get('/dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
