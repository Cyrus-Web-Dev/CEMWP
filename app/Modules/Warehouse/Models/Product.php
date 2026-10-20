<?php

namespace App\Modules\Warehouse\Models;

use App\Modules\Warehouse\Support\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    /** Subquery total stok semua lokasi (dipakai filter stok menipis / habis). */
    public const TOTAL_STOCK_SQL = 'COALESCE((SELECT SUM(i.qty_on_hand) FROM inventories i WHERE i.product_id = products.id), 0)';

    protected $fillable = [
        'sku', 'barcode', 'name', 'category_id', 'description', 'unit', 'cost_price', 'selling_price',
        'min_stock', 'weight_grams', 'is_active', 'is_sellable_online', 'track_stock', 'image_path',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'min_stock' => 'integer',
            'weight_grams' => 'integer',
            'is_active' => 'boolean',
            'is_sellable_online' => 'boolean',
            'track_stock' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Cari di nama / SKU / barcode (PostgreSQL ILIKE, tidak peka huruf besar-kecil). */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term || trim($term) === '') {
            return $query;
        }
        $like = Like::contains($term);

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'ilike', $like)
                ->orWhere('sku', 'ilike', $like)
                ->orWhere('barcode', 'ilike', $like);
        });
    }

    /** Tambahkan kolom stock_on_hand, stock_reserved, stock_available (jumlah semua lokasi). */
    public function scopeWithStock(Builder $query): Builder
    {
        return $query
            ->withSum('inventories as stock_on_hand', 'qty_on_hand')
            ->withSum('inventories as stock_reserved', 'qty_reserved');
    }
}
