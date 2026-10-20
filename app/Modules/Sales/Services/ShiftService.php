<?php

namespace App\Modules\Sales\Services;

use App\Models\User;
use App\Modules\Sales\Exceptions\SalesException;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderReturn;
use App\Modules\Sales\Models\Shift;
use App\Modules\Warehouse\Models\Warehouse;
use App\Services\ActivityLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    public function current(User $user): ?Shift
    {
        return Shift::query()->open()->where('user_id', $user->id)->with('warehouse:id,code,name')->first();
    }

    public function open(User $user, int $warehouseId, float $openingCash): Shift
    {
        $warehouse = Warehouse::query()->active()->find($warehouseId);
        if (! $warehouse || $warehouse->type !== 'store') {
            throw new SalesException('Pilih outlet (lokasi bertipe toko) yang aktif.');
        }
        if ($openingCash < 0) {
            throw new SalesException('Kas awal tidak boleh negatif.');
        }

        try {
            $shift = Shift::create([
                'user_id' => $user->id, 'warehouse_id' => $warehouseId, 'status' => Shift::OPEN,
                'opened_at' => now(), 'opening_cash' => $openingCash,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new SalesException('Kamu masih punya shift yang terbuka. Tutup dulu sebelum membuka yang baru.');
        }

        ActivityLogger::log('shift.opened', "Shift dibuka di {$warehouse->name} (kas awal Rp ".number_format($openingCash, 0, ',', '.').')', $shift, $user->id);

        return $shift;
    }

    /**
     * Rekap shift: penjualan, rincian per metode bayar, dan kas yang seharusnya ada di laci.
     *
     * @return array<string,mixed>
     */
    public function summary(Shift $shift): array
    {
        $orders = Order::query()->where('shift_id', $shift->id)->revenue();

        $byMethod = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('orders.shift_id', $shift->id)
            ->where('payments.status', 'paid')
            ->groupBy('payments.method_code', 'payments.method_name', 'payments.kind')
            ->orderBy('payments.method_name')
            ->get(['payments.method_code', 'payments.method_name', 'payments.kind', DB::raw('SUM(payments.amount) AS total'), DB::raw('COUNT(*) AS n')]);

        $cashSales = (float) $byMethod->where('kind', 'cash')->sum('total');
        $cashRefunds = (float) OrderReturn::query()->where('shift_id', $shift->id)->where('refund_method', 'cash')->sum('refund_amount');
        $returnsTotal = (float) OrderReturn::query()->where('shift_id', $shift->id)->sum('refund_amount');

        return [
            'orders_count' => (clone $orders)->count(),
            'sales_total' => (float) (clone $orders)->sum('grand_total'),
            'discount_total' => (float) (clone $orders)->sum('discount_total'),
            'by_method' => $byMethod->map(fn ($r) => ['code' => $r->method_code, 'name' => $r->method_name, 'kind' => $r->kind, 'total' => (float) $r->total, 'count' => (int) $r->n])->values(),
            'opening_cash' => (float) $shift->opening_cash,
            'cash_sales' => $cashSales,
            'cash_refunds' => $cashRefunds,
            'returns_total' => $returnsTotal,
            'expected_cash' => (float) $shift->opening_cash + $cashSales - $cashRefunds,
        ];
    }

    public function close(Shift $shift, float $countedCash, ?string $notes, User $by): Shift
    {
        if ($countedCash < 0) {
            throw new SalesException('Uang yang dihitung tidak boleh negatif.');
        }

        return DB::transaction(function () use ($shift, $countedCash, $notes, $by) {
            $shift = Shift::query()->lockForUpdate()->findOrFail($shift->id);
            if ($shift->status !== Shift::OPEN) {
                throw new SalesException('Shift ini sudah ditutup.');
            }

            $expected = $this->summary($shift)['expected_cash'];
            $shift->update([
                'status' => Shift::CLOSED, 'closed_at' => now(), 'expected_cash' => $expected,
                'counted_cash' => $countedCash, 'difference' => $countedCash - $expected, 'notes' => $notes,
            ]);
            ActivityLogger::log('shift.closed', 'Shift ditutup. Selisih kas Rp '.number_format($countedCash - $expected, 0, ',', '.'), $shift, $by->id);

            return $shift;
        });
    }
}
