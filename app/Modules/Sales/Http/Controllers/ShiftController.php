<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Sales\Models\Shift;
use App\Modules\Sales\Services\ShiftService;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShiftController extends SalesController
{
    public function __construct(private ShiftService $shifts)
    {
        $this->middleware('permission:pos.operate|orders.view')->only(['index', 'show']);
        $this->middleware('permission:pos.operate')->only(['open', 'close']);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $current = $user->can('pos.operate') ? $this->shifts->current($user) : null;

        $history = Shift::query()->with(['user:id,name', 'warehouse:id,code,name'])
            ->when(! $user->can('orders.view'), fn ($q) => $q->where('user_id', $user->id))
            ->where('status', Shift::CLOSED)->orderByDesc('id')->paginate(15);

        return Inertia::render('Sales/Shifts/Index', [
            'current' => $current ? [
                'id' => $current->id, 'outlet' => $current->warehouse?->name, 'opened_at' => $current->opened_at?->toIso8601String(),
                'opening_cash' => (float) $current->opening_cash, 'summary' => $this->shifts->summary($current),
            ] : null,
            'outlets' => Warehouse::active()->where('type', 'store')->orderBy('name')->get(['id', 'code', 'name']),
            'history' => $history,
            'canOperate' => $user->can('pos.operate'),
        ]);
    }

    public function show(Request $request, Shift $shift)
    {
        $user = $request->user();
        abort_unless($user->can('orders.view') || $shift->user_id === $user->id, 403);

        return Inertia::render('Sales/Shifts/Show', [
            'shift' => $shift->load(['user:id,name', 'warehouse:id,code,name']),
            'summary' => $this->shifts->summary($shift),
        ]);
    }

    public function open(Request $request)
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'opening_cash' => ['required', 'numeric', 'min:0', 'max:100000000000'],
        ]);

        return $this->attempt(
            fn () => $this->shifts->open($request->user(), $data['warehouse_id'], (float) $data['opening_cash']),
            'Shift dibuka. Selamat berjualan!',
            fn () => redirect()->route('sales.pos.index')
        );
    }

    public function close(Request $request, Shift $shift)
    {
        abort_unless($shift->user_id === $request->user()->id, 403, 'Hanya pemilik shift yang bisa menutupnya.');
        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:100000000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->attempt(
            fn () => $this->shifts->close($shift, (float) $data['counted_cash'], $data['notes'] ?? null, $request->user()),
            'Shift ditutup.',
            fn (Shift $s) => redirect()->route('sales.shifts.show', $s)
        );
    }
}
