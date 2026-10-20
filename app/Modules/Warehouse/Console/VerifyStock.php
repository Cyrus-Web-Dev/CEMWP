<?php

namespace App\Modules\Warehouse\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyStock extends Command
{
    protected $signature = 'cemwp:stock-verify';

    protected $description = 'Rekonsiliasi: saldo inventories harus sama dengan jumlah ledger stock_movements, dan reservasi aktif harus sama dengan qty_reserved';

    public function handle(): int
    {
        $problems = 0;

        // 1) Saldo vs ledger. Kombinasi yang hanya ada di salah satu sisi juga dianggap selisih.
        $rows = DB::select(<<<'SQL'
SELECT COALESCE(i.product_id, m.product_id) AS product_id,
       COALESCE(i.warehouse_id, m.warehouse_id) AS warehouse_id,
       COALESCE(i.qty_on_hand, 0) AS saldo,
       COALESCE(m.total, 0) AS ledger
FROM inventories i
FULL OUTER JOIN (
    SELECT product_id, warehouse_id, SUM(quantity) AS total FROM stock_movements GROUP BY product_id, warehouse_id
) m ON m.product_id = i.product_id AND m.warehouse_id = i.warehouse_id
WHERE COALESCE(i.qty_on_hand, 0) <> COALESCE(m.total, 0)
SQL);
        foreach ($rows as $r) {
            $problems++;
            $this->error("Selisih saldo: produk #{$r->product_id} lokasi #{$r->warehouse_id} saldo={$r->saldo} ledger={$r->ledger}");
        }

        // 2) Reservasi aktif vs qty_reserved.
        $rows = DB::select(<<<'SQL'
SELECT i.product_id, i.warehouse_id, i.qty_reserved, COALESCE(r.total, 0) AS aktif
FROM inventories i
LEFT JOIN (
    SELECT product_id, warehouse_id, SUM(quantity) AS total FROM stock_reservations WHERE status = 'active' GROUP BY product_id, warehouse_id
) r ON r.product_id = i.product_id AND r.warehouse_id = i.warehouse_id
WHERE i.qty_reserved <> COALESCE(r.total, 0)
SQL);
        foreach ($rows as $r) {
            $problems++;
            $this->error("Selisih reservasi: produk #{$r->product_id} lokasi #{$r->warehouse_id} qty_reserved={$r->qty_reserved} reservasi_aktif={$r->aktif}");
        }

        if ($problems === 0) {
            $this->info('OK: semua saldo stok cocok dengan ledger dan reservasi.');

            return self::SUCCESS;
        }
        $this->warn("{$problems} ketidakcocokan ditemukan.");

        return self::FAILURE;
    }
}
