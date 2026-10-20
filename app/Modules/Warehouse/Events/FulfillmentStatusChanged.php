<?php

namespace App\Modules\Warehouse\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dikirim setiap status pengiriman berubah. Modul CRM mendengarkan event ini (alur ke-3 "The Connected Loop")
 * untuk mencatat status pengiriman ke profil pelanggan.
 */
class FulfillmentStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $fulfillmentId,
        public readonly ?string $sourceType,
        public readonly ?int $sourceId,
        public readonly string $from,
        public readonly string $to,
        public readonly ?string $courier = null,
        public readonly ?string $trackingNumber = null,
    ) {
    }
}
