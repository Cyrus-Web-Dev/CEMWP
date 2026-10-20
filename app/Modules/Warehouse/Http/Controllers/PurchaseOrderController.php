<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Models\PurchaseOrder;
use App\Modules\Warehouse\Models\Supplier;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Services\PurchasingService;
use App\Modules\Warehouse\Support\Like;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PurchaseOrderController extends WarehouseController
{
    public function __construct(private PurchasingService $purchasing)
    {
        $this->middleware('permission:purchasing.view')->only(['index', 'show']);
        $this->middleware('permission:purchasing.manage')->only(['create', 'store', 'edit', 'update', 'place', 'cancel', 'close']);
        $this->middleware('permission:purchasing.receive')->only(['receive']);
    }

    public function index(Request $request)
    {
        $filters = $request->only(['q', 'status']);

        $orders = PurchaseOrder::query()
            ->with(['supplier:id,name', 'warehouse:id,code,name', 'items:id,purchase_order_id,qty_ordered,qty_received,unit_cost'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['q'] ?? null, function ($query, $q) {
                $like = Like::contains($q);
                $query->where(fn ($w) => $w->where('number', 'ilike', $like)->orWhereHas('supplier', fn ($s) => $s->where('name', 'ilike', $like)));
            })
            ->orderByDesc('id')
            ->paginate((int) config('cemwp.warehouse.per_page', 25))
            ->withQueryString()
            ->through(fn (PurchaseOrder $po) => [
                'id' => $po->id, 'number' => $po->number, 'status' => $po->status,
                'supplier' => $po->supplier?->name, 'warehouse' => $po->warehouse?->name,
                'ordered_at' => $po->ordered_at?->toDateString(), 'expected_at' => $po->expected_at?->toDateString(),
                'lines' => $po->items->count(),
                'total' => round($po->items->sum(fn ($i) => $i->qty_ordered * (float) $i->unit_cost), 2),
            ]);

        return Inertia::render('Warehouse/PurchaseOrders/Index', [
            'orders' => $orders,
            'filters' => $filters,
            'statuses' => PurchaseOrder::STATUS_LABELS,
        ]);
    }

    public function create()
    {
        return Inertia::render('Warehouse/PurchaseOrders/Form', $this->formProps(null));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return $this->attempt(
            fn () => $this->purchasing->save(null, $data, $request->user()->id),
            'PO dibuat sebagai draf.',
            fn (PurchaseOrder $po) => redirect()->route('warehouse.purchase-orders.show', $po)
        );
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'warehouse:id,code,name', 'items.product:id,sku,name,unit', 'receipts:id,purchase_order_id,number,received_at', 'creator:id,name']);

        return Inertia::render('Warehouse/PurchaseOrders/Show', [
            'order' => $purchaseOrder,
            'statuses' => PurchaseOrder::STATUS_LABELS,
            'requestKey' => (string) Str::uuid(), // token anti klik-ganda untuk form penerimaan
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== PurchaseOrder::DRAFT) {
            return redirect()->route('warehouse.purchase-orders.show', $purchaseOrder)->with('error', 'Hanya PO berstatus draf yang bisa diubah.');
        }

        return Inertia::render('Warehouse/PurchaseOrders/Form', $this->formProps($purchaseOrder));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $data = $this->validated($request);

        return $this->attempt(
            fn () => $this->purchasing->save($purchaseOrder, $data, $request->user()->id),
            'PO diperbarui.',
            fn (PurchaseOrder $po) => redirect()->route('warehouse.purchase-orders.show', $po)
        );
    }

    public function place(Request $request, PurchaseOrder $purchaseOrder)
    {
        return $this->attempt(fn () => $this->purchasing->place($purchaseOrder, $request->user()->id), 'PO ditandai sudah dipesan ke supplier.');
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder)
    {
        return $this->attempt(fn () => $this->purchasing->cancel($purchaseOrder, $request->user()->id), 'PO dibatalkan.');
    }

    public function close(Request $request, PurchaseOrder $purchaseOrder)
    {
        return $this->attempt(fn () => $this->purchasing->close($purchaseOrder, $request->user()->id), 'PO ditutup.');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder)
    {
        $data = $request->validate([
            'request_key' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        return $this->attempt(
            fn () => $this->purchasing->receive($purchaseOrder, $data['quantities'], $data['notes'] ?? null, $request->user()->id, $data['request_key']),
            'Barang diterima dan stok bertambah.'
        );
    }

    private function formProps(?PurchaseOrder $po): array
    {
        return [
            'order' => $po?->load('items.product:id,sku,name,unit'),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'warehouses' => Warehouse::active()->orderByDesc('is_default')->orderBy('name')->get(['id', 'code', 'name', 'is_default']),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'expected_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999999'],
        ]);
    }
}
