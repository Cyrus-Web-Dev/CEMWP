<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Warehouse\Exceptions\StockException;
use App\Modules\Warehouse\Models\GoodsReceipt;
use App\Modules\Warehouse\Models\PurchaseOrder;
use App\Modules\Warehouse\Models\StockMovement;
use App\Support\DocumentNumber;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

/** Pembelian ke supplier & penerimaan barang (barang masuk gudang). */
class PurchasingService
{
    public function __construct(private StockService $stock)
    {
    }

    /**
     * Buat / ubah PO (hanya selagi draf).
     *
     * @param  array{supplier_id:int,warehouse_id:int,expected_at?:?string,notes?:?string,items:array<int,array{product_id:int,quantity:int,unit_cost:float|int|string}>}  $data
     */
    public function save(?PurchaseOrder $po, array $data, ?int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $data, $userId) {
            $lines = $this->mergeLines($data['items'], 'qty_ordered');

            if ($po) {
                $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);
                if ($po->status !== PurchaseOrder::DRAFT) {
                    throw new StockException('Hanya PO berstatus draf yang bisa diubah.');
                }
                $po->update([
                    'supplier_id' => $data['supplier_id'], 'warehouse_id' => $data['warehouse_id'],
                    'expected_at' => $data['expected_at'] ?? null, 'notes' => $data['notes'] ?? null,
                ]);
                $po->items()->delete();
                $action = 'po.updated';
            } else {
                $po = PurchaseOrder::create([
                    'number' => DocumentNumber::next('PO'),
                    'supplier_id' => $data['supplier_id'], 'warehouse_id' => $data['warehouse_id'],
                    'status' => PurchaseOrder::DRAFT,
                    'expected_at' => $data['expected_at'] ?? null, 'notes' => $data['notes'] ?? null,
                    'created_by' => $userId,
                ]);
                $action = 'po.created';
            }

            foreach ($lines as $productId => $line) {
                $po->items()->create(['product_id' => $productId, 'qty_ordered' => $line['qty_ordered'], 'unit_cost' => $line['unit_cost']]);
            }

            ActivityLogger::log($action, "PO {$po->number} disimpan (".count($lines).' item)', $po, $userId);

            return $po;
        });
    }

    public function place(PurchaseOrder $po, ?int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $userId) {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);
            if ($po->status !== PurchaseOrder::DRAFT) {
                throw new StockException('PO ini sudah diproses sebelumnya.');
            }
            if (! $po->items()->exists()) {
                throw new StockException('PO belum punya item.');
            }
            $po->update(['status' => PurchaseOrder::ORDERED, 'ordered_at' => now()->toDateString()]);
            ActivityLogger::log('po.placed', "PO {$po->number} dikirim ke supplier", $po, $userId);

            return $po;
        });
    }

    public function cancel(PurchaseOrder $po, ?int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $userId) {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);
            if (! in_array($po->status, [PurchaseOrder::DRAFT, PurchaseOrder::ORDERED], true)) {
                throw new StockException('PO yang sudah diterima (sebagian/seluruhnya) tidak bisa dibatalkan. Gunakan "Tutup PO".');
            }
            $po->update(['status' => PurchaseOrder::CANCELLED]);
            ActivityLogger::log('po.cancelled', "PO {$po->number} dibatalkan", $po, $userId);

            return $po;
        });
    }

    /** Tutup PO yang diterima sebagian (sisa barang tidak akan datang). */
    public function close(PurchaseOrder $po, ?int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $userId) {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);
            if ($po->status !== PurchaseOrder::PARTIAL) {
                throw new StockException('Hanya PO yang diterima sebagian yang bisa ditutup.');
            }
            $po->update(['status' => PurchaseOrder::RECEIVED]);
            ActivityLogger::log('po.closed', "PO {$po->number} ditutup (sisa tidak diterima)", $po, $userId);

            return $po;
        });
    }

    /**
     * Terima barang untuk sebuah PO.
     *
     * @param  array<int,int>  $quantities  [purchase_order_item_id => jumlah diterima sekarang]
     * @param  string|null  $requestKey  token unik dari form; klik ganda dengan token sama tidak menggandakan stok
     */
    public function receive(PurchaseOrder $po, array $quantities, ?string $notes, ?int $userId, ?string $requestKey = null): GoodsReceipt
    {
        return DB::transaction(function () use ($po, $quantities, $notes, $userId, $requestKey) {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);

            if ($replay = $this->replayedReceipt($requestKey)) {
                return $replay;
            }
            if (! $po->isReceivable()) {
                throw new StockException('PO ini tidak sedang menunggu penerimaan barang.');
            }

            $items = $po->items()->orderBy('product_id')->lockForUpdate()->get();
            $toReceive = [];
            foreach ($items as $item) {
                $qty = (int) ($quantities[$item->id] ?? 0);
                if ($qty < 0) {
                    throw new StockException('Jumlah diterima tidak boleh negatif.');
                }
                if ($qty === 0) {
                    continue;
                }
                if ($qty > $item->qty_outstanding) {
                    throw new StockException("Jumlah diterima melebihi sisa pesanan (maks. {$item->qty_outstanding}). Ubah PO bila supplier mengirim lebih.");
                }
                $toReceive[] = [$item, $qty];
            }
            if ($toReceive === []) {
                throw new StockException('Isi jumlah diterima minimal untuk satu item.');
            }

            $receipt = GoodsReceipt::create([
                'number' => DocumentNumber::next('GR'),
                'purchase_order_id' => $po->id, 'warehouse_id' => $po->warehouse_id, 'supplier_id' => $po->supplier_id,
                'received_at' => now(), 'notes' => $notes, 'received_by' => $userId,
            ]);

            foreach ($toReceive as [$item, $qty]) {
                $receipt->items()->create(['product_id' => $item->product_id, 'quantity' => $qty, 'unit_cost' => $item->unit_cost]);
                $this->stock->receive($item->product_id, $po->warehouse_id, $qty, (float) $item->unit_cost, [
                    'reference' => $receipt, 'reference_number' => $receipt->number, 'user_id' => $userId,
                    'note' => "Penerimaan PO {$po->number}",
                    'idempotency_key' => $requestKey ? "grn:{$requestKey}:{$item->product_id}" : null,
                ]);
                $item->qty_received += $qty;
                $item->save();
            }

            $complete = $po->items()->whereColumn('qty_received', '<', 'qty_ordered')->doesntExist();
            $po->update(['status' => $complete ? PurchaseOrder::RECEIVED : PurchaseOrder::PARTIAL]);

            ActivityLogger::log('goods.received', "Penerimaan barang {$receipt->number} untuk PO {$po->number}", $receipt, $userId);

            return $receipt;
        });
    }

    /**
     * Penerimaan langsung tanpa PO (mis. barang titipan / pembelian tunai di pasar).
     *
     * @param  array{warehouse_id:int,supplier_id?:?int,notes?:?string,items:array<int,array{product_id:int,quantity:int,unit_cost:float|int|string}>}  $data
     */
    public function receiveDirect(array $data, ?int $userId, ?string $requestKey = null): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $userId, $requestKey) {
            if ($replay = $this->replayedReceipt($requestKey)) {
                return $replay;
            }

            $lines = $this->mergeLines($data['items'], 'quantity');

            $receipt = GoodsReceipt::create([
                'number' => DocumentNumber::next('GR'),
                'warehouse_id' => $data['warehouse_id'], 'supplier_id' => $data['supplier_id'] ?? null,
                'received_at' => now(), 'notes' => $data['notes'] ?? null, 'received_by' => $userId,
            ]);

            foreach ($lines as $productId => $line) {
                $receipt->items()->create(['product_id' => $productId, 'quantity' => $line['quantity'], 'unit_cost' => $line['unit_cost']]);
                $this->stock->receive($productId, (int) $data['warehouse_id'], $line['quantity'], (float) $line['unit_cost'], [
                    'reference' => $receipt, 'reference_number' => $receipt->number, 'user_id' => $userId,
                    'note' => 'Penerimaan langsung (tanpa PO)',
                    'idempotency_key' => $requestKey ? "grn:{$requestKey}:{$productId}" : null,
                ]);
            }

            ActivityLogger::log('goods.received', "Penerimaan langsung {$receipt->number}", $receipt, $userId);

            return $receipt;
        });
    }

    /** Bila token form ini sudah pernah diproses, kembalikan penerimaan yang dulu dibuat. */
    private function replayedReceipt(?string $requestKey): ?GoodsReceipt
    {
        if (! $requestKey) {
            return null;
        }
        $movement = StockMovement::query()
            ->where('idempotency_key', 'like', "grn:{$requestKey}:%")
            ->orderBy('id')
            ->first();

        return $movement && $movement->reference_id ? GoodsReceipt::query()->find($movement->reference_id) : null;
    }

    /**
     * Gabungkan baris produk kembar dan urutkan berdasarkan product_id (urutan kunci konsisten).
     *
     * @return array<int,array<string,int|float|string>>
     */
    private function mergeLines(array $items, string $qtyKey): array
    {
        $lines = [];
        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $qty = (int) $item['quantity'];
            if ($qty <= 0) {
                throw new StockException('Jumlah setiap item harus lebih dari nol.');
            }
            $lines[$productId][$qtyKey] = ($lines[$productId][$qtyKey] ?? 0) + $qty;
            $lines[$productId]['unit_cost'] = (float) ($item['unit_cost'] ?? 0);
        }
        if ($lines === []) {
            throw new StockException('Tambahkan minimal satu item.');
        }
        ksort($lines);

        return $lines;
    }
}
