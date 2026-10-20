<?php

namespace App\Modules\Warehouse\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpname extends Model
{
    public const DRAFT = 'draft';
    public const FINALIZED = 'finalized';
    public const CANCELLED = 'cancelled';

    public const STATUS_LABELS = [self::DRAFT => 'Sedang dihitung', self::FINALIZED => 'Final', self::CANCELLED => 'Dibatalkan'];

    protected $fillable = ['number', 'warehouse_id', 'status', 'notes', 'created_by', 'finalized_by', 'finalized_at'];

    protected function casts(): array
    {
        return ['finalized_at' => 'datetime'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
