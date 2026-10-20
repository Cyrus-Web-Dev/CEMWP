<?php

namespace App\Providers;

use App\Mail\Transport\GmailApiTransport;
use App\Services\GmailApiService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GmailApiService::class);
    }

    public function boot(): void
    {
        // Kebijakan password minimum di SELURUH aplikasi (reset, ganti password, akun baru).
        Password::defaults(function () {
            return Password::min(8)->mixedCase()->numbers()->uncompromised();
        });

        // Daftarkan driver "gmail_api" (dipakai MAIL_MAILER=gmail_api).
        Mail::extend('gmail_api', fn () => new GmailApiTransport($this->app->make(GmailApiService::class)));

        if (! config('mail.mailers.gmail_api')) {
            config(['mail.mailers.gmail_api' => ['transport' => 'gmail_api']]);
        }

        // Batas laju default untuk routes/api.php (60 req/menit per token/user, atau per IP).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
