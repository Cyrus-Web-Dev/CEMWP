<?php

use App\Modules\Warehouse\Http\Controllers\CategoryController;
use App\Modules\Warehouse\Http\Controllers\FulfillmentController;
use App\Modules\Warehouse\Http\Controllers\InventoryController;
use App\Modules\Warehouse\Http\Controllers\LocationController;
use App\Modules\Warehouse\Http\Controllers\OpnameController;
use App\Modules\Warehouse\Http\Controllers\ProductController;
use App\Modules\Warehouse\Http\Controllers\PurchaseOrderController;
use App\Modules\Warehouse\Http\Controllers\ReceiptController;
use App\Modules\Warehouse\Http\Controllers\SupplierController;
use App\Modules\Warehouse\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modul Warehouse — halaman internal (login + role wajib). Nama rute: warehouse.*
| Otorisasi per-permission dipasang di constructor tiap controller (Spatie Permission).
|--------------------------------------------------------------------------
*/
Route::middleware(['web', 'auth', 'has.role'])->prefix('warehouse')->name('warehouse.')->group(function () {

    // Produk (rute "search" HARUS sebelum resource agar tidak tertangkap {product})
    Route::get('products/search', [ProductController::class, 'search'])->name('products.search');
    Route::resource('products', ProductController::class);

    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('locations', LocationController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['locations' => 'warehouse']);
    Route::resource('suppliers', SupplierController::class)->only(['index', 'store', 'update', 'destroy']);

    // Stok
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
    Route::get('inventory/export', [InventoryController::class, 'export'])->name('inventory.export');
    Route::post('inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');

    // Pembelian & penerimaan barang
    Route::resource('purchase-orders', PurchaseOrderController::class)->except(['destroy'])->parameters(['purchase-orders' => 'purchaseOrder']);
    Route::post('purchase-orders/{purchaseOrder}/place', [PurchaseOrderController::class, 'place'])->name('purchase-orders.place');
    Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
    Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::post('purchase-orders/{purchaseOrder}/close', [PurchaseOrderController::class, 'close'])->name('purchase-orders.close');
    Route::resource('receipts', ReceiptController::class)->only(['index', 'create', 'store', 'show']);

    // Transfer antar lokasi
    Route::resource('transfers', TransferController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('transfers/{transfer}/ship', [TransferController::class, 'ship'])->name('transfers.ship');
    Route::post('transfers/{transfer}/receive', [TransferController::class, 'receive'])->name('transfers.receive');
    Route::post('transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');

    // Stok opname
    Route::resource('opnames', OpnameController::class)->only(['index', 'store', 'show']);
    Route::put('opnames/{opname}/counts', [OpnameController::class, 'counts'])->name('opnames.counts');
    Route::post('opnames/{opname}/finalize', [OpnameController::class, 'finalize'])->name('opnames.finalize');
    Route::post('opnames/{opname}/cancel', [OpnameController::class, 'cancel'])->name('opnames.cancel');

    // Pengiriman pesanan (pick -> pack -> ship -> deliver)
    Route::get('fulfillments', [FulfillmentController::class, 'index'])->name('fulfillments.index');
    Route::get('fulfillments/{fulfillment}', [FulfillmentController::class, 'show'])->name('fulfillments.show');
    Route::post('fulfillments/{fulfillment}/start', [FulfillmentController::class, 'start'])->name('fulfillments.start');
    Route::put('fulfillments/{fulfillment}/picked', [FulfillmentController::class, 'picked'])->name('fulfillments.picked');
    Route::post('fulfillments/{fulfillment}/pack', [FulfillmentController::class, 'pack'])->name('fulfillments.pack');
    Route::post('fulfillments/{fulfillment}/ship', [FulfillmentController::class, 'ship'])->name('fulfillments.ship');
    Route::post('fulfillments/{fulfillment}/deliver', [FulfillmentController::class, 'deliver'])->name('fulfillments.deliver');
    Route::post('fulfillments/{fulfillment}/cancel', [FulfillmentController::class, 'cancel'])->name('fulfillments.cancel');
});
