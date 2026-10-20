<?php

namespace App\Modules\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Pesanan baru tercatat (POS: sudah lunas; online: menunggu pembayaran / COD diterima). Dikirim setelah commit. */
class OrderPlaced
{
    use Dispatchable;

    public function __construct(public readonly int $orderId, public readonly string $channel)
    {
    }
}
