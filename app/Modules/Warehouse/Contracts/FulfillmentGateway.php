<?php

namespace App\Modules\Warehouse\Contracts;

use App\Modules\Warehouse\Models\Fulfillment;

/**
 * Cara modul Sales "memicu tim gudang menyiapkan barang" (alur ke-2). Idempoten per (source_type, source_id).
 *
 * Kunci $data: source_type ('order'), source_id, source_number, channel ('ecommerce'|'pos'|'manual'),
 * warehouse_id, priority, recipient_name, recipient_phone, shipping_address, notes,
 * items => [['product_id' => 1, 'quantity' => 2], ...]
 */
interface FulfillmentGateway
{
    public function createFromOrder(array $data): Fulfillment;

    /**
     * Batalkan tugas gudang milik sebuah pesanan (pesanan dibatalkan sebelum dikirim). Mengembalikan null bila tidak ada.
     * Melempar StockException bila barang sudah dikirim. TIDAK mengubah stok (itu urusan StockGateway::restock).
     */
    public function cancelBySource(string $sourceType, int $sourceId, string $reason): ?Fulfillment;
}
