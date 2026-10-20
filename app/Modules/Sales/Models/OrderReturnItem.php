<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturnItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_return_id', 'order_item_id', 'product_id', 'quantity', 'amount'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'amount' => 'decimal:2'];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
