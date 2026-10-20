<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Services\CustomerResolver;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Services\OrderService;
use App\Modules\Sales\Services\PricingService;
use App\Modules\Sales\Services\ShiftService;
use App\Modules\Warehouse\Contracts\StockGateway;
use App\Modules\Warehouse\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PosController extends SalesController
{
    public function __construct(
        private ShiftService $shifts,
        private OrderService $orders,
        private PricingService $pricing,
        private CustomerResolver $customers,
        private StockGateway $stock,
    ) {
        $this->middleware('permission:pos.operate')->except(['receipt']);
    }

    public function index(Request $request)
    {
        $shift = $this->shifts->current($request->user());
        if (! $shift) {
            return redirect()->route('sales.shifts.index')->with('error', 'Buka shift kasir dulu untuk mulai berjualan.');
        }

        return Inertia::render('Sales/Pos/Index', [
            'shift' => ['id' => $shift->id, 'outlet' => $shift->warehouse?->name, 'opened_at' => $shift->opened_at?->toIso8601String()],
            'paymentMethods' => PaymentMethod::forPos()->get(['code', 'name', 'kind', 'requires_reference']),
            'storeName' => config('cemwp.sales.store_name'),
            'userId' => $request->user()->id,
        ]);
    }

    /** Katalog untuk kasir (stok = tersedia di outlet shift ini). */
    public function catalog(Request $request)
    {
        $shift = $this->shifts->current($request->user());
        abort_unless($shift, 409, 'Belum ada shift terbuka.');

        $products = Product::query()->active()->with('category:id,name')->orderBy('name')->limit(3000)
            ->get(['id', 'sku', 'barcode', 'name', 'unit', 'selling_price', 'category_id', 'track_stock']);
        $available = $this->stock->availabilityMap($products->pluck('id')->all(), $shift->warehouse_id);

        return response()->json([
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id, 'sku' => $p->sku, 'barcode' => $p->barcode, 'name' => $p->name, 'unit' => $p->unit,
                'price' => (float) $p->selling_price, 'category' => $p->category?->name,
                'track_stock' => $p->track_stock, 'available' => $p->track_stock ? max(0, $available[$p->id] ?? 0) : null,
            ])->values(),
        ]);
    }

    public function lookupCustomer(Request $request)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $customer = $this->customers->find(Customer::normalizePhone($data['phone']), null);

        return response()->json(['customer' => $customer ? [
            'name' => $customer->name, 'phone' => $customer->phone, 'orders_count' => $customer->orders_count,
            'last_order_at' => $customer->last_order_at?->toIso8601String(),
        ] : null]);
    }

    public function quote(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
        ]);
        $shift = $this->shifts->current($request->user());
        abort_unless($shift, 409, 'Belum ada shift terbuka.');

        return $this->json(function () use ($data, $shift) {
            $customer = $this->customers->find(Customer::normalizePhone($data['customer_phone'] ?? null), null);
            $q = $this->pricing->quote([
                'items' => $data['items'], 'channel' => Order::POS, 'coupon_code' => $data['coupon_code'] ?? null,
                'customer_id' => $customer?->id, 'warehouse_id' => $shift->warehouse_id, 'strict_coupon' => false,
            ]);

            return [
                'subtotal' => $q['subtotal'], 'discount_total' => $q['discount_total'], 'tax_total' => $q['tax_total'],
                'grand_total' => $q['grand_total'], 'coupon' => $q['coupon'], 'coupon_error' => $q['coupon_error'],
                'shortages' => $q['shortages'],
                'lines' => array_map(fn ($l) => ['product_id' => $l['product_id'], 'line_total' => $l['line_total'], 'discount' => $l['discount']], $q['lines']),
            ];
        });
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'payments' => ['nullable', 'array', 'max:6'],
            'payments.*.method' => ['required_with:payments', 'string', 'max:30'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:1', 'max:100000000000'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->json(function () use ($data, $request) {
            $order = $this->orders->createPosSale($data, $request->user());

            return [
                'order' => ['id' => $order->id, 'number' => $order->number, 'grand_total' => (float) $order->grand_total, 'change_due' => (float) $order->change_due],
                'receipt_url' => route('sales.pos.receipt', $order->id),
            ];
        });
    }

    public function receipt(Request $request, Order $order)
    {
        $user = $request->user();
        abort_unless($user->can('orders.view') || ($user->can('pos.operate') && $order->user_id === $user->id), 403);

        return Inertia::render('Sales/Pos/Receipt', [
            'order' => $order->load(['items', 'payments', 'cashier:id,name', 'warehouse:id,name', 'returns']),
            'store' => [
                'name' => config('cemwp.sales.store_name'), 'address' => config('cemwp.sales.store_address'),
                'phone' => config('cemwp.sales.store_phone'), 'footer' => config('cemwp.sales.receipt_footer'),
            ],
        ]);
    }
}
