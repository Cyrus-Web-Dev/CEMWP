<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Models\GoodsReceipt;
use App\Modules\Warehouse\Models\Supplier;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Services\PurchasingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/** Bukti penerimaan barang (GRN) + penerimaan langsung tanpa PO. */
class ReceiptController extends WarehouseController
{
    public function __construct(private PurchasingService $purchasing)
    {
        $this->middleware('permission:purchasing.view')->only(['index', 'show']);
        $this->middleware('permission:purchasing.receive')->only(['create', 'store']);
    }

    public function index()
    {
        return Inertia::render('Warehouse/Receipts/Index', [
            'receipts' => GoodsReceipt::query()
                ->with(['warehouse:id,code,name', 'supplier:id,name', 'purchaseOrder:id,number'])
                ->withSum('items as total_units', 'quantity')
                ->orderByDesc('id')
                ->paginate((int) config('cemwp.warehouse.per_page', 25)),
        ]);
    }

    public function create()
    {
        return Inertia::render('Warehouse/Receipts/Create', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'warehouses' => Warehouse::active()->orderByDesc('is_default')->orderBy('name')->get(['id', 'code', 'name', 'is_default']),
            'requestKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'request_key' => ['required', 'string', 'max:64'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999999'],
        ]);

        return $this->attempt(
            fn () => $this->purchasing->receiveDirect($data, $request->user()->id, $data['request_key']),
            'Barang diterima dan stok bertambah.',
            fn (GoodsReceipt $r) => redirect()->route('warehouse.receipts.show', $r)
        );
    }

    public function show(GoodsReceipt $receipt)
    {
        return Inertia::render('Warehouse/Receipts/Show', [
            'receipt' => $receipt->load(['warehouse:id,code,name', 'supplier:id,name', 'purchaseOrder:id,number', 'receiver:id,name', 'items.product:id,sku,name,unit']),
        ]);
    }
}
