<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Enums\FulfillmentStatus;
use App\Modules\Warehouse\Models\Fulfillment;
use App\Modules\Warehouse\Services\FulfillmentService;
use App\Modules\Warehouse\Support\Like;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FulfillmentController extends WarehouseController
{
    public const COURIERS = ['JNE', 'J&T', 'SiCepat', 'AnterAja', 'Pos Indonesia', 'GoSend', 'GrabExpress', 'Kurir toko', 'Ambil di toko'];

    public function __construct(private FulfillmentService $fulfillments)
    {
        $this->middleware('permission:fulfillment.view')->only(['index', 'show']);
        $this->middleware('permission:fulfillment.process')->except(['index', 'show']);
    }

    public function index(Request $request)
    {
        $status = $request->input('status');
        $q = trim((string) $request->input('q'));

        $counts = Fulfillment::query()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');

        $list = Fulfillment::query()
            ->with('warehouse:id,code,name')
            ->withSum('items as total_units', 'quantity')
            ->when($status, fn ($query, $s) => $query->where('status', $s))
            ->when($q !== '', function ($query) use ($q) {
                $like = Like::contains($q);
                $query->where(fn ($w) => $w->where('number', 'ilike', $like)->orWhere('source_number', 'ilike', $like)->orWhere('recipient_name', 'ilike', $like));
            })
            ->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->paginate((int) config('cemwp.warehouse.per_page', 25))->withQueryString();

        return Inertia::render('Warehouse/Fulfillments/Index', [
            'fulfillments' => $list,
            'filters' => ['status' => $status, 'q' => $q],
            'counts' => $counts,
            'statuses' => collect(FulfillmentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]),
        ]);
    }

    public function show(Fulfillment $fulfillment)
    {
        return Inertia::render('Warehouse/Fulfillments/Show', [
            'fulfillment' => $fulfillment->load(['warehouse:id,code,name', 'assignee:id,name', 'items']),
            'statuses' => collect(FulfillmentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]),
            'couriers' => self::COURIERS,
        ]);
    }

    public function start(Request $request, Fulfillment $fulfillment)
    {
        return $this->attempt(fn () => $this->fulfillments->start($fulfillment, $request->user()->id), 'Pengambilan barang dimulai.');
    }

    public function picked(Request $request, Fulfillment $fulfillment)
    {
        $data = $request->validate(['picked' => ['required', 'array'], 'picked.*' => ['nullable', 'integer', 'min:0']]);

        return $this->attempt(
            fn () => $this->fulfillments->savePicked($fulfillment, array_map(fn ($v) => $v ?? 0, $data['picked'])),
            'Jumlah diambil disimpan.'
        );
    }

    public function pack(Fulfillment $fulfillment)
    {
        return $this->attempt(fn () => $this->fulfillments->pack($fulfillment), 'Pesanan dikemas.');
    }

    public function ship(Request $request, Fulfillment $fulfillment)
    {
        $data = $request->validate(['courier' => ['required', 'string', 'max:40'], 'tracking_number' => ['required', 'string', 'max:80']]);

        return $this->attempt(fn () => $this->fulfillments->ship($fulfillment, $data['courier'], $data['tracking_number']), 'Pesanan dikirim.');
    }

    public function deliver(Fulfillment $fulfillment)
    {
        return $this->attempt(fn () => $this->fulfillments->deliver($fulfillment), 'Pesanan ditandai sudah diterima.');
    }

    public function cancel(Request $request, Fulfillment $fulfillment)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->fulfillments->cancel($fulfillment, $data['reason']), 'Tugas gudang dibatalkan.');
    }
}
