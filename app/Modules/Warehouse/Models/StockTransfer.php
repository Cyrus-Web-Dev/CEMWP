<?php

namespace App\Modules\Warehouse\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    public const DRAFT = 'draft';
    public const IN_TRANSIT = 'in_transit';
    public const RECEIVED = 'received';
    public const CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::DRAFT => 'Draf', self::IN_TRANSIT => 'Dalam perjalanan', self::RECEIVED => 'Diterima', self::CANCELLED => 'Dibatalkan',
    ];

    protected $fillable = ['number', 'from_warehouse_id', 'to_warehouse_id', 'status', 'notes', 'created_by', 'shipped_at', 'received_at'];

    protected function casts(): array
    {
        return ['shipped_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
