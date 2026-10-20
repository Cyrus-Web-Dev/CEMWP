<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Exceptions\StockException;
use App\Modules\Warehouse\Models\StockTransfer;
use App\Support\DocumentNumber;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

/** Perpindahan stok antar lokasi: kirim (stok keluar dari asal) lalu terima (stok masuk ke tujuan). */
class TransferService
{
    public function __construct(private StockService $stock)
    {
    }

    /** @param array{from_warehouse_id:int,to_warehouse_id:int,notes?:?string,items:array<int,array{product_id:int,quantity:int}>} $data */
    public function create(array $data, ?int $userId): StockTransfer
    {
        if ((int) $data['from_warehouse_id'] === (int) $data['to_warehouse_id']) {
            throw new StockException('Lokasi asal dan tujuan harus berbeda.');
        }

        return DB::transaction(function () use ($data, $userId) {
            $lines = [];
            foreach ($data['items'] as $item) {
                $qty = (int) $item['quantity'];
                if ($qty <= 0) {
                    throw new StockException('Jumlah setiap item harus lebih dari nol.');
                }
                $lines[(int) $item['product_id']] = ($lines[(int) $item['product_id']] ?? 0) + $qty;
            }
            if ($lines === []) {
                throw new StockException('Tambahkan minimal satu item.');
            }
            ksort($lines);

            $transfer = StockTransfer::create([
                'number' => DocumentNumber::next('TRF'),
                'from_warehouse_id' => $data['from_warehouse_id'], 'to_warehouse_id' => $data['to_warehouse_id'],
                'status' => StockTransfer::DRAFT, 'notes' => $data['notes'] ?? null, 'created_by' => $userId,
            ]);
            foreach ($lines as $productId => $qty) {
                $transfer->items()->create(['product_id' => $productId, 'quantity' => $qty]);
            }
            ActivityLogger::log('transfer.created', "Transfer {$transfer->number} dibuat", $transfer, $userId);

            return $transfer;
        });
    }

    /** Kirim: stok langsung keluar dari lokasi asal. Gagal total bila salah satu item kurang stok. */
    public function ship(StockTransfer $transfer, ?int $userId): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $userId) {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if ($transfer->status !== StockTransfer::DRAFT) {
                throw new StockException('Transfer ini sudah dikirim atau dibatalkan.');
            }

            foreach ($transfer->items()->orderBy('product_id')->get() as $item) {
                $this->stock->move($item->product_id, $transfer->from_warehouse_id, -$item->quantity, MovementType::TransferOut, [
                    'reference' => $transfer, 'reference_number' => $transfer->number, 'user_id' => $userId,
                ]);
            }
            $transfer->update(['status' => StockTransfer::IN_TRANSIT, 'shipped_at' => now()]);
            ActivityLogger::log('transfer.shipped', "Transfer {$transfer->number} dikirim", $transfer, $userId);

            return $transfer;
        });
    }

    public function receive(StockTransfer $transfer, ?int $userId): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $userId) {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if ($transfer->status !== StockTransfer::IN_TRANSIT) {
                throw new StockException('Transfer ini tidak sedang dalam perjalanan.');
            }

            foreach ($transfer->items()->orderBy('product_id')->get() as $item) {
                $this->stock->move($item->product_id, $transfer->to_warehouse_id, $item->quantity, MovementType::TransferIn, [
                    'reference' => $transfer, 'reference_number' => $transfer->number, 'user_id' => $userId,
                ]);
                $item->update(['qty_received' => $item->quantity]);
            }
            $transfer->update(['status' => StockTransfer::RECEIVED, 'received_at' => now()]);
            ActivityLogger::log('transfer.received', "Transfer {$transfer->number} diterima", $transfer, $userId);

            return $transfer;
        });
    }

    /** Batalkan. Bila sudah dikirim, stok dikembalikan ke lokasi asal. */
    public function cancel(StockTransfer $transfer, ?int $userId): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $userId) {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($transfer->status === StockTransfer::IN_TRANSIT) {
                foreach ($transfer->items()->orderBy('product_id')->get() as $item) {
                    $this->stock->move($item->product_id, $transfer->from_warehouse_id, $item->quantity, MovementType::TransferIn, [
                        'reference' => $transfer, 'reference_number' => $transfer->number, 'user_id' => $userId,
                        'reason' => 'transfer_cancelled', 'note' => 'Pembatalan transfer: stok dikembalikan ke lokasi asal',
                    ]);
                }
            } elseif ($transfer->status !== StockTransfer::DRAFT) {
                throw new StockException('Transfer ini tidak bisa dibatalkan.');
            }

            $transfer->update(['status' => StockTransfer::CANCELLED]);
            ActivityLogger::log('transfer.cancelled', "Transfer {$transfer->number} dibatalkan", $transfer, $userId);

            return $transfer;
        });
    }
}
