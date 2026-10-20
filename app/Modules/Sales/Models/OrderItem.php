<?php

namespace App\Modules\Sales\Models;

use App\Modules\Warehouse\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'sku', 'name', 'unit_price', 'cost_price', 'quantity', 'discount', 'line_total', 'weight_grams'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2', 'cost_price' => 'decimal:2', 'discount' => 'decimal:2', 'line_total' => 'decimal:2',
            'quantity' => 'integer', 'weight_grams' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }
}
