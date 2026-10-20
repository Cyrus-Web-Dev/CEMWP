<?php

namespace App\Modules\Warehouse\Models;

use App\Models\User;
use App\Modules\Warehouse\Enums\MovementType;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Baris buku besar stok. Append-only: database menolak UPDATE/DELETE (trigger). */
class StockMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id', 'warehouse_id', 'type', 'quantity', 'qty_before', 'qty_after', 'unit_cost', 'reason',
        'reference_type', 'reference_id', 'reference_number', 'idempotency_key', 'note', 'user_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'integer',
            'qty_before' => 'integer',
            'qty_after' => 'integer',
            'unit_cost' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
