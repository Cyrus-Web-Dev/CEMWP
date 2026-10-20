<?php

namespace App\Modules\Crm\Listeners;

use App\Modules\Sales\Events\OrderPaid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Langkah ke-3 & ke-7 "The Connected Loop": pesanan yang sudah dibayar masuk ke profil perilaku pelanggan
 * (berapa kali beli, lewat jalur mana, produk apa). Marketing kelak membaca tabel ini untuk menyusun kupon.
 *
 * Penting: gagal di sini TIDAK BOLEH menggagalkan penjualan, jadi semua kesalahan ditelan dan dicatat di log.
 * Idempoten: satu order hanya dihitung sekali (crm_processed_orders).
 */
class UpdateCustomerPurchaseStats
{
    public function handle(OrderPaid $event): void
    {
        if (! $event->customerId) {
            return;
        }

        try {
            DB::transaction(function () use ($event) {
                $new = DB::table('crm_processed_orders')->insertOrIgnore(['order_id' => $event->orderId, 'processed_at' => now()]);
                if ($new === 0) {
                    return;
                }

                $pos = $event->channel === 'pos' ? 1 : 0;
                $online = 1 - $pos;

                DB::update(
                    'UPDATE customers SET orders_count = orders_count + 1, pos_orders_count = pos_orders_count + ?, '
                    .'online_orders_count = online_orders_count + ?, total_spent = total_spent + ?, '
                    .'first_order_at = COALESCE(first_order_at, ?), last_order_at = ?, last_channel = ?, updated_at = ? WHERE id = ?',
                    [$pos, $online, $event->total, $event->paidAt, $event->paidAt, $event->channel, now(), $event->customerId]
                );

                foreach ($event->items as $item) {
                    DB::insert(
                        'INSERT INTO customer_product_stats (customer_id, product_id, quantity_total, orders_count, pos_orders_count, online_orders_count, last_purchased_at) '
                        .'VALUES (?, ?, ?, 1, ?, ?, ?) '
                        .'ON CONFLICT (customer_id, product_id) DO UPDATE SET '
                        .'quantity_total = customer_product_stats.quantity_total + EXCLUDED.quantity_total, '
                        .'orders_count = customer_product_stats.orders_count + 1, '
                        .'pos_orders_count = customer_product_stats.pos_orders_count + EXCLUDED.pos_orders_count, '
                        .'online_orders_count = customer_product_stats.online_orders_count + EXCLUDED.online_orders_count, '
                        .'last_purchased_at = EXCLUDED.last_purchased_at',
                        [$event->customerId, $item['product_id'], $item['quantity'], $pos, $online, $event->paidAt]
                    );
                }
            });
        } catch (Throwable $e) {
            Log::error('[CRM] Gagal memperbarui profil pelanggan untuk order #'.$event->orderId.': '.$e->getMessage());
        }
    }
}
