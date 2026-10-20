<?php

namespace App\Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    protected $table = 'inventories';

    protected $fillable = ['product_id', 'warehouse_id', 'qty_on_hand', 'qty_reserved'];

    protected function casts(): array
    {
        return ['qty_on_hand' => 'integer', 'qty_reserved' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getQtyAvailableAttribute(): int
    {
        return $this->qty_on_hand - $this->qty_reserved;
    }
}
