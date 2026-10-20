<?php

namespace App\Modules\Warehouse\Services;

use App\Modules\Warehouse\Models\Fulfillment;
use App\Modules\Warehouse\Models\Product;
use App\Modules\Warehouse\Models\PurchaseOrder;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\StockTransfer;
use Illuminate\Support\Facades\DB;

/** Angka ringkas untuk dashboard modul Warehouse. */
class DashboardMetrics
{
    public function summary(): array
    {
        $stockValue = (float) DB::table('inventories')
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->whereNull('products.deleted_at')
            ->sum(DB::raw('inventories.qty_on_hand * products.cost_price'));

        $tracked = fn () => Product::query()->where('is_active', true)->where('track_stock', true);

        return [
            'products' => Product::query()->where('is_active', true)->count(),
            'stock_value' => round($stockValue, 2),
            'units_on_hand' => (int) DB::table('inventories')->sum('qty_on_hand'),
            'units_reserved' => (int) DB::table('inventories')->sum('qty_reserved'),
            'low_stock' => $tracked()->where('min_stock', '>', 0)->whereRaw(Product::TOTAL_STOCK_SQL.' <= products.min_stock')->count(),
            'out_of_stock' => $tracked()->whereRaw(Product::TOTAL_STOCK_SQL.' <= 0')->count(),
            'fulfillments_pending' => Fulfillment::query()->where('status', 'pending')->count(),
            'fulfillments_active' => Fulfillment::query()->whereIn('status', ['picking', 'packed'])->count(),
            'transfers_in_transit' => StockTransfer::query()->where('status', StockTransfer::IN_TRANSIT)->count(),
            'purchase_orders_open' => PurchaseOrder::query()->whereIn('status', [PurchaseOrder::ORDERED, PurchaseOrder::PARTIAL])->count(),
        ];
    }

    public function lowStockProducts(int $limit = 8)
    {
        return Product::query()
            ->where('is_active', true)->where('track_stock', true)->where('min_stock', '>', 0)
            ->whereRaw(Product::TOTAL_STOCK_SQL.' <= products.min_stock')
            ->withStock()
            ->orderByRaw(Product::TOTAL_STOCK_SQL.' asc')
            ->limit($limit)
            ->get(['id', 'sku', 'name', 'min_stock', 'unit']);
    }

    public function recentMovements(int $limit = 10)
    {
        return StockMovement::query()
            ->with(['product:id,sku,name', 'warehouse:id,code,name'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
