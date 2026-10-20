<?php

return [
    App\Providers\AppServiceProvider::class,
    // Modul domain (modular monolith). Tiap modul mendaftarkan rute, migrasi, dan event-nya sendiri.
    App\Modules\Warehouse\WarehouseServiceProvider::class,
    App\Modules\Crm\CrmServiceProvider::class,
    App\Modules\Sales\SalesServiceProvider::class,
];
