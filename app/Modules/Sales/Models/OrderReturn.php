<?php

namespace App\Modules\Sales\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderReturn extends Model
{
    public $timestamps = false;

    protected $fillable = ['number', 'order_id', 'user_id', 'shift_id', 'reason', 'refund_method', 'refund_amount', 'restocked', 'created_at'];

    protected function casts(): array
    {
        return ['refund_amount' => 'decimal:2', 'restocked' => 'boolean', 'created_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }
}
