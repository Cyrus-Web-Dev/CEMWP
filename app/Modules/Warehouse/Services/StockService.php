<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Warehouse\Contracts\StockGateway;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Events\LowStockReached;
use App\Modules\Warehouse\Events\StockChanged;
use App\Modules\Warehouse\Exceptions\InsufficientStockException;
use App\Modules\Warehouse\Exceptions\StockException;
use App\Modules\Warehouse\Models\Inventory;
use App\Modules\Warehouse\Models\Product;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\StockReservation;
use App\Modules\Warehouse\Models\Warehouse;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu perubahan stok.
 *
 * Aturan keselamatan:
 *  1. Setiap perubahan dilakukan di dalam transaksi DAN dengan SELECT ... FOR UPDATE pada baris inventories,
 *     sehingga dua kasir / pembeli online tidak bisa menjual stok terakhir yang sama.
 *  2. Operasi multi-item mengunci baris berurutan (lokasi, lalu produk) supaya tidak terjadi deadlock.
 *  3. Setiap perubahan menulis satu baris ledger (stock_movements) — saldo bisa diaudit dan direkonsiliasi
 *     (php artisan cemwp:stock-verify).
 *  4. Idempotency: operasi yang diulang dengan key yang sama (retry jaringan) tidak menggandakan stok.
 *  5. Event dikirim SETELAH commit, jadi listener tidak pernah melihat data yang akhirnya di-rollback.
 */
class StockService implements StockGateway
{
    // ------------------------------------------------------------------ Pembacaan

    public function availability(int $productId, ?int $warehouseId = null): int
    {
        return $this->availabilityMap([$productId], $warehouseId)[$productId] ?? 0;
    }

    public function availabilityMap(array $productIds, ?int $warehouseId = null): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($productIds === []) {
            return [];
        }

        $query = Inventory::query()
            ->selectRaw('product_id, SUM(qty_on_hand - qty_reserved) AS available')
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        } else {
            $query->whereIn('warehouse_id', Warehouse::query()->where('is_active', true)->select('id'));
        }

        $map = array_fill_keys($productIds, 0);
        foreach ($query->get() as $row) {
            $map[(int) $row->product_id] = (int) $row->available;
        }

        return $map;
    }

    // ------------------------------------------------------------------ Primitif tunggal

    /**
     * Ubah stok satu produk di satu lokasi.
     *
     * @param  int  $delta  bertanda: + masuk, - keluar
     * @param  array{reference?:Model,reference_type?:string,reference_id?:int,reference_number?:string,reason?:string,note?:string,unit_cost?:float|string,user_id?:int,idempotency_key?:string}  $meta
     */
    public function move(int $productId, int $warehouseId, int $delta, MovementType $type, array $meta = []): StockMovement
    {
        if ($delta === 0) {
            throw new StockException('Jumlah perubahan stok tidak boleh nol.');
        }

        return DB::transaction(function () use ($productId, $warehouseId, $delta, $type, $meta) {
            $product = $this->trackedProduct($productId);
            $this->assertWarehouseUsable($warehouseId);

            $inventory = $this->lockInventory($productId, $warehouseId);

            // Pengecekan idempotency SETELAH kunci baris: permintaan kembar yang bersamaan akan antre di kunci
            // dan yang kedua melihat hasil commit yang pertama (tanpa melanggar unique index).
            if (! empty($meta['idempotency_key'])) {
                $existing = StockMovement::query()->where('idempotency_key', $meta['idempotency_key'])->first();
                if ($existing) {
                    return $existing;
                }
            }

            return $this->apply($inventory, $product, $delta, 0, $type, $meta);
        });
    }

    /** Barang masuk + perbarui harga pokok rata-rata bergerak (moving average). */
    public function receive(int $productId, int $warehouseId, int $quantity, float $unitCost, array $meta = []): StockMovement
    {
        if ($quantity <= 0) {
            throw new StockException('Jumlah penerimaan harus lebih dari nol.');
        }

        return DB::transaction(function () use ($productId, $warehouseId, $quantity, $unitCost, $meta) {
            // Kunci baris produk agar dua penerimaan bersamaan tidak saling menimpa harga pokok rata-rata.
            $product = Product::withTrashed()->lockForUpdate()->find($productId);
            if (! $product) {
                throw new StockException("Produk #{$productId} tidak ditemukan.");
            }
            $totalBefore = (int) Inventory::query()->where('product_id', $productId)->sum('qty_on_hand');

            $movement = $this->move($productId, $warehouseId, $quantity, MovementType::Receipt, $meta + ['unit_cost' => $unitCost]);

            // Jika movement ini hasil idempotency (sudah pernah diproses), jangan hitung ulang harga pokok.
            if ($unitCost > 0 && $movement->wasRecentlyCreated) {
                $newCost = ($totalBefore + $quantity) > 0
                    ? round((($totalBefore * (float) $product->cost_price) + ($quantity * $unitCost)) / ($totalBefore + $quantity), 2)
                    : $unitCost;
                $product->forceFill(['cost_price' => $newCost])->save();
            }

            return $movement;
        });
    }

    /** Koreksi manual (rusak, hilang, ditemukan, dll.) — $delta bertanda. */
    public function adjust(int $productId, int $warehouseId, int $delta, string $reason, ?string $note = null, ?int $userId = null): StockMovement
    {
        return $this->move($productId, $warehouseId, $delta, MovementType::Adjustment, [
            'reason' => $reason, 'note' => $note, 'user_id' => $userId,
        ]);
    }

    /**
     * Set stok ke angka hasil hitung fisik. Mengembalikan null bila sudah sama.
     * Selisih dihitung terhadap stok SAAT INI (terkunci), bukan terhadap angka lama di layar.
     */
    public function setCount(int $productId, int $warehouseId, int $counted, array $meta = []): ?StockMovement
    {
        if ($counted < 0) {
            throw new StockException('Hasil hitung tidak boleh negatif.');
        }

        return DB::transaction(function () use ($productId, $warehouseId, $counted, $meta) {
            $product = $this->trackedProduct($productId);
            $this->assertWarehouseUsable($warehouseId);
            $inventory = $this->lockInventory($productId, $warehouseId);

            $delta = $counted - $inventory->qty_on_hand;
            if ($delta === 0) {
                return null;
            }

            return $this->apply($inventory, $product, $delta, 0, MovementType::Opname, $meta);
        });
    }

    // ------------------------------------------------------------------ Penjualan langsung (POS)

    public function deductForSale(int $warehouseId, array $items, string $referenceNumber, ?string $idempotencyKey = null, ?int $userId = null): Collection
    {
        $items = $this->prepareItems($items);

        return DB::transaction(function () use ($warehouseId, $items, $referenceNumber, $idempotencyKey, $userId) {
            $out = new Collection();
            foreach ($items as $productId => $qty) {
                $out->push($this->move($productId, $warehouseId, -$qty, MovementType::Sale, [
                    'reference_number' => $referenceNumber,
                    'idempotency_key' => $idempotencyKey ? "{$idempotencyKey}:{$productId}" : null,
                    'user_id' => $userId,
                ]));
            }

            return $out;
        });
    }

    public function restock(int $warehouseId, array $items, string $referenceNumber, ?string $idempotencyKey = null, ?int $userId = null, ?string $reason = null): Collection
    {
        $items = $this->prepareItems($items);

        return DB::transaction(function () use ($warehouseId, $items, $referenceNumber, $idempotencyKey, $userId, $reason) {
            $out = new Collection();
            foreach ($items as $productId => $qty) {
                $out->push($this->move($productId, $warehouseId, $qty, MovementType::SaleReturn, [
                    'reference_number' => $referenceNumber,
                    'reason' => $reason,
                    'idempotency_key' => $idempotencyKey ? "{$idempotencyKey}:{$productId}" : null,
                    'user_id' => $userId,
                ]));
            }

            return $out;
        });
    }

    // ------------------------------------------------------------------ Reservasi (e-commerce)

    public function reserve(string $reference, int $warehouseId, array $items, ?CarbonInterface $expiresAt = null): Collection
    {
        $items = $this->prepareItems($items);
        $expiresAt ??= now()->addMinutes((int) config('cemwp.warehouse.reservation_ttl_minutes', 30));

        return DB::transaction(function () use ($reference, $warehouseId, $items, $expiresAt) {
            $this->assertWarehouseUsable($warehouseId);
            $out = new Collection();

            foreach ($items as $productId => $qty) {
                $inventory = $this->lockInventory($productId, $warehouseId);

                $existing = StockReservation::query()
                    ->where('reference', $reference)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                // Idempoten: reservasi aktif / yang sudah menjadi penjualan dikembalikan apa adanya.
                if ($existing && in_array($existing->status, [StockReservation::ACTIVE, StockReservation::COMMITTED], true)) {
                    $out->push($existing);

                    continue;
                }

                $available = $inventory->qty_on_hand - $inventory->qty_reserved;
                if ($available < $qty) {
                    throw InsufficientStockException::for($this->trackedProduct($productId), $warehouseId, $qty, max(0, $available));
                }

                $inventory->qty_reserved += $qty;
                $inventory->save();

                if ($existing) { // pernah dilepas / kedaluwarsa -> hidupkan lagi
                    $existing->fill(['quantity' => $qty, 'status' => StockReservation::ACTIVE, 'expires_at' => $expiresAt, 'resolved_at' => null])->save();
                    $out->push($existing);
                } else {
                    $out->push(StockReservation::create([
                        'product_id' => $productId, 'warehouse_id' => $warehouseId, 'reference' => $reference,
                        'quantity' => $qty, 'status' => StockReservation::ACTIVE, 'expires_at' => $expiresAt,
                    ]));
                }
            }

            return $out;
        });
    }

    public function commitReservation(string $reference, ?string $referenceNumber = null, ?int $userId = null): Collection
    {
        return DB::transaction(function () use ($reference, $referenceNumber, $userId) {
            $movements = new Collection();

            foreach ($this->activeReservationsFor($reference) as $row) {
                $inventory = $this->lockInventory($row->product_id, $row->warehouse_id);
                $reservation = StockReservation::query()->whereKey($row->id)->lockForUpdate()->first();
                if (! $reservation || $reservation->status !== StockReservation::ACTIVE) {
                    continue; // sudah diproses permintaan lain
                }

                $product = Product::withTrashed()->findOrFail($reservation->product_id);
                $movements->push($this->apply(
                    $inventory, $product, -$reservation->quantity, -$reservation->quantity, MovementType::Sale,
                    ['reference_number' => $referenceNumber ?? $reference, 'user_id' => $userId, 'note' => "Reservasi {$reference}"]
                ));

                $reservation->forceFill(['status' => StockReservation::COMMITTED, 'resolved_at' => now()])->save();
            }

            return $movements;
        });
    }

    public function releaseReservation(string $reference, string $status = StockReservation::RELEASED): int
    {
        return DB::transaction(function () use ($reference, $status) {
            $released = 0;

            foreach ($this->activeReservationsFor($reference) as $row) {
                $inventory = $this->lockInventory($row->product_id, $row->warehouse_id);
                $reservation = StockReservation::query()->whereKey($row->id)->lockForUpdate()->first();
                if (! $reservation || $reservation->status !== StockReservation::ACTIVE) {
                    continue;
                }

                $inventory->qty_reserved = max(0, $inventory->qty_reserved - $reservation->quantity);
                $inventory->save();
                $reservation->forceFill(['status' => $status, 'resolved_at' => now()])->save();
                $released++;
            }

            return $released;
        });
    }

    /** Dijalankan scheduler tiap menit: lepaskan reservasi yang melewati batas waktu pembayaran. */
    public function expireReservations(): int
    {
        $references = StockReservation::query()
            ->active()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->limit(500)
            ->pluck('reference')
            ->unique();

        $total = 0;
        foreach ($references as $reference) {
            $total += $this->releaseReservation($reference, StockReservation::EXPIRED);
        }

        return $total;
    }

    // ------------------------------------------------------------------ Internal

    /**
     * Terapkan perubahan pada baris inventories yang SUDAH terkunci, lalu tulis ledger.
     *
     * @param  int  $reservedDelta  perubahan qty_reserved (mis. -N saat reservasi diubah jadi penjualan)
     */
    private function apply(Inventory $inventory, Product $product, int $delta, int $reservedDelta, MovementType $type, array $meta): StockMovement
    {
        $before = $inventory->qty_on_hand;
        $after = $before + $delta;
        $newReserved = $inventory->qty_reserved + $reservedDelta;

        // after < newReserved: stok yang sebagian sudah dicadangkan pembeli lain tidak boleh ikut keluar.
        if ($after < 0 || $after < $newReserved) {
            throw InsufficientStockException::for(
                $product,
                $inventory->warehouse_id,
                abs($delta),
                max(0, $before - $inventory->qty_reserved)
            );
        }

        $inventory->qty_on_hand = $after;
        $inventory->qty_reserved = $newReserved;
        $inventory->save();

        /** @var Model|null $reference */
        $reference = $meta['reference'] ?? null;

        $movement = StockMovement::create([
            'product_id' => $inventory->product_id,
            'warehouse_id' => $inventory->warehouse_id,
            'type' => $type,
            'quantity' => $delta,
            'qty_before' => $before,
            'qty_after' => $after,
            'unit_cost' => $meta['unit_cost'] ?? null,
            'reason' => $meta['reason'] ?? null,
            'reference_type' => $reference ? $reference->getMorphClass() : ($meta['reference_type'] ?? null),
            'reference_id' => $reference ? $reference->getKey() : ($meta['reference_id'] ?? null),
            'reference_number' => $meta['reference_number'] ?? null,
            'idempotency_key' => $meta['idempotency_key'] ?? null,
            'note' => $meta['note'] ?? null,
            'user_id' => $meta['user_id'] ?? auth()->id(),
            'created_at' => now(),
        ]);

        $movementId = $movement->id;
        $productId = $inventory->product_id;
        $warehouseId = $inventory->warehouse_id;
        $minStock = (int) $product->min_stock;

        DB::afterCommit(function () use ($movementId, $productId, $warehouseId, $minStock, $before, $after, $delta) {
            event(new StockChanged($movementId));
            if ($delta < 0 && $minStock > 0 && $before > $minStock && $after <= $minStock) {
                event(new LowStockReached($productId, $warehouseId, $after, $minStock));
            }
        });

        return $movement;
    }

    /** Pastikan baris inventories ada (insert ... on conflict do nothing) lalu kunci. */
    private function lockInventory(int $productId, int $warehouseId): Inventory
    {
        DB::table('inventories')->insertOrIgnore([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'qty_on_hand' => 0,
            'qty_reserved' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Inventory::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function trackedProduct(int $productId): Product
    {
        $product = Product::withTrashed()->find($productId);
        if (! $product) {
            throw new StockException("Produk #{$productId} tidak ditemukan.");
        }
        if (! $product->track_stock) {
            throw new StockException("Produk \"{$product->name}\" tidak melacak stok.");
        }

        return $product;
    }

    private function assertWarehouseUsable(int $warehouseId): void
    {
        $warehouse = Warehouse::query()->find($warehouseId);
        if (! $warehouse) {
            throw new StockException("Lokasi stok #{$warehouseId} tidak ditemukan.");
        }
        if (! $warehouse->is_active) {
            throw new StockException("Lokasi \"{$warehouse->name}\" tidak aktif.");
        }
    }

    /**
     * Gabungkan item kembar, validasi, buang produk tanpa pelacakan stok, dan urutkan berdasarkan product_id
     * (urutan kunci yang konsisten mencegah deadlock).
     *
     * @return array<int,int> [product_id => quantity]
     */
    private function prepareItems(array $items): array
    {
        $merged = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 0);
            if ($productId <= 0 || $qty <= 0) {
                throw new StockException('Item tidak valid: product_id dan quantity harus bilangan positif.');
            }
            $merged[$productId] = ($merged[$productId] ?? 0) + $qty;
        }
        if ($merged === []) {
            throw new StockException('Daftar item kosong.');
        }

        $products = Product::withTrashed()->whereIn('id', array_keys($merged))->get()->keyBy('id');
        foreach (array_keys($merged) as $productId) {
            $product = $products->get($productId);
            if (! $product) {
                throw new StockException("Produk #{$productId} tidak ditemukan.");
            }
            if (! $product->track_stock) {
                unset($merged[$productId]);
            }
        }

        ksort($merged);

        return $merged;
    }

    /** @return Collection<int,StockReservation> */
    private function activeReservationsFor(string $reference): Collection
    {
        return StockReservation::query()
            ->where('reference', $reference)
            ->where('status', StockReservation::ACTIVE)
            ->orderBy('warehouse_id')
            ->orderBy('product_id')
            ->get();
    }
}
