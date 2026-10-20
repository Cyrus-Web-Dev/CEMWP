<?php

namespace App\Modules\Sales;

use App\Modules\Sales\Console\ExpireUnpaidOrders;
use App\Modules\Sales\Http\Middleware\EnsureCustomerToken;
use App\Modules\Sales\Listeners\SyncOrderFromFulfillment;
use App\Modules\Sales\Payments\MidtransGateway;
use App\Modules\Warehouse\Events\FulfillmentStatusChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Modul Sales: POS, etalase online, pesanan, pembayaran, kupon, retur, laporan.
 * Bergantung pada modul lain HANYA lewat kontrak: StockGateway, FulfillmentGateway (Warehouse), CustomerResolver (CRM).
 */
class SalesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MidtransGateway::class);
    }

    public function boot(): void
    {
        Route::aliasMiddleware('customer.token', EnsureCustomerToken::class);

        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/routes/shop.php');
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');

        // Alur 2 -> 1: gudang mengirim / menyelesaikan tugas -> status pesanan ikut berubah.
        Event::listen(FulfillmentStatusChanged::class, SyncOrderFromFulfillment::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireUnpaidOrders::class]);
        }
    }
}
