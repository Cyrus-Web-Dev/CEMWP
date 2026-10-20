<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(SalesSeeder::class);

        // Lokasi stok bawaan (dipakai penerimaan barang & stok online). Aman dijalankan ulang.
        Warehouse::firstOrCreate(
            ['code' => config('cemwp.warehouse.default_warehouse_code', 'GD-UTAMA')],
            ['name' => 'Gudang Utama', 'type' => 'warehouse', 'is_active' => true, 'is_default' => ! Warehouse::where('is_default', true)->exists()]
        );

        // Opsional: super-admin awal dari .env (CEMWP_ADMIN_EMAIL + CEMWP_ADMIN_PASSWORD).
        $email = config('cemwp.admin.email');
        $password = config('cemwp.admin.password');
        if ($email && $password) {
            $admin = User::firstOrCreate(
                ['email' => strtolower($email)],
                ['name' => config('cemwp.admin.name'), 'password' => $password, 'email_verified_at' => now(), 'status' => 'active']
            );
            $admin->syncRoles(['super-admin']);
        }
    }
}
