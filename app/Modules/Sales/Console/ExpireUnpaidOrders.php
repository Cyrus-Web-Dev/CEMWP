<?php

namespace App\Modules\Sales\Console;

use App\Modules\Sales\Services\OrderService;
use Illuminate\Console\Command;

class ExpireUnpaidOrders extends Command
{
    protected $signature = 'cemwp:expire-orders';

    protected $description = 'Kedaluwarsakan pesanan online yang melewati batas waktu pembayaran (stok & kupon dilepas)';

    public function handle(OrderService $orders): int
    {
        $this->info($orders->expireOverdue().' pesanan dikedaluwarsakan.');

        return self::SUCCESS;
    }
}
