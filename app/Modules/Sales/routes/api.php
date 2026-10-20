<?php

use App\Modules\Sales\Http\Controllers\Api\AuthController;
use App\Modules\Sales\Http\Controllers\Api\MidtransWebhookController;
use App\Modules\Sales\Http\Controllers\Api\OrderApiController;
use App\Modules\Sales\Http\Controllers\Api\ShopApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 untuk aplikasi mobile (React Native). Katalog: lihat modul Warehouse (/api/v1/catalog/*).
| Token pelanggan (Sanctum) didapat dari /auth/login atau /auth/register/verify.
|--------------------------------------------------------------------------
*/
Route::prefix('api/v1')->middleware('api')->name('api.')->group(function () {

    // Autentikasi (dibatasi ketat: 10 permintaan / menit / IP)
    Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
        Route::post('register', [AuthController::class, 'registerStart'])->name('auth.register');
        Route::post('register/verify', [AuthController::class, 'registerVerify'])->name('auth.register.verify');
        Route::post('login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('password/forgot', [AuthController::class, 'resetStart'])->name('auth.password.forgot');
        Route::post('password/reset', [AuthController::class, 'resetVerify'])->name('auth.password.reset');
    });

    // Data pendukung checkout (publik)
    Route::middleware('throttle:api')->group(function () {
        Route::get('shipping-methods', [ShopApiController::class, 'shippingMethods'])->name('shipping-methods');
        Route::get('payment-methods', [ShopApiController::class, 'paymentMethods'])->name('payment-methods');
        Route::get('categories', [ShopApiController::class, 'categories'])->name('categories');
        Route::post('quote', [ShopApiController::class, 'quote'])->name('quote');
    });

    // Khusus pelanggan berotentikasi
    Route::middleware(['auth:sanctum', 'customer.token', 'throttle:api'])->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('orders', [OrderApiController::class, 'index'])->name('orders.index');
        Route::post('orders', [OrderApiController::class, 'store'])->name('orders.store');
        Route::get('orders/{number}', [OrderApiController::class, 'show'])->name('orders.show');
        Route::post('orders/{number}/cancel', [OrderApiController::class, 'cancel'])->name('orders.cancel');
    });
});

// Notifikasi pembayaran Midtrans (tanpa CSRF/sesi; keaslian dijamin signature SHA-512).
Route::post('api/webhooks/midtrans', MidtransWebhookController::class)->middleware('api')->name('api.webhooks.midtrans');
