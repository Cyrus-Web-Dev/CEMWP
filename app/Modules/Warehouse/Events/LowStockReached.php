<?php

namespace App\Modules\Warehouse\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Stok sebuah produk (di satu lokasi) baru saja turun sampai/di bawah titik pesan ulang. */
class LowStockReached
{
    use Dispatchable;

    public function __construct(
        public readonly int $productId,
        public readonly int $warehouseId,
        public readonly int $onHand,
        public readonly int $minStock,
    ) {
    }
}
