<?php

namespace App\Modules\Sales\Listeners;

use App\Modules\Sales\Services\OrderService;
use App\Modules\Warehouse\Events\FulfillmentStatusChanged;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Status pengiriman dari gudang -> status pesanan (Dikirim / Selesai / Perlu ditinjau). Hanya untuk tugas milik pesanan. */
class SyncOrderFromFulfillment
{
    public function __construct(private OrderService $orders)
    {
    }

    public function handle(FulfillmentStatusChanged $event): void
    {
        if ($event->sourceType !== 'order' || ! $event->sourceId) {
            return;
        }

        try {
            $this->orders->syncFromFulfillment($event->sourceId, $event->to, $event->courier, $event->trackingNumber);
        } catch (Throwable $e) {
            Log::error("[Sales] Gagal sinkron status pesanan #{$event->sourceId} dari gudang: ".$e->getMessage());
        }
    }
}
