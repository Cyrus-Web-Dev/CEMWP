<?php

namespace App\Modules\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Pembayaran masuk tetapi stok tidak tersedia / gudang tidak bisa memenuhi: perlu keputusan manusia (kirim ulang / refund). */
class OrderNeedsReview
{
    use Dispatchable;

    public function __construct(public readonly int $orderId, public readonly string $reason)
    {
    }
}
