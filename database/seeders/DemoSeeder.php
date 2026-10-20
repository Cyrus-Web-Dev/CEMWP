<?php

namespace Database\Seeders;

use App\Modules\Sales\Models\Coupon;
use App\Modules\Sales\Services\OrderService;
use App\Modules\Sales\Services\ShiftService;
use App\Models\User;
use App\Modules\Warehouse\Models\Category;
use App\Modules\Warehouse\Models\Product;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\Supplier;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Services\FulfillmentService;
use App\Modules\Warehouse\Services\StockService;
use App\Modules\Warehouse\Services\TransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Data contoh (toko kopi). Jalankan: php artisan db:seed --class=DemoSeeder
 * Aman diulang: produk/lokasi dicari dulu, stok awal hanya dimasukkan untuk produk yang belum punya riwayat.
 * Semua stok masuk lewat StockService, sehingga ledger konsisten (cek: php artisan cemwp:stock-verify).
 */
class DemoSeeder extends Seeder
{
    public function run(StockService $stock, TransferService $transfers, FulfillmentService $fulfillments, OrderService $orders, ShiftService $shifts): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(SalesSeeder::class);

        $main = Warehouse::firstOrCreate(
            ['code' => config('cemwp.warehouse.default_warehouse_code', 'GD-UTAMA')],
            ['name' => 'Gudang Utama', 'type' => 'warehouse', 'is_active' => true, 'is_default' => ! Warehouse::where('is_default', true)->exists()]
        );
        $store = Warehouse::firstOrCreate(['code' => 'TK-PDG'], ['name' => 'Toko Padang', 'type' => 'store', 'address' => 'Padang, Sumatera Barat']);
        Warehouse::firstOrCreate(['code' => 'ONLINE'], ['name' => 'Stok Online', 'type' => 'online']);

        $cat = fn (string $name) => Category::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        $kopi = $cat('Kopi')->id;
        $minuman = $cat('Bahan Minuman')->id;
        $alat = $cat('Peralatan Seduh')->id;
        $kemasan = $cat('Kemasan')->id;

        $suppliers = [
            ['SUP-GAYO', 'Koperasi Kopi Gayo', 'Aceh Tengah'],
            ['SUP-LPG', 'Tani Robusta Lampung', 'Lampung Barat'],
            ['SUP-ALAT', 'CV Perkakas Seduh Nusantara', 'Jakarta'],
        ];
        foreach ($suppliers as [$code, $name, $address]) {
            Supplier::firstOrCreate(['code' => $code], ['name' => $name, 'address' => $address, 'phone' => '0812'.random_int(10000000, 99999999)]);
        }

        // sku, nama, kategori, satuan, harga pokok, harga jual, min stok, berat(g), stok awal gudang
        $products = [
            ['KP-GAYO-250', 'Kopi Arabika Gayo 250g', $kopi, 'pak', 62000, 95000, 20, 270, 120],
            ['KP-ROBL-250', 'Kopi Robusta Lampung 250g', $kopi, 'pak', 38000, 65000, 20, 270, 150],
            ['KP-TORJ-250', 'Kopi Toraja Kalosi 250g', $kopi, 'pak', 70000, 110000, 15, 270, 60],
            ['KP-HOUSE-1K', 'Kopi House Blend 1kg', $kopi, 'pak', 120000, 185000, 10, 1050, 8],
            ['MN-ARENCR-500', 'Gula Aren Cair 500ml', $minuman, 'botol', 24000, 39000, 24, 600, 90],
            ['MN-SIRUP-VAN', 'Sirup Vanilla 750ml', $minuman, 'botol', 52000, 78000, 12, 900, 5],
            ['MN-SUSU-1L', 'Susu UHT Full Cream 1L', $minuman, 'karton', 17000, 24000, 48, 1100, 200],
            ['AL-FPRESS-600', 'French Press 600ml', $alat, 'pcs', 95000, 159000, 6, 900, 25],
            ['AL-V60-FLT100', 'Kertas Filter V60 (isi 100)', $alat, 'pak', 21000, 38000, 15, 150, 70],
            ['AL-TUMBLER-500', 'Tumbler Stainless 500ml', $alat, 'pcs', 55000, 99000, 10, 380, 40],
            ['KM-CUP8-50', 'Gelas Kertas 8oz (isi 50)', $kemasan, 'pak', 14000, 25000, 30, 500, 300],
            ['KM-PAPERBAG', 'Paper Bag Kopi 250g (isi 25)', $kemasan, 'pak', 18000, 32000, 20, 400, 100],
        ];

        $made = [];
        foreach ($products as [$sku, $name, $categoryId, $unit, $cost, $price, $min, $weight, $qty]) {
            $product = Product::firstOrCreate(['sku' => $sku], [
                'name' => $name, 'category_id' => $categoryId, 'unit' => $unit, 'cost_price' => $cost, 'selling_price' => $price,
                'min_stock' => $min, 'weight_grams' => $weight, 'barcode' => '899'.random_int(1000000000, 9999999999),
            ]);
            $made[$sku] = $product;

            if (! StockMovement::where('product_id', $product->id)->exists()) {
                $stock->receive($product->id, $main->id, $qty, (float) $cost, ['reference_number' => 'SALDO-AWAL', 'note' => 'Saldo awal (data contoh)']);
            }
        }

        // Transfer contoh: satu sudah diterima toko, satu masih dalam perjalanan.
        if (\App\Modules\Warehouse\Models\StockTransfer::query()->doesntExist()) {
            $t1 = $transfers->create(['from_warehouse_id' => $main->id, 'to_warehouse_id' => $store->id, 'notes' => 'Stok awal toko', 'items' => [
                ['product_id' => $made['KP-GAYO-250']->id, 'quantity' => 30], ['product_id' => $made['KM-CUP8-50']->id, 'quantity' => 60],
            ]], null);
            $transfers->receive($transfers->ship($t1, null), null);

            $t2 = $transfers->create(['from_warehouse_id' => $main->id, 'to_warehouse_id' => $store->id, 'notes' => 'Isi ulang minggu ini', 'items' => [
                ['product_id' => $made['KP-ROBL-250']->id, 'quantity' => 25], ['product_id' => $made['MN-SUSU-1L']->id, 'quantity' => 40],
            ]], null);
            $transfers->ship($t2, null);

            // Stok untuk etalase online & aplikasi mobile (lokasi ONLINE).
            $online = Warehouse::where('code', 'ONLINE')->first();
            $t3 = $transfers->create(['from_warehouse_id' => $main->id, 'to_warehouse_id' => $online->id, 'notes' => 'Stok etalase online', 'items' => [
                ['product_id' => $made['KP-GAYO-250']->id, 'quantity' => 20], ['product_id' => $made['KP-ROBL-250']->id, 'quantity' => 30],
                ['product_id' => $made['KP-TORJ-250']->id, 'quantity' => 10], ['product_id' => $made['AL-V60-FLT100']->id, 'quantity' => 20],
                ['product_id' => $made['AL-TUMBLER-500']->id, 'quantity' => 10], ['product_id' => $made['MN-ARENCR-500']->id, 'quantity' => 15],
            ]], null);
            $transfers->receive($transfers->ship($t3, null), null);
        }

        // Tugas gudang contoh (seolah dipicu e-commerce). Idempoten per source_id.
        $orders = [
            [9001, 'ORD-DEMO-0001', 'Rina Marlina', '0813-1111-2222', 'Jl. Sudirman No. 12, Padang', 'normal', [['KP-GAYO-250', 2], ['AL-V60-FLT100', 1]]],
            [9002, 'ORD-DEMO-0002', 'Budi Santoso', '0812-3333-4444', 'Jl. Khatib Sulaiman No. 5, Padang', 'urgent', [['AL-TUMBLER-500', 1]]],
            [9003, 'ORD-DEMO-0003', 'Siti Aminah', '0857-5555-6666', 'Jl. Pemuda No. 30, Bukittinggi', 'normal', [['KP-ROBL-250', 4], ['MN-ARENCR-500', 2]]],
        ];
        foreach ($orders as [$id, $number, $recipient, $phone, $address, $priority, $lines]) {
            $f = $fulfillments->createFromOrder([
                'source_type' => 'manual', 'source_id' => $id, 'source_number' => $number, 'channel' => 'ecommerce',
                'warehouse_id' => $main->id, 'priority' => $priority, 'recipient_name' => $recipient, 'recipient_phone' => $phone,
                'shipping_address' => $address,
                'items' => array_map(fn ($l) => ['product_id' => $made[$l[0]]->id, 'quantity' => $l[1]], $lines),
            ]);
            if ($id === 9003 && $f->status->value === 'pending') {
                $fulfillments->start($f, null);
            }
        }

        // Kupon contoh
        foreach ([
            ['WELCOME10', 'Diskon 10% belanja pertama', 'percent', 10, 50000, 25000, 'all'],
            ['ONLINE20K', 'Potongan Rp 20.000 belanja online', 'fixed', 20000, 100000, null, 'online'],
        ] as [$code, $name, $type, $value, $min, $max, $channel]) {
            Coupon::firstOrCreate(['code' => $code], [
                'name' => $name, 'type' => $type, 'value' => $value, 'min_subtotal' => $min, 'max_discount' => $max,
                'channel' => $channel, 'usage_limit_per_customer' => 1, 'is_active' => true,
            ]);
        }

        // Akun demo + beberapa penjualan kasir (HANYA di lingkungan local). Password: Password123
        if (! app()->environment('local')) {
            return;
        }
        $demoUsers = [];
        foreach ([
            ['kasir@cemwp.test', 'Kasir Demo', 'cashier'], ['sales@cemwp.test', 'Manajer Penjualan Demo', 'sales-manager'],
            ['gudang@cemwp.test', 'Staf Gudang Demo', 'warehouse-staff'],
        ] as [$email, $name, $role]) {
            $u = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'Password123', 'email_verified_at' => now(), 'status' => 'active']);
            $u->syncRoles([$role]);
            $demoUsers[$role] = $u;
        }

        $cashier = $demoUsers['cashier'];
        if (\App\Modules\Sales\Models\Order::query()->doesntExist() && ! $shifts->current($cashier)) {
            $shifts->open($cashier, $store->id, 200000);
            $sales = [
                ['0812-7777-0001', 'Andi', [['KP-GAYO-250', 1], ['AL-V60-FLT100', 1]], 'CASH'],
                ['0812-7777-0002', 'Dewi', [['KP-ROBL-250', 2]], 'QRIS'],
                [null, null, [['KM-CUP8-50', 2]], 'CASH'],
            ];
            foreach ($sales as $i => [$phone, $name, $lines, $method]) {
                $items = array_map(fn ($l) => ['product_id' => $made[$l[0]]->id, 'quantity' => $l[1]], $lines);
                $total = collect($lines)->sum(fn ($l) => $made[$l[0]]->selling_price * $l[1]);
                $orders->createPosSale([
                    'idempotency_key' => "demo-sale-{$i}", 'items' => $items, 'customer_phone' => $phone, 'customer_name' => $name,
                    'payments' => [['method' => $method, 'amount' => $method === 'CASH' ? $total + 5000 : $total, 'reference' => $method === 'QRIS' ? 'DEMO-REF' : null]],
                ], $cashier);
            }
        }
    }
}
