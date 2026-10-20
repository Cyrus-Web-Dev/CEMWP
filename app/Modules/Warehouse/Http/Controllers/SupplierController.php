<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Models\Supplier;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SupplierController extends WarehouseController
{
    public function __construct()
    {
        $this->middleware('permission:suppliers.view')->only(['index']);
        $this->middleware('permission:suppliers.manage')->except(['index']);
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));

        return Inertia::render('Warehouse/Suppliers/Index', [
            'suppliers' => Supplier::query()
                ->withCount('purchaseOrders')
                ->when($q !== '', function ($query) use ($q) {
                    $like = \App\Modules\Warehouse\Support\Like::contains($q);
                    $query->where(fn ($w) => $w->where('name', 'ilike', $like)->orWhere('code', 'ilike', $like));
                })
                ->orderBy('name')->get(),
            'filters' => ['q' => $q],
        ]);
    }

    public function store(Request $request)
    {
        $supplier = Supplier::create($this->validated($request));
        ActivityLogger::log('supplier.created', "Supplier baru: {$supplier->code} - {$supplier->name}", $supplier);

        return back()->with('success', 'Supplier ditambahkan.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request, $supplier));
        ActivityLogger::log('supplier.updated', "Supplier diperbarui: {$supplier->code}", $supplier);

        return back()->with('success', 'Supplier diperbarui.');
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchaseOrders()->exists()) {
            return back()->with('error', 'Supplier sudah punya PO, jadi tidak bisa dihapus. Nonaktifkan saja.');
        }
        $supplier->delete();
        ActivityLogger::log('supplier.deleted', "Supplier dihapus: {$supplier->code}", null);

        return back()->with('success', 'Supplier dihapus.');
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('suppliers', 'code')->ignore($supplier?->id)],
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
