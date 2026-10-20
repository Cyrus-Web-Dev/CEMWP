<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /** Permission per modul: "modul.aksi". Modul baru (Crm, Marketing) menambahkan barisnya di sini. */
    public static array $modules = [
        // --- Inti ---
        'users' => ['view', 'manage'],
        'settings' => ['manage'],
        // --- Warehouse ---
        'products' => ['view', 'manage'],
        'categories' => ['manage'],
        'warehouses' => ['view', 'manage'],
        'suppliers' => ['view', 'manage'],
        'inventory' => ['view', 'adjust', 'export'],
        'purchasing' => ['view', 'manage', 'receive'],
        'transfers' => ['view', 'manage', 'receive'],
        'opname' => ['view', 'manage', 'finalize'],
        'fulfillment' => ['view', 'process'],
        // --- Sales ---
        'pos' => ['operate'],
        'orders' => ['view', 'manage', 'refund'],
        'coupons' => ['manage'],
        'salesconfig' => ['manage'],
        'reports' => ['view'],
    ];

    /** Permission milik modul Warehouse (untuk role gudang). */
    private const WAREHOUSE = ['products', 'categories', 'warehouses', 'suppliers', 'inventory', 'purchasing', 'transfers', 'opname', 'fulfillment'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = [];
        foreach (self::$modules as $module => $actions) {
            foreach ($actions as $action) {
                $all[] = "{$module}.{$action}";
            }
        }
        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $of = fn (array $modules) => array_values(array_filter($all, fn ($p) => in_array(explode('.', $p)[0], $modules, true)));

        // super-admin: semua izin.
        $this->role('super-admin', $all);

        // warehouse-manager: seluruh operasional gudang; hanya MELIHAT daftar pengguna.
        $this->role('warehouse-manager', array_merge($of(self::WAREHOUSE), ['users.view']));

        // warehouse-staff: kerja harian di lantai gudang (tanpa master data, penyesuaian, PO, finalisasi opname).
        $this->role('warehouse-staff', [
            'products.view', 'warehouses.view', 'suppliers.view', 'inventory.view',
            'purchasing.view', 'purchasing.receive',
            'transfers.view', 'transfers.manage', 'transfers.receive',
            'opname.view', 'opname.manage',
            'fulfillment.view', 'fulfillment.process',
        ]);

        // cashier: hanya mengoperasikan kasir (shift, transaksi, cetak struk miliknya).
        $this->role('cashier', ['pos.operate']);

        // sales-manager: seluruh penjualan + melihat produk/stok/pengiriman; tidak mengubah data gudang.
        $this->role('sales-manager', [
            'pos.operate', 'orders.view', 'orders.manage', 'orders.refund', 'coupons.manage', 'salesconfig.manage', 'reports.view',
            'products.view', 'inventory.view', 'fulfillment.view', 'users.view',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function role(string $name, array $permissions): void
    {
        Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->syncPermissions($permissions);
    }
}
