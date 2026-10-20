<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Models\Inventory;
use App\Modules\Warehouse\Models\StockTransfer;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Services\TransferService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransferController extends WarehouseController
{
    public function __construct(private TransferService $transfers)
    {
        $this->middleware('permission:transfers.view')->only(['index', 'show']);
        $this->middleware('permission:transfers.manage')->only(['create', 'store', 'ship', 'cancel']);
        $this->middleware('permission:transfers.receive')->only(['receive']);
    }

    public function index(Request $request)
    {
        $status = $request->input('status');

        return Inertia::render('Warehouse/Transfers/Index', [
            'transfers' => StockTransfer::query()
                ->with(['from:id,code,name', 'to:id,code,name'])
                ->withSum('items as total_units', 'quantity')
                ->when($status, fn ($q, $s) => $q->where('status', $s))
                ->orderByDesc('id')
                ->paginate((int) config('cemwp.warehouse.per_page', 25))->withQueryString(),
            'filters' => ['status' => $status],
            'statuses' => StockTransfer::STATUS_LABELS,
        ]);
    }

    public function create()
    {
        return Inertia::render('Warehouse/Transfers/Form', [
            'warehouses' => Warehouse::active()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        return $this->attempt(
            fn () => $this->transfers->create($data, $request->user()->id),
            'Transfer dibuat sebagai draf. Stok baru keluar saat transfer dikirim.',
            fn (StockTransfer $t) => redirect()->route('warehouse.transfers.show', $t)
        );
    }

    public function show(StockTransfer $transfer)
    {
        $transfer->load(['from:id,code,name', 'to:id,code,name', 'items.product:id,sku,name,unit', 'creator:id,name']);

        // Stok tersedia di lokasi asal, ditampilkan selagi draf supaya kekurangan terlihat sebelum dikirim.
        $available = $transfer->status === StockTransfer::DRAFT
            ? Inventory::query()->where('warehouse_id', $transfer->from_warehouse_id)
                ->whereIn('product_id', $transfer->items->pluck('product_id'))
                ->get(['product_id', 'qty_on_hand', 'qty_reserved'])
                ->mapWithKeys(fn ($i) => [$i->product_id => $i->qty_on_hand - $i->qty_reserved])
            : collect();

        return Inertia::render('Warehouse/Transfers/Show', [
            'transfer' => $transfer,
            'available' => $available,
            'statuses' => StockTransfer::STATUS_LABELS,
        ]);
    }

    public function ship(Request $request, StockTransfer $transfer)
    {
        return $this->attempt(fn () => $this->transfers->ship($transfer, $request->user()->id), 'Transfer dikirim. Stok sudah keluar dari lokasi asal.');
    }

    public function receive(Request $request, StockTransfer $transfer)
    {
        return $this->attempt(fn () => $this->transfers->receive($transfer, $request->user()->id), 'Transfer diterima. Stok masuk ke lokasi tujuan.');
    }

    public function cancel(Request $request, StockTransfer $transfer)
    {
        return $this->attempt(fn () => $this->transfers->cancel($transfer, $request->user()->id), 'Transfer dibatalkan.');
    }
}
