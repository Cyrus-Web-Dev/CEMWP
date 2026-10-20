<?php

namespace Database\Seeders;

use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Models\ShippingMethod;
use Illuminate\Database\Seeder;

/** Pengaturan awal penjualan: metode pembayaran & pengiriman. Aman dijalankan ulang (tidak menimpa perubahanmu). */
class SalesSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            // code, name, kind, pos, online, requires_ref, active, sort, instructions
            ['CASH', 'Tunai', 'cash', true, false, false, true, 1, null],
            ['QRIS', 'QRIS', 'manual', true, false, true, true, 2, null],
            ['CARD', 'Kartu debit / kredit', 'manual', true, false, true, true, 3, null],
            ['BANK_TRANSFER', 'Transfer bank', 'manual', false, true, false, true, 4, config('cemwp.sales.bank_transfer_info')],
            // Nonaktif sampai MIDTRANS_SERVER_KEY diisi (aktifkan di Sales > Pengaturan).
            ['MIDTRANS', 'Pembayaran online (QRIS, e-wallet, VA)', 'gateway', false, true, false, false, 5, null],
            ['COD', 'Bayar di tempat (COD)', 'cod', false, true, false, true, 6, null],
        ];
        foreach ($methods as [$code, $name, $kind, $pos, $online, $ref, $active, $sort, $instructions]) {
            PaymentMethod::firstOrCreate(['code' => $code], [
                'name' => $name, 'kind' => $kind, 'for_pos' => $pos, 'for_online' => $online,
                'requires_reference' => $ref, 'is_active' => $active, 'sort_order' => $sort, 'instructions' => $instructions,
            ]);
        }

        $shipping = [
            // courier, service, pickup, base, per_kg, free_over, min, max, sort
            ['Ambil di toko', 'Gratis', true, 0, 0, null, 0, 0, 1],
            ['Kurir toko', 'Dalam kota', false, 8000, 2000, 150000, 0, 1, 2],
            ['JNE', 'Reguler', false, 10000, 8000, null, 2, 4, 3],
            ['J&T', 'Express', false, 11000, 8500, null, 2, 4, 4],
        ];
        foreach ($shipping as [$courier, $service, $pickup, $base, $perKg, $freeOver, $min, $max, $sort]) {
            ShippingMethod::firstOrCreate(['courier' => $courier, 'service' => $service], [
                'is_pickup' => $pickup, 'base_cost' => $base, 'cost_per_kg' => $perKg, 'free_over' => $freeOver,
                'min_days' => $min, 'max_days' => $max, 'is_active' => true, 'sort_order' => $sort,
            ]);
        }
    }
}
