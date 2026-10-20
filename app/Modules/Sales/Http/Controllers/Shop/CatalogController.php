<?php

namespace App\Modules\Sales\Http\Controllers\Shop;

use App\Modules\Warehouse\Contracts\StockGateway;
use App\Modules\Warehouse\Models\Category;
use App\Modules\Warehouse\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CatalogController extends ShopBase
{
    public function __construct(private StockGateway $stock)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->only(['q', 'category_id']);

        $products = Product::query()->active()->where('is_sellable_online', true)->with('category:id,name')
            ->search($filters['q'] ?? null)
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->orderBy('name')
            ->paginate((int) config('cemwp.sales.shop_per_page', 24))->withQueryString();

        $stock = $this->stock->availabilityMap($products->pluck('id')->all(), Warehouse::onlineId());
        $cap = (int) config('cemwp.warehouse.public_stock_cap', 50);
        $products->through(fn (Product $p) => [
            'id' => $p->id, 'name' => $p->name, 'price' => (float) $p->selling_price, 'unit' => $p->unit,
            'category' => $p->category?->name,
            'stock' => $p->track_stock ? min($cap, max(0, $stock[$p->id] ?? 0)) : null, // null = selalu tersedia
        ]);

        return Inertia::render('Shop/Home', [
            'products' => $products, 'filters' => $filters,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active && $product->is_sellable_online, 404);

        $avail = $this->stock->availability($product->id, Warehouse::onlineId());
        $cap = (int) config('cemwp.warehouse.public_stock_cap', 50);

        return Inertia::render('Shop/Product', [
            'product' => [
                'id' => $product->id, 'name' => $product->name, 'description' => $product->description, 'price' => (float) $product->selling_price,
                'unit' => $product->unit, 'weight_grams' => $product->weight_grams, 'category' => $product->category?->name,
                'stock' => $product->track_stock ? min($cap, max(0, $avail)) : null,
            ],
        ]);
    }
}
