<?php

namespace App\Modules\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;

class OrderCancelled
{
    use Dispatchable;

    public function __construct(public readonly int $orderId, public readonly string $reason)
    {
    }
}
