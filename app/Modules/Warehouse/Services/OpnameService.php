<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Warehouse\Exceptions\StockException;
use App\Modules\Warehouse\Models\Inventory;
use App\Modules\Warehouse\Models\StockOpname;
use App\Support\DocumentNumber;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

/** Stok opname: hitung fisik, lalu koreksi stok sistem lewat ledger. */
class OpnameService
{
    public function __construct(private StockService $stock)
    {
    }

    /** Mulai opname: ambil foto stok semua produk (terlacak) yang tercatat di lokasi tersebut. */
    public function start(int $warehouseId, ?string $notes, ?int $userId): StockOpname
    {
        return DB::transaction(function () use ($warehouseId, $notes, $userId) {
            $rows = Inventory::query()
                ->where('warehouse_id', $warehouseId)
                ->whereHas('product', fn ($q) => $q->where('track_stock', true))
                ->orderBy('product_id')
                ->get(['product_id', 'qty_on_hand']);

            if ($rows->isEmpty()) {
                throw new StockException('Belum ada stok tercatat di lokasi ini, jadi tidak ada yang bisa dihitung.');
            }

            $opname = StockOpname::create([
                'number' => DocumentNumber::next('OPN'),
                'warehouse_id' => $warehouseId, 'status' => StockOpname::DRAFT, 'notes' => $notes, 'created_by' => $userId,
            ]);
            foreach ($rows as $row) {
                $opname->items()->create(['product_id' => $row->product_id, 'system_qty' => $row->qty_on_hand]);
            }
            ActivityLogger::log('opname.started', "Stok opname {$opname->number} dimulai ({$rows->count()} produk)", $opname, $userId);

            return $opname;
        });
    }

    /**
     * Simpan hasil hitung (boleh bertahap). Nilai kosong/null = belum dihitung.
     *
     * @param  array<int,int|string|null>  $counts  [stock_opname_item_id => hasil hitung]
     */
    public function saveCounts(StockOpname $opname, array $counts): void
    {
        DB::transaction(function () use ($opname, $counts) {
            $opname = StockOpname::query()->lockForUpdate()->findOrFail($opname->id);
            if ($opname->status !== StockOpname::DRAFT) {
                throw new StockException('Opname ini sudah final atau dibatalkan.');
            }

            $items = $opname->items()->get()->keyBy('id');
            foreach ($counts as $itemId => $value) {
                $item = $items->get((int) $itemId);
                if (! $item) {
                    continue;
                }
                if ($value === null || $value === '') {
                    $item->update(['counted_qty' => null]);

                    continue;
                }
                if (! is_numeric($value) || (int) $value < 0 || (int) $value != $value) {
                    throw new StockException('Hasil hitung harus bilangan bulat nol atau lebih.');
                }
                $item->update(['counted_qty' => (int) $value]);
            }
        });
    }

    /**
     * Finalisasi: stok sistem disamakan dengan hasil hitung (hanya baris yang sudah dihitung).
     * Selisih dihitung terhadap stok saat ini; yang bergerak selama penghitungan ikut tertimpa — finalisasi segera.
     */
    public function finalize(StockOpname $opname, ?int $userId): StockOpname
    {
        return DB::transaction(function () use ($opname, $userId) {
            $opname = StockOpname::query()->lockForUpdate()->findOrFail($opname->id);
            if ($opname->status !== StockOpname::DRAFT) {
                throw new StockException('Opname ini sudah final atau dibatalkan.');
            }

            $counted = $opname->items()->whereNotNull('counted_qty')->orderBy('product_id')->get();
            if ($counted->isEmpty()) {
                throw new StockException('Belum ada produk yang dihitung.');
            }

            foreach ($counted as $item) {
                $movement = $this->stock->setCount($item->product_id, $opname->warehouse_id, $item->counted_qty, [
                    'reference' => $opname, 'reference_number' => $opname->number, 'user_id' => $userId,
                    'reason' => 'opname', 'note' => "Hasil hitung fisik {$opname->number}",
                ]);
                $systemNow = $movement ? $movement->qty_before : $item->counted_qty;
                $item->update(['system_qty' => $systemNow, 'difference' => $item->counted_qty - $systemNow]);
            }

            $opname->update(['status' => StockOpname::FINALIZED, 'finalized_by' => $userId, 'finalized_at' => now()]);
            ActivityLogger::log('opname.finalized', "Stok opname {$opname->number} difinalisasi", $opname, $userId);

            return $opname;
        });
    }

    public function cancel(StockOpname $opname, ?int $userId): StockOpname
    {
        return DB::transaction(function () use ($opname, $userId) {
            $opname = StockOpname::query()->lockForUpdate()->findOrFail($opname->id);
            if ($opname->status !== StockOpname::DRAFT) {
                throw new StockException('Hanya opname yang sedang berjalan yang bisa dibatalkan.');
            }
            $opname->update(['status' => StockOpname::CANCELLED]);
            ActivityLogger::log('opname.cancelled', "Stok opname {$opname->number} dibatalkan", $opname, $userId);

            return $opname;
        });
    }
}
