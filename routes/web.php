<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

// Halaman depan = etalase toko online. Staf masuk lewat /login (atau langsung /dashboard).
Route::get('/', fn () => redirect()->route('shop.home'))->name('home');

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Halaman internal inti (login + akun aktif + punya role).
| Rute tiap modul bisnis didaftarkan oleh service provider modulnya
| (lihat app/Modules/Warehouse/routes/web.php).
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'has.role'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::put('/users/{user}/role', [UserManagementController::class, 'updateRole'])->name('users.role');
    Route::post('/users/{user}/toggle-active', [UserManagementController::class, 'toggleActive'])->name('users.toggle-active');
    Route::put('/users/{user}/password', [UserManagementController::class, 'resetPassword'])->name('users.password');

    Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');
});
