<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    public const KINDS = ['cash' => 'Tunai', 'manual' => 'Manual (dikonfirmasi petugas)', 'gateway' => 'Gateway otomatis', 'cod' => 'Bayar di tempat (COD)'];

    protected $fillable = ['code', 'name', 'kind', 'for_pos', 'for_online', 'requires_reference', 'is_active', 'sort_order', 'instructions'];

    protected function casts(): array
    {
        return ['for_pos' => 'boolean', 'for_online' => 'boolean', 'requires_reference' => 'boolean', 'is_active' => 'boolean'];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForPos(Builder $q): Builder
    {
        return $q->active()->where('for_pos', true)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeForOnline(Builder $q): Builder
    {
        return $q->active()->where('for_online', true)->orderBy('sort_order')->orderBy('id');
    }
}
