<?php

namespace App\Modules\Sales\Models;

use App\Modules\Crm\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'value', 'min_subtotal', 'max_discount', 'channel', 'starts_at', 'ends_at',
        'usage_limit', 'usage_limit_per_customer', 'customer_id', 'campaign_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2', 'min_subtotal' => 'decimal:2', 'max_discount' => 'decimal:2',
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean',
            'usage_limit' => 'integer', 'usage_limit_per_customer' => 'integer', 'used_count' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }
}
