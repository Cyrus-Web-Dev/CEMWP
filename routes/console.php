<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lepaskan reservasi stok e-commerce yang melewati batas waktu pembayaran.
Schedule::command('cemwp:expire-reservations')->everyMinute()->withoutOverlapping();

// Kedaluwarsakan pesanan online yang tidak dibayar tepat waktu (stok & kupon dilepas).
Schedule::command('cemwp:expire-orders')->everyMinute()->withoutOverlapping();

// Rekonsiliasi saldo stok vs ledger setiap malam; gagal = ada selisih (cek storage/logs).
Schedule::command('cemwp:stock-verify')->dailyAt('02:00');
