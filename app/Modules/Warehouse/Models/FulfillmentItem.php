<?php

namespace App\Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentItem extends Model
{
    protected $fillable = ['fulfillment_id', 'product_id', 'sku', 'product_name', 'quantity', 'qty_picked'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'qty_picked' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
