<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderReturn;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Laporan penjualan. "Penjualan" = pesanan berstatus diproses / dikirim / selesai (COD dihitung saat pesanan diterima toko). */
class ReportService
{
    /**
     * @return array<string,mixed>
     */
    public function summary(CarbonInterface $from, CarbonInterface $to, ?string $channel = null): array
    {
        $orders = fn () => Order::query()->revenue()->whereBetween('placed_at', [$from, $to])
            ->when($channel, fn ($q, $c) => $q->where('channel', $c));

        $totals = $orders()->toBase()->selectRaw(
            'COUNT(*) AS orders, COALESCE(SUM(grand_total),0) AS gross, COALESCE(SUM(discount_total),0) AS discount, '
            .'COALESCE(SUM(shipping_cost),0) AS shipping, COALESCE(SUM(tax_total),0) AS tax'
        )->first();

        $returns = (float) OrderReturn::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($channel, fn ($q, $c) => $q->whereHas('order', fn ($o) => $o->where('channel', $c)))
            ->sum('refund_amount');

        $byChannel = $orders()->toBase()->groupBy('channel')
            ->selectRaw('channel, COUNT(*) AS orders, SUM(grand_total) AS total')->get()
            ->map(fn ($r) => ['channel' => $r->channel, 'orders' => (int) $r->orders, 'total' => (float) $r->total])->values();

        $daily = $orders()->toBase()->groupByRaw('DATE(placed_at)')->orderByRaw('DATE(placed_at)')
            ->selectRaw('DATE(placed_at) AS day, COUNT(*) AS orders, SUM(grand_total) AS total')->get()
            ->map(fn ($r) => ['day' => (string) $r->day, 'orders' => (int) $r->orders, 'total' => (float) $r->total])->values();

        $byMethod = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->whereIn('orders.status', [Order::PROCESSING, Order::SHIPPED, Order::COMPLETED])
            ->where('payments.status', 'paid')
            ->whereBetween('orders.placed_at', [$from, $to])
            ->when($channel, fn ($q, $c) => $q->where('orders.channel', $c))
            ->groupBy('payments.method_name')->orderByDesc(DB::raw('SUM(payments.amount)'))
            ->get(['payments.method_name AS name', DB::raw('SUM(payments.amount) AS total'), DB::raw('COUNT(*) AS n')])
            ->map(fn ($r) => ['name' => $r->name, 'total' => (float) $r->total, 'count' => (int) $r->n])->values();

        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', [Order::PROCESSING, Order::SHIPPED, Order::COMPLETED])
            ->whereBetween('orders.placed_at', [$from, $to])
            ->when($channel, fn ($q, $c) => $q->where('orders.channel', $c))
            ->groupBy('order_items.product_id', 'order_items.name', 'order_items.sku')
            ->orderByDesc(DB::raw('SUM(order_items.line_total)'))->limit(10)
            ->get([
                'order_items.name', 'order_items.sku',
                DB::raw('SUM(order_items.quantity) AS qty'), DB::raw('SUM(order_items.line_total) AS revenue'),
                DB::raw('SUM(order_items.line_total - order_items.cost_price * order_items.quantity) AS margin'),
            ])
            ->map(fn ($r) => ['name' => $r->name, 'sku' => $r->sku, 'qty' => (int) $r->qty, 'revenue' => (float) $r->revenue, 'margin' => (float) $r->margin])->values();

        $gross = (float) $totals->gross;

        return [
            'orders' => (int) $totals->orders, 'gross' => $gross, 'discount' => (float) $totals->discount,
            'shipping' => (float) $totals->shipping, 'tax' => (float) $totals->tax,
            'returns' => $returns, 'net' => $gross - $returns,
            'average_order' => $totals->orders > 0 ? round($gross / $totals->orders, 0) : 0.0,
            'by_channel' => $byChannel, 'daily' => $daily, 'by_method' => $byMethod, 'top_products' => $topProducts,
        ];
    }

    /** Ringkasan hari ini untuk dashboard. @return array<string,mixed> */
    public function today(): array
    {
        $start = now()->startOfDay();
        $q = Order::query()->revenue()->where('placed_at', '>=', $start);

        return [
            'orders' => (clone $q)->count(),
            'total' => (float) (clone $q)->sum('grand_total'),
            'pos_total' => (float) (clone $q)->where('channel', Order::POS)->sum('grand_total'),
            'online_total' => (float) (clone $q)->where('channel', Order::ECOMMERCE)->sum('grand_total'),
            'awaiting_payment' => Order::query()->where('status', Order::AWAITING_PAYMENT)->count(),
            'needs_review' => Order::query()->where('status', Order::NEEDS_REVIEW)->count(),
        ];
    }
}
