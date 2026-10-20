<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Warehouse\Exceptions\StockException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/** Dasar semua controller Warehouse: menerjemahkan kesalahan bisnis (StockException) menjadi pesan di layar. */
abstract class WarehouseController extends Controller
{
    /**
     * Jalankan aksi; sukses -> redirect + pesan sukses, StockException -> kembali dengan pesan error.
     *
     * @param  callable():mixed  $action
     * @param  (callable(mixed):RedirectResponse)|null  $then  menentukan tujuan redirect dari hasil aksi
     */
    protected function attempt(callable $action, string $success, ?callable $then = null): RedirectResponse
    {
        try {
            $result = $action();
        } catch (StockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return ($then ? $then($result) : back())->with('success', $success);
    }

    /** Slug unik: "Kopi Bubuk" -> "kopi-bubuk", "kopi-bubuk-x7k2" bila sudah dipakai. */
    protected function uniqueSlug(string $name, string $table, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        while (\Illuminate\Support\Facades\DB::table($table)->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
