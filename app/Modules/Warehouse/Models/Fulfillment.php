<?php

namespace App\Modules\Warehouse\Models;

use App\Models\User;
use App\Modules\Warehouse\Enums\FulfillmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fulfillment extends Model
{
    protected $fillable = [
        'number', 'source_type', 'source_id', 'source_number', 'channel', 'warehouse_id', 'status', 'priority',
        'recipient_name', 'recipient_phone', 'shipping_address', 'courier', 'tracking_number', 'notes', 'assigned_to',
        'picked_at', 'packed_at', 'shipped_at', 'delivered_at', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => FulfillmentStatus::class,
            'picked_at' => 'datetime', 'packed_at' => 'datetime', 'shipped_at' => 'datetime',
            'delivered_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FulfillmentItem::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
