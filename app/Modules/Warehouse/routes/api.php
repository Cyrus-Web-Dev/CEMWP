<?php

use App\Modules\Warehouse\Http\Controllers\Api\CatalogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 modul Warehouse (katalog & ketersediaan untuk aplikasi mobile / integrasi)
| Katalog bersifat PUBLIK (sama seperti etalase toko online) dan dibatasi laju. Token tidak diperlukan.
|--------------------------------------------------------------------------
*/
Route::prefix('api/v1/catalog')->middleware(['api', 'throttle:api'])->name('api.catalog.')->group(function () {
    Route::get('products', [CatalogController::class, 'products'])->name('products');
    Route::get('availability', [CatalogController::class, 'availability'])->name('availability');
});
