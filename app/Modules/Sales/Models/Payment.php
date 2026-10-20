<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const EXPIRED = 'expired';
    public const REFUND_PENDING = 'refund_pending';
    public const REFUNDED = 'refunded';

    protected $fillable = [
        'order_id', 'payment_method_id', 'method_code', 'method_name', 'kind', 'amount', 'tendered', 'status', 'reference',
        'gateway', 'gateway_ref', 'redirect_url', 'gateway_payload', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'tendered' => 'decimal:2', 'gateway_payload' => 'array', 'paid_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
