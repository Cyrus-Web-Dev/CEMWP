<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Models\Category;
use App\Modules\Warehouse\Models\Inventory;
use App\Modules\Warehouse\Models\Product;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\StockReservation;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProductController extends WarehouseController
{
    public function __construct()
    {
        $this->middleware('permission:products.view')->only(['index', 'show', 'search']);
        $this->middleware('permission:products.manage')->except(['index', 'show', 'search']);
    }

    public function index(Request $request)
    {
        $filters = $request->only(['q', 'category_id', 'status']);

        $products = Product::query()
            ->with('category:id,name')
            ->withStock()
            ->search($filters['q'] ?? null)
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['status'] ?? null, function ($q, $status) {
                return match ($status) {
                    'active' => $q->where('is_active', true),
                    'inactive' => $q->where('is_active', false),
                    'low' => $q->where('track_stock', true)->where('min_stock', '>', 0)->whereRaw(Product::TOTAL_STOCK_SQL.' <= products.min_stock'),
                    'out' => $q->where('track_stock', true)->whereRaw(Product::TOTAL_STOCK_SQL.' <= 0'),
                    default => $q,
                };
            })
            ->orderBy('name')
            ->paginate((int) config('cemwp.warehouse.per_page', 25))
            ->withQueryString();

        return Inertia::render('Warehouse/Products/Index', [
            'products' => $products,
            'filters' => $filters,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Warehouse/Products/Form', [
            'product' => null,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $product = Product::create($data);
        ActivityLogger::log('product.created', "Produk baru: {$product->sku} - {$product->name}", $product);

        return redirect()->route('warehouse.products.show', $product)->with('success', 'Produk berhasil ditambahkan. Stok awal dimasukkan lewat Penerimaan Barang.');
    }

    public function show(Product $product)
    {
        $product->load('category:id,name');

        return Inertia::render('Warehouse/Products/Show', [
            'product' => $product,
            'inventories' => Inventory::query()->with('warehouse:id,code,name,type')->where('product_id', $product->id)->get(),
            'movements' => StockMovement::query()
                ->with(['warehouse:id,code,name', 'user:id,name'])
                ->where('product_id', $product->id)
                ->orderByDesc('id')->limit(25)->get(),
        ]);
    }

    public function edit(Product $product)
    {
        return Inertia::render('Warehouse/Products/Form', [
            'product' => $product,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);

        // Mematikan pelacakan stok saat masih ada stok akan membuat stok "menggantung" tanpa kendali.
        if ($product->track_stock && ! $data['track_stock'] && Inventory::where('product_id', $product->id)->sum('qty_on_hand') > 0) {
            throw ValidationException::withMessages(['track_stock' => 'Pelacakan stok tidak bisa dimatikan selagi masih ada stok. Nol-kan stok dulu (penyesuaian/opname).']);
        }

        $product->update($data);
        ActivityLogger::log('product.updated', "Produk diperbarui: {$product->sku} - {$product->name}", $product);

        return redirect()->route('warehouse.products.show', $product)->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product)
    {
        $onHand = (int) Inventory::where('product_id', $product->id)->sum('qty_on_hand');
        $reserved = StockReservation::active()->where('product_id', $product->id)->exists();
        if ($onHand > 0 || $reserved) {
            return back()->with('error', 'Produk masih punya stok atau reservasi aktif, jadi tidak bisa dihapus. Nonaktifkan saja, atau nol-kan stoknya dulu.');
        }

        $product->delete();
        ActivityLogger::log('product.deleted', "Produk dihapus: {$product->sku} - {$product->name}", $product);

        return redirect()->route('warehouse.products.index')->with('success', 'Produk dihapus.');
    }

    /** JSON untuk kotak pencarian produk di form PO / transfer / penerimaan. */
    public function search(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'warehouse_id' => ['nullable', 'integer']]);

        $products = Product::query()
            ->active()
            ->where('track_stock', true)
            ->search($request->input('q'))
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'sku', 'barcode', 'name', 'unit', 'cost_price', 'selling_price']);

        if ($warehouseId = $request->integer('warehouse_id')) {
            $available = Inventory::query()
                ->where('warehouse_id', $warehouseId)
                ->whereIn('product_id', $products->pluck('id'))
                ->get(['product_id', 'qty_on_hand', 'qty_reserved'])
                ->keyBy('product_id');
            $products->each(function ($p) use ($available) {
                $row = $available->get($p->id);
                $p->setAttribute('available', $row ? $row->qty_on_hand - $row->qty_reserved : 0);
            });
        }

        return response()->json($products);
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        // Barcode kosong dari formulir = tidak ada barcode (jangan disimpan sebagai string kosong; unik).
        $request->merge(['barcode' => $request->filled('barcode') ? trim((string) $request->input('barcode')) : null]);

        return $request->validate([
            'sku' => ['required', 'string', 'max:40', Rule::unique('products', 'sku')->ignore($product?->id)],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products', 'barcode')->ignore($product?->id)],
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'unit' => ['required', 'string', 'max:20'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'weight_grams' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'is_sellable_online' => ['required', 'boolean'],
            'track_stock' => ['required', 'boolean'],
        ]);
    }
}
