<?php

namespace App\Modules\Warehouse\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Dikirim SETELAH transaksi database commit. Modul lain (mis. e-commerce) bisa mendengarkan untuk menyegarkan ketersediaan. */
class StockChanged
{
    use Dispatchable;

    public function __construct(public readonly int $movementId)
    {
    }
}
