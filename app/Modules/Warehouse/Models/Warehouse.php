<?php

namespace App\Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    public const TYPES = ['warehouse' => 'Gudang', 'store' => 'Toko / outlet', 'online' => 'Stok online'];

    protected $fillable = ['code', 'name', 'type', 'address', 'is_active', 'is_default'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_default' => 'boolean'];
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * Lokasi stok yang dijual lewat e-commerce / aplikasi mobile: lokasi aktif bertipe "online";
     * bila belum ada, memakai lokasi bawaan. (Tanpa cache statis agar aman di test & worker.)
     */
    public static function onlineId(): ?int
    {
        $id = static::query()->active()->where('type', 'online')->orderBy('id')->value('id');

        return $id ?? static::query()->active()->where('is_default', true)->value('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
