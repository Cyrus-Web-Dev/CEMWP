<?php

use App\Modules\Sales\Http\Controllers\Shop\AuthController;
use App\Modules\Sales\Http\Controllers\Shop\CartController;
use App\Modules\Sales\Http\Controllers\Shop\CatalogController;
use App\Modules\Sales\Http\Controllers\Shop\CheckoutController;
use App\Modules\Sales\Http\Controllers\Shop\OrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Etalase toko online (publik, tanpa login staf). Nama rute: shop.*
|--------------------------------------------------------------------------
*/
Route::middleware('web')->prefix('shop')->name('shop.')->group(function () {
    Route::get('/', [CatalogController::class, 'index'])->name('home');
    Route::get('products/{product}', [CatalogController::class, 'show'])->name('product');

    Route::get('cart', [CartController::class, 'show'])->name('cart');
    Route::post('cart/add', [CartController::class, 'add'])->middleware('throttle:60,1')->name('cart.add');
    Route::patch('cart/{product}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('cart/{product}', [CartController::class, 'remove'])->name('cart.remove');

    Route::get('checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('checkout/quote', [CheckoutController::class, 'quote'])->middleware('throttle:60,1')->name('checkout.quote');
    Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');

    Route::get('orders/{number}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{number}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::get('track', [OrderController::class, 'trackForm'])->name('track');
    Route::post('track', [OrderController::class, 'track'])->middleware('throttle:20,1')->name('track.lookup');
    Route::get('account', [OrderController::class, 'account'])->name('account');

    Route::get('login', [AuthController::class, 'show'])->name('auth');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('register', [AuthController::class, 'registerStart'])->middleware('throttle:6,1')->name('register.start');
    Route::post('register/verify', [AuthController::class, 'registerVerify'])->middleware('throttle:10,1')->name('register.verify');
    Route::post('password/forgot', [AuthController::class, 'resetStart'])->middleware('throttle:6,1')->name('reset.start');
    Route::post('password/reset', [AuthController::class, 'resetVerify'])->middleware('throttle:10,1')->name('reset.verify');
});
