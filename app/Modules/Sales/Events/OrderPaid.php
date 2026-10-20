<?php

namespace App\Modules\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pembayaran pesanan lunas (POS: saat bayar; online: saat dana diterima; COD: saat barang diterima & dibayar).
 * Berisi data primitif supaya CRM / Marketing tidak perlu membaca tabel modul Sales.
 * Dikirim setelah commit.
 */
class OrderPaid
{
    use Dispatchable;

    /** @param array<int,array{product_id:int,quantity:int}> $items */
    public function __construct(
        public readonly int $orderId,
        public readonly ?int $customerId,
        public readonly string $channel,
        public readonly float $total,
        public readonly string $paidAt,
        public readonly array $items,
    ) {
    }
}
