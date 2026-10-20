<?php

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Menemukan / membuat pelanggan dari nomor HP atau email. Dipakai POS (kasir mengetik HP) dan checkout tamu,
 * sehingga satu orang yang belanja di kasir lalu online tetap satu profil.
 *
 * Aman dipanggil di dalam transaksi order: memakai "INSERT ... ON CONFLICT DO NOTHING" sehingga tabrakan unik
 * (dua kasir mendaftarkan HP yang sama bersamaan) tidak membatalkan transaksi PostgreSQL.
 */
class CustomerResolver
{
    /** @param array{name?:?string,phone?:?string,email?:?string,source?:?string} $data */
    public function findOrCreate(array $data): ?Customer
    {
        $phone = Customer::normalizePhone($data['phone'] ?? null);
        $email = Customer::normalizeEmail($data['email'] ?? null);
        if ($phone === null && $email === null) {
            return null; // pembeli tanpa identitas (walk-in)
        }

        $customer = $this->find($phone, $email);

        if (! $customer) {
            DB::table('customers')->insertOrIgnore([
                'name' => trim((string) ($data['name'] ?? '')) ?: 'Pelanggan',
                'email' => $email, 'phone' => $phone,
                'source' => $data['source'] ?? 'web', 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            // Bila konflik ganda tak terselesaikan, hasilnya null = diperlakukan sebagai walk-in.
            return $this->find($phone, $email);
        }

        // Lengkapi data yang masih kosong; JANGAN menimpa data yang sudah ada.
        $fill = [];
        if ($phone && ! $customer->phone && ! Customer::query()->where('phone', $phone)->exists()) {
            $fill['phone'] = $phone;
        }
        if ($email && ! $customer->email && ! Customer::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            $fill['email'] = $email;
        }
        if (($customer->name === '' || $customer->name === 'Pelanggan') && ! empty($data['name'])) {
            $fill['name'] = trim($data['name']);
        }
        if ($fill) {
            $customer->forceFill($fill)->save();
        }

        return $customer;
    }

    public function find(?string $phone, ?string $email): ?Customer
    {
        if ($phone) {
            $c = Customer::query()->where('phone', $phone)->first();
            if ($c) {
                return $c;
            }
        }
        if ($email) {
            return Customer::query()->whereRaw('lower(email) = ?', [$email])->first();
        }

        return null;
    }
}
