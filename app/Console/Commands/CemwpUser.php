<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class CemwpUser extends Command
{
    protected $signature = 'cemwp:user {email : Email user}
                            {--name= : Nama (wajib bila user baru)}
                            {--password= : Password (wajib bila user baru; minimal 8 karakter)}
                            {--role=warehouse-staff : super-admin | warehouse-manager | warehouse-staff}';

    protected $description = 'Buat user baru atau ubah role/password user yang sudah ada (tanpa perlu tinker / email OTP)';

    public function handle(): int
    {
        (new RolePermissionSeeder())->run(); // pastikan role & permission ada (aman diulang)

        $role = (string) $this->option('role');
        if (! Role::where('name', $role)->exists()) {
            $this->error('Role tidak dikenal. Pilihan: '.Role::orderBy('name')->pluck('name')->implode(' | '));

            return self::FAILURE;
        }

        $email = strtolower((string) $this->argument('email'));
        $user = User::where('email', $email)->first();

        if (! $user) {
            $name = $this->option('name') ?: $this->ask('Nama lengkap');
            $password = $this->option('password') ?: $this->secret('Password (min. 8 karakter)');

            $validator = Validator::make(
                ['name' => $name, 'email' => $email, 'password' => $password],
                ['name' => 'required|string|max:255', 'email' => 'required|email', 'password' => 'required|string|min:8']
            );
            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $this->error($message);
                }

                return self::FAILURE;
            }

            $user = User::create([
                'name' => $name, 'email' => $email, 'password' => $password,
                'email_verified_at' => now(), 'status' => 'active',
            ]);
            $this->info("User baru dibuat: {$user->email}");
        } elseif ($this->option('password')) {
            if (strlen((string) $this->option('password')) < 8) {
                $this->error('Password minimal 8 karakter.');

                return self::FAILURE;
            }
            $user->forceFill(['password' => $this->option('password')])->save();
            $this->info('Password diperbarui.');
        }

        $user->syncRoles([$role]);
        $user->forceFill(['status' => 'active'])->save();
        $this->info("Role untuk {$user->email} sekarang: {$role}");

        return self::SUCCESS;
    }
}
