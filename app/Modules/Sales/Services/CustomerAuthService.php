<?php

namespace App\Modules\Sales\Services;

use App\Modules\Crm\Models\Customer;
use App\Modules\Sales\Exceptions\SalesException;
use App\Services\OtpMailer;
use App\Services\OtpService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Pendaftaran / login / reset password pelanggan, dengan verifikasi email lewat OTP (Gmail API).
 *
 * Keamanan: akun hanya dibuat/diklaim SETELAH email terverifikasi. Pelanggan lama (mis. dari kasir) yang HP-nya sudah
 * tercatat tidak bisa diklaim lewat nomor HP saja; klaim hanya lewat email yang SAMA dengan yang tercatat.
 */
class CustomerAuthService
{
    private const PENDING_TTL_MINUTES = 15;

    public function __construct(private OtpService $otp, private OtpMailer $mailer)
    {
    }

    /** Langkah 1 pendaftaran: simpan data sementara (password sudah di-hash) dan kirim OTP ke email. */
    public function startRegistration(string $name, string $email, ?string $phone, string $password, bool $marketingConsent): ?string
    {
        $email = Customer::normalizeEmail($email);
        $phoneNorm = Customer::normalizePhone($phone);
        if ($phone !== null && trim($phone) !== '' && $phoneNorm === null) {
            throw new SalesException('Nomor HP tidak valid.');
        }

        $existing = Customer::query()->whereRaw('lower(email) = ?', [$email])->first();
        if ($existing && $existing->hasAccount()) {
            throw new SalesException('Email ini sudah terdaftar. Silakan masuk, atau pakai "Lupa password".');
        }
        if ($phoneNorm) {
            $byPhone = Customer::query()->where('phone', $phoneNorm)->first();
            if ($byPhone && (! $existing || $byPhone->id !== $existing->id)) {
                throw new SalesException('Nomor HP ini sudah tercatat di toko kami. Daftar memakai email yang sama dengan saat berbelanja sebelumnya, atau hubungi toko.');
            }
        }

        Cache::put("customer-reg:{$email}", [
            'name' => trim($name), 'phone' => $phoneNorm, 'password_hash' => Hash::make($password), 'consent' => $marketingConsent,
        ], now()->addMinutes(self::PENDING_TTL_MINUTES));

        return $this->sendCode("register:{$email}", $email, $name, 'Gunakan kode ini untuk menyelesaikan pendaftaran akunmu.', 'Kode verifikasi pendaftaran');
    }

    /** Langkah 2: cocokkan OTP lalu buat / klaim akun. */
    public function completeRegistration(string $email, string $code): Customer
    {
        $email = Customer::normalizeEmail($email);
        $pending = Cache::get("customer-reg:{$email}");
        if (! $pending) {
            throw new SalesException('Sesi pendaftaran sudah habis. Silakan ulangi dari awal.');
        }
        [$ok, $error] = $this->otp->verify("register:{$email}", $code);
        if (! $ok) {
            throw new SalesException($error);
        }

        $customer = Customer::query()->whereRaw('lower(email) = ?', [$email])->first();
        if ($customer && $customer->hasAccount()) {
            throw new SalesException('Email ini sudah terdaftar.');
        }

        if ($customer) { // klaim pelanggan tamu dengan email yang sama (sudah terverifikasi)
            $customer->forceFill(['name' => $pending['name'] ?: $customer->name, 'email_verified_at' => now()]);
        } else {
            $customer = new Customer(['name' => $pending['name'], 'email' => $email, 'source' => 'web', 'status' => 'active']);
            $customer->forceFill(['email' => $email, 'email_verified_at' => now()]);
        }
        if ($pending['phone'] && ! $customer->phone && ! Customer::query()->where('phone', $pending['phone'])->exists()) {
            $customer->phone = $pending['phone'];
        }
        $customer->forceFill([
            'marketing_consent' => (bool) $pending['consent'], 'marketing_consent_at' => $pending['consent'] ? now() : null,
        ]);
        $customer->save();
        // Hash sudah dibuat saat langkah 1; tulis langsung agar tidak di-hash ulang oleh cast "hashed".
        Customer::query()->whereKey($customer->id)->update(['password' => $pending['password_hash']]);

        Cache::forget("customer-reg:{$email}");

        return $customer->fresh();
    }

    public function attempt(string $email, string $password): Customer
    {
        $customer = Customer::query()->whereRaw('lower(email) = ?', [Customer::normalizeEmail($email)])->first();
        // Verifikasi selalu dijalankan (waktu respons seragam) agar tidak bisa dipakai menebak email terdaftar.
        $valid = Hash::check($password, $customer?->password ?? Hash::make('dummy-'.bin2hex(random_bytes(4))));
        if (! $customer || ! $customer->hasAccount() || ! $valid) {
            throw new SalesException('Email atau password salah.');
        }
        if (! $customer->isActive()) {
            throw new SalesException('Akun ini dinonaktifkan. Hubungi toko.');
        }

        return $customer;
    }

    /** Reset password langkah 1. Respons sama baik email terdaftar maupun tidak (tidak membocorkan keberadaan akun). */
    public function startReset(string $email): ?string
    {
        $email = Customer::normalizeEmail($email);
        $customer = Customer::query()->whereRaw('lower(email) = ?', [$email])->first();
        if (! $customer || ! $customer->hasAccount() || ! $customer->isActive()) {
            return null;
        }

        return $this->sendCode("customer-reset:{$email}", $email, $customer->name, 'Gunakan kode ini untuk mengatur ulang password akunmu.', 'Kode reset password');
    }

    public function completeReset(string $email, string $code, string $newPassword): Customer
    {
        $email = Customer::normalizeEmail($email);
        [$ok, $error] = $this->otp->verify("customer-reset:{$email}", $code);
        if (! $ok) {
            throw new SalesException($error);
        }
        $customer = Customer::query()->whereRaw('lower(email) = ?', [$email])->first();
        if (! $customer || ! $customer->hasAccount()) {
            throw new SalesException('Akun tidak ditemukan.');
        }
        $customer->forceFill(['password' => $newPassword])->save();
        $customer->tokens()->delete(); // semua sesi aplikasi mobile dicabut

        return $customer;
    }

    /** @return string|null catatan untuk pengguna (mode lokal) */
    private function sendCode(string $key, string $email, ?string $name, string $intro, string $subject): ?string
    {
        if (! $this->otp->canResend($key)) {
            throw new SalesException('Tunggu '.$this->otp->secondsUntilResendAllowed($key).' detik sebelum meminta kode baru.');
        }
        $code = $this->otp->generate($key);
        [$sent, $error, $note] = $this->mailer->send($email, $name, $subject, $intro, $code);
        if (! $sent) {
            $this->otp->clear($key);
            throw new SalesException($error ?: 'Kode verifikasi gagal dikirim. Coba lagi sebentar lagi.');
        }
        $this->otp->markResent($key);

        return $note;
    }
}
