<?php

namespace App\Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    protected $fillable = ['stock_opname_id', 'product_id', 'system_qty', 'counted_qty', 'difference'];

    protected function casts(): array
    {
        return ['system_qty' => 'integer', 'counted_qty' => 'integer', 'difference' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
