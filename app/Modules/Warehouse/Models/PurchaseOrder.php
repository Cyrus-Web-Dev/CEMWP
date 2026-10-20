<?php

namespace App\Modules\Warehouse\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    public const DRAFT = 'draft';
    public const ORDERED = 'ordered';
    public const PARTIAL = 'partial';
    public const RECEIVED = 'received';
    public const CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::DRAFT => 'Draf', self::ORDERED => 'Dipesan', self::PARTIAL => 'Diterima sebagian',
        self::RECEIVED => 'Selesai', self::CANCELLED => 'Dibatalkan',
    ];

    protected $fillable = ['number', 'supplier_id', 'warehouse_id', 'status', 'ordered_at', 'expected_at', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['ordered_at' => 'date:Y-m-d', 'expected_at' => 'date:Y-m-d'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isReceivable(): bool
    {
        return in_array($this->status, [self::ORDERED, self::PARTIAL], true);
    }
}
