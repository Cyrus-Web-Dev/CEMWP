<?php

namespace App\Modules\Crm;

use App\Modules\Crm\Listeners\UpdateCustomerPurchaseStats;
use App\Modules\Sales\Events\OrderPaid;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Modul CRM (bagian awal): identitas pelanggan + profil perilaku belanja.
 * Segmentasi, riwayat interaksi, dan halaman admin pelanggan menyusul di Project 3.
 */
class CrmServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Event::listen(OrderPaid::class, UpdateCustomerPurchaseStats::class);
    }
}
