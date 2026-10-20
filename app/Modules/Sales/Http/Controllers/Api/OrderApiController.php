<?php

namespace App\Modules\Sales\Http\Controllers\Api;

use App\Modules\Crm\Models\Customer;
use App\Modules\Sales\Exceptions\SalesException;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Services\CheckoutService;
use App\Modules\Sales\Services\OrderService;
use App\Modules\Sales\Support\OrderPresenter;
use Illuminate\Http\Request;

/**
 * POST /api/v1/orders  (header wajib: Idempotency-Key)
 * Alur: token Sanctum divalidasi -> transaksi DB: pesanan dibuat, stok dipotong (COD) atau dicadangkan (bayar online) ->
 * 201 Created + detail pesanan. Event OrderPaid kelak memperbarui profil pelanggan di CRM.
 */
class OrderApiController extends ApiBase
{
    public function __construct(private CheckoutService $checkout, private OrderService $orders)
    {
    }

    public function store(Request $request)
    {
        $key = (string) $request->header('Idempotency-Key');
        if (strlen($key) < 8 || strlen($key) > 100) {
            return response()->json(['message' => 'Header Idempotency-Key (8-100 karakter) wajib diisi agar pesanan tidak ganda saat koneksi putus.'], 422);
        }

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'shipping_method_id' => ['required', 'integer'], 'payment_method' => ['required', 'string', 'max:30'],
            'coupon_code' => ['nullable', 'string', 'max:40'], 'notes' => ['nullable', 'string', 'max:500'],
            'recipient_name' => ['nullable', 'string', 'max:150'], 'recipient_phone' => ['nullable', 'string', 'max:30'],
            'shipping_address' => ['nullable', 'string', 'max:500'], 'shipping_city' => ['nullable', 'string', 'max:100'],
            'shipping_postal_code' => ['nullable', 'string', 'max:10'],
        ]);

        /** @var Customer $customer */
        $customer = $request->user('sanctum');

        return $this->guard(function () use ($data, $key, $customer) {
            $order = $this->checkout->placeOnlineOrder($data + ['idempotency_key' => $key], $customer);
            $order->load(['items', 'payments', 'histories']);

            return response()->json(['data' => OrderPresenter::toArray($order)], 201);
        });
    }

    public function index(Request $request)
    {
        /** @var Customer $customer */
        $customer = $request->user('sanctum');
        $page = Order::query()->where('customer_id', $customer->id)->with('payments')->orderByDesc('id')->paginate(15);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Order $o) => OrderPresenter::toArray($o, false))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Request $request, string $number)
    {
        return response()->json(['data' => OrderPresenter::toArray($this->own($request, $number)->load(['items', 'payments', 'histories']))]);
    }

    public function cancel(Request $request, string $number)
    {
        $order = $this->own($request, $number);

        return $this->guard(function () use ($order) {
            if ($order->status !== Order::AWAITING_PAYMENT) {
                throw new SalesException('Pesanan ini sudah diproses dan tidak bisa dibatalkan dari aplikasi. Hubungi toko.');
            }
            $this->orders->cancel($order, 'Dibatalkan oleh pembeli', null);

            return response()->json(['data' => OrderPresenter::toArray($order->fresh(['items', 'payments', 'histories']))]);
        });
    }

    private function own(Request $request, string $number): Order
    {
        /** @var Customer $customer */
        $customer = $request->user('sanctum');

        return Order::query()->where('number', $number)->where('customer_id', $customer->id)->firstOrFail();
    }
}
