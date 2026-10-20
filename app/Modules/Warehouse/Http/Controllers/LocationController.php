<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Models\Inventory;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\Warehouse;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Lokasi stok (gudang / toko / stok online). Model: Warehouse. */
class LocationController extends WarehouseController
{
    public function __construct()
    {
        $this->middleware('permission:warehouses.view')->only(['index']);
        $this->middleware('permission:warehouses.manage')->except(['index']);
    }

    public function index()
    {
        return Inertia::render('Warehouse/Locations/Index', [
            'locations' => Warehouse::query()
                ->withCount('inventories')
                ->withSum('inventories as units_on_hand', 'qty_on_hand')
                ->orderByDesc('is_default')->orderBy('name')->get(),
            'types' => Warehouse::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $warehouse = DB::transaction(function () use ($data) {
            if ($data['is_default']) {
                Warehouse::query()->where('is_default', true)->update(['is_default' => false]);
            }

            return Warehouse::create($data);
        });
        ActivityLogger::log('warehouse.created', "Lokasi stok baru: {$warehouse->code} - {$warehouse->name}", $warehouse);

        return back()->with('success', 'Lokasi stok ditambahkan.');
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $data = $this->validated($request, $warehouse);

        if (! $data['is_active'] && Inventory::where('warehouse_id', $warehouse->id)->where(fn ($q) => $q->where('qty_on_hand', '>', 0)->orWhere('qty_reserved', '>', 0))->exists()) {
            return back()->with('error', 'Lokasi masih menyimpan stok. Pindahkan atau nol-kan stoknya dulu sebelum menonaktifkan.');
        }

        DB::transaction(function () use ($data, $warehouse) {
            if ($data['is_default']) {
                Warehouse::query()->where('is_default', true)->where('id', '!=', $warehouse->id)->update(['is_default' => false]);
            }
            $warehouse->update($data);
        });
        ActivityLogger::log('warehouse.updated', "Lokasi stok diperbarui: {$warehouse->code}", $warehouse);

        return back()->with('success', 'Lokasi stok diperbarui.');
    }

    public function destroy(Warehouse $warehouse)
    {
        if (StockMovement::where('warehouse_id', $warehouse->id)->exists() || Inventory::where('warehouse_id', $warehouse->id)->where('qty_on_hand', '>', 0)->exists()) {
            return back()->with('error', 'Lokasi sudah punya riwayat stok, jadi tidak bisa dihapus. Nonaktifkan saja.');
        }
        Inventory::where('warehouse_id', $warehouse->id)->delete(); // baris saldo kosong (0) saja
        $warehouse->delete();
        ActivityLogger::log('warehouse.deleted', "Lokasi stok dihapus: {$warehouse->code}", null);

        return back()->with('success', 'Lokasi stok dihapus.');
    }

    private function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('warehouses', 'code')->ignore($warehouse?->id)],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(Warehouse::TYPES))],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
        ]);
    }
}
