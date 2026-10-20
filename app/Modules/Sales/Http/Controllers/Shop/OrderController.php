<?php

namespace App\Modules\Sales\Http\Controllers\Shop;

use App\Modules\Crm\Models\Customer;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Services\OrderService;
use App\Modules\Sales\Support\OrderPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;

/** Halaman pesanan pembeli: lihat, lacak, batalkan; daftar pesanan akun. */
class OrderController extends ShopBase
{
    public function __construct(private OrderService $orders)
    {
    }

    public function show(Request $request, string $number)
    {
        $order = Order::query()->where('number', $number)->where('channel', Order::ECOMMERCE)->firstOrFail();
        if (! $this->canView($request, $order)) {
            return redirect()->route('shop.track')->with('error', 'Untuk melihat pesanan ini, masukkan nomor pesanan dan nomor HP-mu.');
        }
        $order->load(['items', 'payments', 'histories']);

        $payment = $order->payments->first();
        $method = $payment ? PaymentMethod::query()->find($payment->payment_method_id) : null;

        return Inertia::render('Shop/Order', [
            'order' => OrderPresenter::toArray($order),
            'instructions' => $method?->kind === 'manual' ? ($method->instructions ?: config('cemwp.sales.bank_transfer_info')) : null,
            'canCancel' => $order->status === Order::AWAITING_PAYMENT,
        ]);
    }

    public function cancel(Request $request, string $number)
    {
        $order = Order::query()->where('number', $number)->where('channel', Order::ECOMMERCE)->firstOrFail();
        abort_unless($this->canView($request, $order), 403);
        abort_unless($order->status === Order::AWAITING_PAYMENT, 422, 'Pesanan ini sudah tidak bisa dibatalkan dari sini. Hubungi toko.');

        return $this->attempt(fn () => $this->orders->cancel($order, 'Dibatalkan oleh pembeli', null), 'Pesanan dibatalkan.');
    }

    public function trackForm()
    {
        return Inertia::render('Shop/Track');
    }

    public function track(Request $request)
    {
        $data = $request->validate(['number' => ['required', 'string', 'max:40'], 'phone' => ['required', 'string', 'max:30']]);

        // Batasi percobaan menebak: 10 / 10 menit per IP (nomor pesanan berurutan, jadi nomor HP adalah rahasia pembuktinya).
        $key = 'shop-track:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->with('error', 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.');
        }
        RateLimiter::hit($key, 600);

        $phone = Customer::normalizePhone($data['phone']);
        $order = Order::query()->where('number', strtoupper(trim($data['number'])))->where('channel', Order::ECOMMERCE)->first();
        $match = $order && $phone && ($order->customer_phone === $phone || Customer::normalizePhone($order->recipient_phone) === $phone);
        if (! $match) {
            return back()->with('error', 'Pesanan tidak ditemukan. Periksa nomor pesanan dan nomor HP yang dipakai saat memesan.');
        }

        $this->grant($request, $order);

        return redirect()->route('shop.orders.show', $order->number);
    }

    public function account(Request $request)
    {
        $customer = $this->customer();
        if (! $customer) {
            return redirect()->route('shop.auth');
        }

        return Inertia::render('Shop/Account', [
            'orders' => Order::query()->where('customer_id', $customer->id)->with('payments')->orderByDesc('id')->paginate(10)
                ->through(fn (Order $o) => OrderPresenter::toArray($o, false)),
            'profile' => ['name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->phone],
        ]);
    }
}
