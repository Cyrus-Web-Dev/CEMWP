<?php

namespace App\Modules\Sales\Services;

use App\Models\User;
use App\Modules\Sales\Exceptions\SalesException;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderItem;
use App\Modules\Sales\Models\OrderReturn;
use App\Modules\Warehouse\Contracts\StockGateway;
use App\Services\ActivityLogger;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/** Retur barang yang sudah terjual (POS maupun online) + pengembalian dana. Ongkir & pajak tidak ikut dikembalikan. */
class ReturnService
{
    public const REFUND_METHODS = ['cash' => 'Tunai dari laci kasir', 'original' => 'Ke metode bayar asal (diproses manual)', 'manual' => 'Cara lain (dicatat manual)'];

    public function __construct(private StockGateway $stock, private ShiftService $shifts)
    {
    }

    /**
     * @param  array<int,int>  $quantities  [order_item_id => jumlah diretur]
     */
    public function create(Order $order, array $quantities, string $reason, string $refundMethod, bool $restock, User $by): OrderReturn
    {
        if (! array_key_exists($refundMethod, self::REFUND_METHODS)) {
            throw new SalesException('Metode pengembalian dana tidak dikenal.');
        }
        if (trim($reason) === '') {
            throw new SalesException('Isi alasan retur.');
        }

        return DB::transaction(function () use ($order, $quantities, $reason, $refundMethod, $restock, $by) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($order->status, [Order::COMPLETED, Order::PROCESSING, Order::SHIPPED], true) || ! in_array($order->payment_status, ['paid', 'partially_refunded'], true)) {
                throw new SalesException('Retur hanya untuk pesanan yang sudah dibayar dan tidak dibatalkan.');
            }

            $shift = $this->shifts->current($by);
            if ($refundMethod === 'cash' && ! $shift) {
                throw new SalesException('Buka shift kasir dulu untuk refund tunai dari laci.');
            }

            $items = OrderItem::query()->where('order_id', $order->id)->withSum('returnItems as returned_qty', 'quantity')->get()->keyBy('id');

            $lines = [];
            $amount = 0.0;
            foreach ($quantities as $itemId => $qty) {
                $qty = (int) $qty;
                if ($qty <= 0) {
                    continue;
                }
                $item = $items->get((int) $itemId);
                if (! $item) {
                    throw new SalesException('Item retur tidak termasuk pesanan ini.');
                }
                $returnable = $item->quantity - (int) $item->returned_qty;
                if ($qty > $returnable) {
                    throw new SalesException("Retur {$item->name} melebihi jumlah yang bisa diretur ({$returnable}).");
                }
                $lineAmount = round((float) $item->line_total * $qty / $item->quantity, 0);
                $amount += $lineAmount;
                $lines[] = [$item, $qty, $lineAmount];
            }
            if ($lines === []) {
                throw new SalesException('Pilih minimal satu barang yang diretur.');
            }

            $number = DocumentNumber::next('RET');
            $return = OrderReturn::create([
                'number' => $number, 'order_id' => $order->id, 'user_id' => $by->id,
                'shift_id' => $shift?->id,
                'reason' => mb_substr(trim($reason), 0, 250), 'refund_method' => $refundMethod,
                'refund_amount' => $amount, 'restocked' => $restock && $order->stock_committed, 'created_at' => now(),
            ]);
            foreach ($lines as [$item, $qty, $lineAmount]) {
                $return->items()->create(['order_item_id' => $item->id, 'product_id' => $item->product_id, 'quantity' => $qty, 'amount' => $lineAmount]);
            }

            if ($return->restocked) {
                $this->stock->restock(
                    $order->warehouse_id,
                    array_map(fn ($l) => ['product_id' => $l[0]->product_id, 'quantity' => $l[1]], $lines),
                    $number, "return:{$number}", $by->id, 'sale_return'
                );
            }

            // "Dikembalikan" penuh bila seluruh nilai barang sudah dikembalikan (ongkir & pajak tidak ikut).
            $totalRefunded = (float) OrderReturn::query()->where('order_id', $order->id)->sum('refund_amount');
            $itemsTotal = (float) $items->sum('line_total');
            $order->forceFill(['payment_status' => $totalRefunded >= $itemsTotal - 0.5 ? 'refunded' : 'partially_refunded'])->save();

            \App\Modules\Sales\Models\OrderStatusHistory::create([
                'order_id' => $order->id, 'from_status' => $order->status, 'to_status' => $order->status,
                'note' => "Retur {$number}: Rp ".number_format($amount, 0, ',', '.'), 'user_id' => $by->id, 'created_at' => now(),
            ]);
            ActivityLogger::log('order.return', "Retur {$number} untuk {$order->number}: Rp ".number_format($amount, 0, ',', '.'), $order, $by->id);

            return $return->load('items');
        });
    }
}
