<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CemwpApiToken extends Command
{
    protected $signature = 'cemwp:api-token {email : Email user pemilik token} {--name=dev : Nama token}';

    protected $description = 'Buat token API (Sanctum, izin catalog:read) untuk menguji /api/v1/catalog/* dari aplikasi mobile / Postman';

    public function handle(): int
    {
        $user = User::where('email', strtolower((string) $this->argument('email')))->first();
        if (! $user) {
            $this->error('User tidak ditemukan.');

            return self::FAILURE;
        }

        $ttl = (int) config('cemwp.warehouse.api_token_ttl_minutes');
        $token = $user->createToken((string) $this->option('name'), ['catalog:read'], $ttl > 0 ? now()->addMinutes($ttl) : null);

        $this->info('Token (disimpan hanya sekali, salin sekarang):');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
