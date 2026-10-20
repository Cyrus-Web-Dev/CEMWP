<?php

namespace App\Modules\Warehouse\Console;

use App\Modules\Warehouse\Services\StockService;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature = 'cemwp:expire-reservations';

    protected $description = 'Lepaskan reservasi stok yang melewati batas waktu pembayaran (dijadwalkan tiap menit)';

    public function handle(StockService $stock): int
    {
        $n = $stock->expireReservations();
        $this->info("{$n} reservasi dilepas.");

        return self::SUCCESS;
    }
}
