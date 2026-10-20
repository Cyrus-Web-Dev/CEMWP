<?php

namespace App\Modules\Warehouse\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Warehouse\Contracts\StockGateway;
use App\Modules\Warehouse\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Katalog PUBLIK untuk aplikasi mobile (Project 5) dan etalase. Hanya baca; stok yang tampil dibatasi (public_stock_cap).
 * Ketersediaan dihitung dari lokasi stok online (Warehouse::onlineId()) kecuali warehouse_id diberikan.
 *
 * GET /api/v1/catalog/products?updated_since=2026-10-01T00:00:00Z&per_page=200&cursor=...
 *   -> sinkronisasi DELTA: hanya yang berubah sejak waktu itu, termasuk yang dihapus (deleted_at terisi).
 * GET /api/v1/catalog/availability?product_ids[]=1&product_ids[]=2&warehouse_id=1
 */
class CatalogController extends Controller
{
    public function products(Request $request): JsonResponse
    {
        $data = $request->validate([
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        $query = Product::query()->with('category:id,name,slug');

        if (! empty($data['updated_since'])) {
            // Delta: ikutkan produk yang dihapus agar klien bisa menghapusnya dari cache SQLite.
            $query->withTrashed()->where('updated_at', '>', $data['updated_since']);
        } else {
            $query->where('is_active', true)->where('is_sellable_online', true);
        }

        // Keset pagination berbasis kursor + urutan stabil (updated_at, id) agar tidak ada baris terlewat.
        $page = $query->orderBy('updated_at')->orderBy('id')->cursorPaginate($data['per_page'] ?? 200)->withQueryString();

        $stock = app(StockGateway::class)->availabilityMap(
            $page->getCollection()->pluck('id')->all(),
            $data['warehouse_id'] ?? Warehouse::onlineId()
        );
        $cap = (int) config('cemwp.warehouse.public_stock_cap', 50);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'name' => $p->name,
                'description' => $p->description,
                'category' => $p->category ? ['id' => $p->category->id, 'name' => $p->category->name] : null,
                'unit' => $p->unit,
                'price' => (float) $p->selling_price,
                'weight_grams' => $p->weight_grams,
                'is_active' => $p->is_active,
                'is_sellable_online' => $p->is_sellable_online,
                'track_stock' => $p->track_stock,
                'available' => $p->track_stock ? min($cap, max(0, $stock[$p->id] ?? 0)) : null,
                'updated_at' => $p->updated_at?->toIso8601String(),
                'deleted_at' => $p->deleted_at?->toIso8601String(),
            ])->values(),
            'next_cursor' => $page->nextCursor()?->encode(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:200'],
            'product_ids.*' => ['integer'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        return response()->json([
            'data' => array_map(
                fn ($n) => min((int) config('cemwp.warehouse.public_stock_cap', 50), max(0, $n)),
                app(StockGateway::class)->availabilityMap($data['product_ids'], $data['warehouse_id'] ?? Warehouse::onlineId())
            ),
            'server_time' => now()->toIso8601String(),
        ]);
    }

}
