<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Warehouse\Contracts\FulfillmentGateway;
use App\Modules\Warehouse\Enums\FulfillmentStatus;
use App\Modules\Warehouse\Events\FulfillmentCreated;
use App\Modules\Warehouse\Events\FulfillmentStatusChanged;
use App\Modules\Warehouse\Exceptions\StockException;
use App\Modules\Warehouse\Models\Fulfillment;
use App\Modules\Warehouse\Models\Product;
use App\Support\DocumentNumber;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Tugas gudang untuk satu pesanan: ambil (pick) -> kemas (pack) -> kirim (ship) -> diterima (deliver).
 * Modul ini TIDAK mengubah stok: stok sudah dipotong/dicadangkan oleh Sales lewat StockGateway.
 */
class FulfillmentService implements FulfillmentGateway
{
    public function createFromOrder(array $data): Fulfillment
    {
        $sourceType = $data['source_type'] ?? 'order';
        $sourceId = isset($data['source_id']) ? (int) $data['source_id'] : null;

        return DB::transaction(function () use ($data, $sourceType, $sourceId) {
            // Idempoten: pesanan yang sama tidak membuat tugas gudang kedua.
            if ($sourceId !== null) {
                $existing = Fulfillment::query()->where('source_type', $sourceType)->where('source_id', $sourceId)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $quantities = [];
            foreach ($data['items'] ?? [] as $item) {
                $qty = (int) ($item['quantity'] ?? 0);
                $productId = (int) ($item['product_id'] ?? 0);
                if ($productId <= 0 || $qty <= 0) {
                    throw new StockException('Item pesanan tidak valid.');
                }
                $quantities[$productId] = ($quantities[$productId] ?? 0) + $qty;
            }
            if ($quantities === []) {
                throw new StockException('Pesanan tidak memiliki item.');
            }
            ksort($quantities);

            $products = Product::withTrashed()->whereIn('id', array_keys($quantities))->get()->keyBy('id');

            $fulfillment = Fulfillment::create([
                'number' => DocumentNumber::next('FUL'),
                'source_type' => $sourceType, 'source_id' => $sourceId, 'source_number' => $data['source_number'] ?? null,
                'channel' => $data['channel'] ?? 'ecommerce',
                'warehouse_id' => $data['warehouse_id'],
                'status' => FulfillmentStatus::Pending,
                'priority' => $data['priority'] ?? 'normal',
                'recipient_name' => $data['recipient_name'] ?? null, 'recipient_phone' => $data['recipient_phone'] ?? null,
                'shipping_address' => $data['shipping_address'] ?? null, 'notes' => $data['notes'] ?? null,
            ]);

            foreach ($quantities as $productId => $qty) {
                $product = $products->get($productId);
                if (! $product) {
                    throw new StockException("Produk #{$productId} tidak ditemukan.");
                }
                $fulfillment->items()->create([
                    'product_id' => $productId, 'sku' => $product->sku, 'product_name' => $product->name, 'quantity' => $qty,
                ]);
            }

            $id = $fulfillment->id;
            DB::afterCommit(fn () => event(new FulfillmentCreated($id)));
            ActivityLogger::log('fulfillment.created', "Tugas gudang {$fulfillment->number} dibuat", $fulfillment);

            return $fulfillment;
        });
    }

    public function cancelBySource(string $sourceType, int $sourceId, string $reason): ?Fulfillment
    {
        $fulfillment = Fulfillment::query()->where('source_type', $sourceType)->where('source_id', $sourceId)->first();
        if (! $fulfillment || $fulfillment->status === FulfillmentStatus::Cancelled) {
            return $fulfillment;
        }
        if (! $fulfillment->status->canMoveTo(FulfillmentStatus::Cancelled)) {
            throw new StockException('Pesanan sudah dikirim, jadi tugas gudang tidak bisa dibatalkan.');
        }

        return $this->cancel($fulfillment, $reason);
    }

    public function start(Fulfillment $fulfillment, ?int $userId): Fulfillment
    {
        return $this->transition($fulfillment, FulfillmentStatus::Picking, function (Fulfillment $f) use ($userId) {
            $f->assigned_to = $userId;
        });
    }

    /** @param array<int,int|string> $picked [fulfillment_item_id => jumlah sudah diambil] */
    public function savePicked(Fulfillment $fulfillment, array $picked): Fulfillment
    {
        return DB::transaction(function () use ($fulfillment, $picked) {
            $fulfillment = Fulfillment::query()->lockForUpdate()->findOrFail($fulfillment->id);
            if ($fulfillment->status !== FulfillmentStatus::Picking) {
                throw new StockException('Jumlah diambil hanya bisa diisi saat status "Sedang diambil".');
            }
            $items = $fulfillment->items()->get()->keyBy('id');
            foreach ($picked as $itemId => $qty) {
                $item = $items->get((int) $itemId);
                if (! $item) {
                    continue;
                }
                if (! is_numeric($qty) || (int) $qty < 0 || (int) $qty > $item->quantity) {
                    throw new StockException("Jumlah diambil untuk {$item->product_name} harus antara 0 dan {$item->quantity}.");
                }
                $item->update(['qty_picked' => (int) $qty]);
            }

            return $fulfillment;
        });
    }

    public function pack(Fulfillment $fulfillment): Fulfillment
    {
        return $this->transition($fulfillment, FulfillmentStatus::Packed, function (Fulfillment $f) {
            if ($f->items()->whereColumn('qty_picked', '<', 'quantity')->exists()) {
                throw new StockException('Semua item harus sudah diambil penuh sebelum dikemas. Jika barang kurang, batalkan dan laporkan.');
            }
            $f->picked_at = now();
            $f->packed_at = now();
        });
    }

    public function ship(Fulfillment $fulfillment, string $courier, string $trackingNumber): Fulfillment
    {
        return $this->transition($fulfillment, FulfillmentStatus::Shipped, function (Fulfillment $f) use ($courier, $trackingNumber) {
            $f->courier = $courier;
            $f->tracking_number = $trackingNumber;
            $f->shipped_at = now();
        });
    }

    public function deliver(Fulfillment $fulfillment): Fulfillment
    {
        return $this->transition($fulfillment, FulfillmentStatus::Delivered, function (Fulfillment $f) {
            $f->delivered_at = now();
        });
    }

    public function cancel(Fulfillment $fulfillment, string $reason): Fulfillment
    {
        return $this->transition($fulfillment, FulfillmentStatus::Cancelled, function (Fulfillment $f) use ($reason) {
            $f->cancelled_at = now();
            $f->cancel_reason = $reason;
        });
    }

    /** Pindah status dengan baris terkunci; urutan yang tidak sah ditolak. */
    private function transition(Fulfillment $fulfillment, FulfillmentStatus $to, callable $mutate): Fulfillment
    {
        return DB::transaction(function () use ($fulfillment, $to, $mutate) {
            $fulfillment = Fulfillment::query()->lockForUpdate()->findOrFail($fulfillment->id);
            $from = $fulfillment->status;

            if (! $from->canMoveTo($to)) {
                throw new StockException("Status tidak bisa berubah dari \"{$from->label()}\" ke \"{$to->label()}\".");
            }

            $mutate($fulfillment);
            $fulfillment->status = $to;
            $fulfillment->save();

            $event = new FulfillmentStatusChanged(
                $fulfillment->id, $fulfillment->source_type, $fulfillment->source_id, $from->value, $to->value,
                $fulfillment->courier, $fulfillment->tracking_number
            );
            DB::afterCommit(fn () => event($event));
            ActivityLogger::log("fulfillment.{$to->value}", "Tugas gudang {$fulfillment->number}: {$from->label()} -> {$to->label()}", $fulfillment);

            return $fulfillment;
        });
    }
}
