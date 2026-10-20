<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderItem;
use App\Modules\Sales\Services\OrderService;
use App\Modules\Sales\Services\PaymentService;
use App\Modules\Sales\Services\ReturnService;
use App\Modules\Warehouse\Support\Like;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Pesanan (POS + online) untuk back-office. */
class OrderController extends SalesController
{
    public function __construct(private OrderService $orders, private PaymentService $payments, private ReturnService $returns)
    {
        $this->middleware('permission:orders.view')->only(['index', 'show']);
        $this->middleware('permission:orders.manage')->only(['confirmPayment', 'cancel', 'markRefunded']);
        $this->middleware('permission:orders.refund')->only(['storeReturn']);
    }

    public function index(Request $request)
    {
        $filters = $request->only(['q', 'status', 'channel', 'from', 'to']);

        $orders = Order::query()
            ->with(['customer:id,name', 'cashier:id,name'])
            ->when($filters['q'] ?? null, function ($query, $q) {
                $like = Like::contains($q);
                $query->where(fn ($w) => $w->where('number', 'ilike', $like)->orWhere('customer_name', 'ilike', $like)->orWhere('customer_phone', 'ilike', $like));
            })
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['channel'] ?? null, fn ($q, $c) => $q->where('channel', $c))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('placed_at', '>=', $d.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('placed_at', '<=', $d.' 23:59:59'))
            ->orderByDesc('id')
            ->paginate((int) config('cemwp.warehouse.per_page', 25))->withQueryString();

        return Inertia::render('Sales/Orders/Index', [
            'orders' => $orders, 'filters' => $filters,
            'statuses' => Order::STATUS_LABELS, 'paymentLabels' => Order::PAYMENT_LABELS,
        ]);
    }

    public function show(Order $order)
    {
        $order->load(['items', 'payments', 'histories', 'customer:id,name,phone,email,orders_count', 'cashier:id,name', 'warehouse:id,name', 'returns.items', 'returns.user:id,name', 'shift:id,opened_at']);

        $returned = OrderItem::query()->where('order_id', $order->id)->withSum('returnItems as returned_qty', 'quantity')->pluck('returned_qty', 'id');

        return Inertia::render('Sales/Orders/Show', [
            'order' => $order,
            'returnable' => $order->items->mapWithKeys(fn ($i) => [$i->id => $i->quantity - (int) ($returned[$i->id] ?? 0)]),
            'statuses' => Order::STATUS_LABELS, 'paymentLabels' => Order::PAYMENT_LABELS,
            'refundMethods' => ReturnService::REFUND_METHODS,
        ]);
    }

    public function confirmPayment(Request $request, Order $order)
    {
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:100']]);

        return $this->attempt(fn () => $this->payments->confirmManual($order, $data['reference'] ?? null, $request->user()), 'Pembayaran dikonfirmasi. Pesanan diteruskan ke gudang.');
    }

    public function cancel(Request $request, Order $order)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:250']]);

        return $this->attempt(fn () => $this->orders->cancel($order, $data['reason'], $request->user()->id), 'Pesanan dibatalkan.');
    }

    public function markRefunded(Request $request, Order $order)
    {
        return $this->attempt(fn () => $this->orders->markRefunded($order, $request->user()->id), 'Refund dicatat selesai.');
    }

    public function storeReturn(Request $request, Order $order)
    {
        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'reason' => ['required', 'string', 'max:250'],
            'refund_method' => ['required', 'string', 'max:30'],
            'restock' => ['required', 'boolean'],
        ]);

        return $this->attempt(
            fn () => $this->returns->create($order, array_map('intval', array_filter($data['quantities'])), $data['reason'], $data['refund_method'], (bool) $data['restock'], $request->user()),
            'Retur dicatat.'
        );
    }
}
