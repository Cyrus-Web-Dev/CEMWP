<?php

namespace App\Modules\Sales\Http\Controllers\Shop;

use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Services\CustomerResolver;
use App\Modules\Sales\Exceptions\SalesException;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Models\ShippingMethod;
use App\Modules\Sales\Services\CheckoutService;
use App\Modules\Sales\Services\PricingService;
use App\Modules\Sales\Support\ShopCart;
use App\Modules\Warehouse\Exceptions\StockException;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CheckoutController extends ShopBase
{
    public function __construct(private PricingService $pricing, private CheckoutService $checkout, private CustomerResolver $customers)
    {
    }

    public function show(Request $request)
    {
        $cart = new ShopCart($request);
        $cart->sanitize();
        if ($cart->items() === []) {
            return redirect()->route('shop.cart')->with('error', 'Keranjang masih kosong.');
        }

        $customer = $this->customer();
        $last = $customer ? Order::query()->where('customer_id', $customer->id)->where('channel', Order::ECOMMERCE)->whereNotNull('shipping_city')->latest('id')->first() : null;

        return Inertia::render('Shop/Checkout', [
            'shippingMethods' => ShippingMethod::active()->get()->map(fn ($m) => [
                'id' => $m->id, 'label' => $m->label(), 'is_pickup' => $m->is_pickup, 'min_days' => $m->min_days, 'max_days' => $m->max_days,
            ]),
            'paymentMethods' => PaymentMethod::forOnline()->get(['code', 'name', 'kind', 'instructions']),
            'prefill' => [
                'customer_name' => $customer?->name ?? '', 'customer_phone' => $customer?->phone ? '0'.substr($customer->phone, 2) : '',
                'customer_email' => $customer?->email ?? '',
                'recipient_name' => $last?->recipient_name ?? $customer?->name ?? '', 'recipient_phone' => $last?->recipient_phone ?? '',
                'shipping_address' => $last?->shipping_address ?? '', 'shipping_city' => $last?->shipping_city ?? '',
                'shipping_postal_code' => $last?->shipping_postal_code ?? '',
            ],
            'loggedIn' => (bool) $customer,
            'requestKey' => (string) Str::uuid(),
        ]);
    }

    /** Hitung ulang total (kupon + ongkir) untuk tampilan checkout. */
    public function quote(Request $request)
    {
        $data = $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'shipping_method_id' => ['nullable', 'integer'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
        ]);
        $cart = new ShopCart($request);
        $cart->sanitize();

        try {
            $customer = $this->customer() ?? $this->customers->find(Customer::normalizePhone($data['customer_phone'] ?? null), null);
            $q = $this->pricing->quote([
                'items' => $cart->asOrderItems(), 'channel' => Order::ECOMMERCE, 'coupon_code' => $data['coupon_code'] ?? null,
                'customer_id' => $customer?->id, 'shipping_method_id' => $data['shipping_method_id'] ?? null,
                'warehouse_id' => Warehouse::onlineId(), 'strict_coupon' => false,
            ]);
        } catch (SalesException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'lines' => array_map(fn ($l) => ['product_id' => $l['product_id'], 'name' => $l['name'], 'quantity' => $l['quantity'], 'unit_price' => $l['unit_price'], 'line_total' => $l['gross']], $q['lines']),
            'subtotal' => $q['subtotal'], 'discount_total' => $q['discount_total'], 'shipping_cost' => $q['shipping_cost'],
            'tax_total' => $q['tax_total'], 'grand_total' => $q['grand_total'],
            'coupon' => $q['coupon'], 'coupon_error' => $q['coupon_error'], 'shortages' => $q['shortages'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'request_key' => ['required', 'string', 'min:8', 'max:100'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:190'],
            'shipping_method_id' => ['required', 'integer'],
            'payment_method' => ['required', 'string', 'max:30'],
            'recipient_name' => ['nullable', 'string', 'max:150'],
            'recipient_phone' => ['nullable', 'string', 'max:30'],
            'shipping_address' => ['nullable', 'string', 'max:500'],
            'shipping_city' => ['nullable', 'string', 'max:100'],
            'shipping_postal_code' => ['nullable', 'string', 'max:10'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        if (Customer::normalizePhone($data['customer_phone']) === null) {
            return back()->withErrors(['customer_phone' => 'Nomor HP tidak valid.']);
        }

        $cart = new ShopCart($request);
        $cart->sanitize();
        if ($cart->items() === []) {
            return redirect()->route('shop.cart')->with('error', 'Keranjang masih kosong.');
        }

        try {
            $order = $this->checkout->placeOnlineOrder(
                $data + ['items' => $cart->asOrderItems(), 'idempotency_key' => $data['request_key']],
                $this->customer()
            );
        } catch (SalesException|StockException $e) {
            return back()->with('error', $e->getMessage());
        }

        $cart->clear();
        $this->grant($request, $order);

        return redirect()->route('shop.orders.show', $order->number)->with('success', 'Pesanan berhasil dibuat.');
    }
}
