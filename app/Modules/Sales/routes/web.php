<?php

use App\Modules\Sales\Http\Controllers\CouponController;
use App\Modules\Sales\Http\Controllers\OrderController;
use App\Modules\Sales\Http\Controllers\PosController;
use App\Modules\Sales\Http\Controllers\ReportController;
use App\Modules\Sales\Http\Controllers\SettingsController;
use App\Modules\Sales\Http\Controllers\ShiftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modul Sales — back-office & kasir (login + role wajib). Nama rute: sales.*
|--------------------------------------------------------------------------
*/
Route::middleware(['web', 'auth', 'has.role'])->prefix('sales')->name('sales.')->group(function () {

    // Kasir (POS)
    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('pos/catalog', [PosController::class, 'catalog'])->name('pos.catalog');
    Route::post('pos/customers/lookup', [PosController::class, 'lookupCustomer'])->name('pos.customers.lookup');
    Route::post('pos/quote', [PosController::class, 'quote'])->name('pos.quote');
    Route::post('pos/sales', [PosController::class, 'store'])->name('pos.store');
    Route::get('pos/receipt/{order}', [PosController::class, 'receipt'])->name('pos.receipt');

    // Shift kasir
    Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::post('shifts/open', [ShiftController::class, 'open'])->name('shifts.open');
    Route::get('shifts/{shift}', [ShiftController::class, 'show'])->name('shifts.show');
    Route::post('shifts/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');

    // Pesanan (POS + online)
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/confirm-payment', [OrderController::class, 'confirmPayment'])->name('orders.confirm-payment');
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('orders/{order}/refunded', [OrderController::class, 'markRefunded'])->name('orders.refunded');
    Route::post('orders/{order}/returns', [OrderController::class, 'storeReturn'])->name('orders.returns');

    Route::resource('coupons', CouponController::class)->only(['index', 'store', 'update', 'destroy']);

    // Pengaturan metode pembayaran & pengiriman
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('settings/payment-methods', [SettingsController::class, 'storePayment'])->name('settings.payment.store');
    Route::put('settings/payment-methods/{paymentMethod}', [SettingsController::class, 'updatePayment'])->name('settings.payment.update');
    Route::delete('settings/payment-methods/{paymentMethod}', [SettingsController::class, 'destroyPayment'])->name('settings.payment.destroy');
    Route::post('settings/shipping-methods', [SettingsController::class, 'storeShipping'])->name('settings.shipping.store');
    Route::put('settings/shipping-methods/{shippingMethod}', [SettingsController::class, 'updateShipping'])->name('settings.shipping.update');
    Route::delete('settings/shipping-methods/{shippingMethod}', [SettingsController::class, 'destroyShipping'])->name('settings.shipping.destroy');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
});
