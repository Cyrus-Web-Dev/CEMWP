<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;

class CouponRedemption extends Model
{
    public $timestamps = false;

    protected $fillable = ['coupon_id', 'order_id', 'customer_id', 'amount', 'created_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'created_at' => 'datetime'];
    }
}
