<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Models\Inventory;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Services\StockService;
use App\Modules\Warehouse\Support\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InventoryController extends WarehouseController
{
    public const ADJUST_REASONS = [
        'damaged' => 'Rusak', 'lost' => 'Hilang', 'expired' => 'Kedaluwarsa', 'found' => 'Ditemukan',
        'correction' => 'Koreksi data', 'sample' => 'Sampel / promosi', 'other' => 'Lainnya',
    ];

    public function __construct(private StockService $stock)
    {
        $this->middleware('permission:inventory.view')->only(['index', 'movements']);
        $this->middleware('permission:inventory.export')->only(['export']);
        $this->middleware('permission:inventory.adjust')->only(['adjust']);
    }

    public function index(Request $request)
    {
        $filters = $request->only(['q', 'warehouse_id', 'status']);
        $base = $this->filteredInventory($filters);

        $totals = (clone $base)->toBase()->selectRaw(
            'COALESCE(SUM(inventories.qty_on_hand),0) AS units, COALESCE(SUM(inventories.qty_reserved),0) AS reserved, COALESCE(SUM(inventories.qty_on_hand * products.cost_price),0) AS value'
        )->first();

        $rows = (clone $base)
            ->select('inventories.*')
            ->with(['product:id,sku,name,unit,min_stock,cost_price', 'warehouse:id,code,name'])
            ->orderBy('products.name')->orderBy('inventories.warehouse_id')
            ->paginate((int) config('cemwp.warehouse.per_page', 25))
            ->withQueryString();

        return Inertia::render('Warehouse/Inventory/Index', [
            'inventories' => $rows,
            'filters' => $filters,
            'warehouses' => Warehouse::active()->orderBy('name')->get(['id', 'code', 'name']),
            'totals' => ['units' => (int) $totals->units, 'reserved' => (int) $totals->reserved, 'value' => (float) $totals->value],
            'reasons' => self::ADJUST_REASONS,
        ]);
    }

    public function movements(Request $request)
    {
        $filters = $request->only(['q', 'warehouse_id', 'type', 'from', 'to']);

        $movements = StockMovement::query()
            ->with(['product:id,sku,name', 'warehouse:id,code,name', 'user:id,name'])
            ->when($filters['q'] ?? null, function (Builder $query, $q) {
                $like = Like::contains($q);
                $query->where(function (Builder $w) use ($like) {
                    $w->where('reference_number', 'ilike', $like)
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'ilike', $like)->orWhere('sku', 'ilike', $like));
                });
            })
            ->when($filters['warehouse_id'] ?? null, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->orderByDesc('id')
            ->paginate((int) config('cemwp.warehouse.per_page', 25))
            ->withQueryString();

        return Inertia::render('Warehouse/Inventory/Movements', [
            'movements' => $movements,
            'filters' => $filters,
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'code', 'name']),
            'types' => collect(MovementType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]),
        ]);
    }

    public function export(Request $request)
    {
        $base = $this->filteredInventory($request->only(['q', 'warehouse_id', 'status']));
        $filename = 'stok-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($base) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8 dengan benar
            fputcsv($out, ['SKU', 'Produk', 'Lokasi', 'Stok fisik', 'Dicadangkan', 'Tersedia', 'Harga pokok', 'Nilai stok']);
            (clone $base)
                ->select('inventories.*')
                ->with(['product:id,sku,name,cost_price', 'warehouse:id,code'])
                ->orderBy('products.name')->orderBy('inventories.id')
                ->chunk(500, function ($chunk) use ($out) {
                    foreach ($chunk as $row) {
                        fputcsv($out, [
                            $row->product->sku, $row->product->name, $row->warehouse->code,
                            $row->qty_on_hand, $row->qty_reserved, $row->qty_on_hand - $row->qty_reserved,
                            $row->product->cost_price, round($row->qty_on_hand * (float) $row->product->cost_price, 2),
                        ]);
                    }
                });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function adjust(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', Rule::in(array_keys(self::ADJUST_REASONS))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $delta = $data['direction'] === 'out' ? -$data['quantity'] : $data['quantity'];

        return $this->attempt(
            fn () => $this->stock->adjust($data['product_id'], $data['warehouse_id'], $delta, $data['reason'], $data['note'] ?? null, $request->user()->id),
            'Penyesuaian stok dicatat.'
        );
    }

    /** Query dasar stok per (produk, lokasi) dengan filter; produk yang sudah dihapus tidak ditampilkan. */
    private function filteredInventory(array $filters): Builder
    {
        return Inventory::query()
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->whereNull('products.deleted_at')
            ->when($filters['q'] ?? null, function (Builder $query, $q) {
                $like = Like::contains($q);
                $query->where(fn (Builder $w) => $w->where('products.name', 'ilike', $like)->orWhere('products.sku', 'ilike', $like)->orWhere('products.barcode', 'ilike', $like));
            })
            ->when($filters['warehouse_id'] ?? null, fn (Builder $q, $id) => $q->where('inventories.warehouse_id', $id))
            ->when($filters['status'] ?? null, function (Builder $q, $status) {
                return match ($status) {
                    'low' => $q->where('products.min_stock', '>', 0)->whereColumn('inventories.qty_on_hand', '<=', 'products.min_stock'),
                    'out' => $q->where('inventories.qty_on_hand', '<=', 0),
                    'reserved' => $q->where('inventories.qty_reserved', '>', 0),
                    default => $q,
                };
            });
    }
}
