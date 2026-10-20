<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Models\StockOpname;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Services\OpnameService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OpnameController extends WarehouseController
{
    public function __construct(private OpnameService $opnames)
    {
        $this->middleware('permission:opname.view')->only(['index', 'show']);
        $this->middleware('permission:opname.manage')->only(['store', 'counts', 'cancel']);
        $this->middleware('permission:opname.finalize')->only(['finalize']);
    }

    public function index()
    {
        return Inertia::render('Warehouse/Opnames/Index', [
            'opnames' => StockOpname::query()
                ->with(['warehouse:id,code,name', 'creator:id,name'])
                ->withCount(['items', 'items as counted_count' => fn ($q) => $q->whereNotNull('counted_qty')])
                ->orderByDesc('id')
                ->paginate((int) config('cemwp.warehouse.per_page', 25)),
            'warehouses' => Warehouse::active()->orderBy('name')->get(['id', 'code', 'name']),
            'statuses' => StockOpname::STATUS_LABELS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->attempt(
            fn () => $this->opnames->start($data['warehouse_id'], $data['notes'] ?? null, $request->user()->id),
            'Stok opname dimulai. Isi hasil hitung fisik tiap produk.',
            fn (StockOpname $o) => redirect()->route('warehouse.opnames.show', $o)
        );
    }

    public function show(StockOpname $opname)
    {
        $opname->load(['warehouse:id,code,name', 'creator:id,name', 'finalizer:id,name']);

        return Inertia::render('Warehouse/Opnames/Show', [
            'opname' => $opname,
            'items' => $opname->items()->with('product:id,sku,barcode,name,unit')->get()->sortBy(fn ($i) => $i->product?->name)->values(),
            'statuses' => StockOpname::STATUS_LABELS,
        ]);
    }

    public function counts(Request $request, StockOpname $opname)
    {
        $data = $request->validate([
            'counts' => ['required', 'array'],
            'counts.*' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ]);

        return $this->attempt(fn () => $this->opnames->saveCounts($opname, $data['counts']), 'Hasil hitung disimpan.');
    }

    public function finalize(Request $request, StockOpname $opname)
    {
        return $this->attempt(fn () => $this->opnames->finalize($opname, $request->user()->id), 'Stok opname final. Stok sistem sudah disesuaikan.');
    }

    public function cancel(Request $request, StockOpname $opname)
    {
        return $this->attempt(fn () => $this->opnames->cancel($opname, $request->user()->id), 'Stok opname dibatalkan.');
    }
}
