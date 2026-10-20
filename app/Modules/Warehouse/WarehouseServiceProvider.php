<?php

namespace App\Modules\Warehouse;

use App\Modules\Warehouse\Console\ExpireReservations;
use App\Modules\Warehouse\Console\VerifyStock;
use App\Modules\Warehouse\Contracts\FulfillmentGateway;
use App\Modules\Warehouse\Contracts\StockGateway;
use App\Modules\Warehouse\Services\FulfillmentService;
use App\Modules\Warehouse\Services\StockService;
use Illuminate\Support\ServiceProvider;

/**
 * Modul Warehouse. Modul lain bergantung pada KONTRAK (StockGateway, FulfillmentGateway),
 * bukan pada kelas konkret — supaya modul ini bisa dipisah menjadi layanan sendiri kelak tanpa mengubah pemanggilnya.
 */
class WarehouseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StockService::class);
        $this->app->bind(StockGateway::class, StockService::class);
        $this->app->bind(FulfillmentGateway::class, FulfillmentService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([VerifyStock::class, ExpireReservations::class]);
        }
    }
}
