<?php

namespace App\Modules\Warehouse\Events;

use Illuminate\Foundation\Events\Dispatchable;

class FulfillmentCreated
{
    use Dispatchable;

    public function __construct(public readonly int $fulfillmentId)
    {
    }
}
