<?php

namespace App\Modules\Warehouse\Support;

class Like
{
    /** Escape karakter wildcard (% _ \) agar input pencarian pengguna diperlakukan sebagai teks biasa. */
    public static function contains(string $term): string
    {
        return '%'.addcslashes(trim($term), '%_\\').'%';
    }
}
