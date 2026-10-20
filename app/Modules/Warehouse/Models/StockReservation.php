<?php

namespace App\Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    public const ACTIVE = 'active';
    public const COMMITTED = 'committed';
    public const RELEASED = 'released';
    public const EXPIRED = 'expired';

    protected $fillable = ['product_id', 'warehouse_id', 'reference', 'quantity', 'status', 'expires_at', 'resolved_at'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'expires_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::ACTIVE);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
