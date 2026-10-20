<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Sales\Models\Payment;
use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Models\ShippingMethod;
use App\Modules\Sales\Payments\MidtransGateway;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Metode pembayaran & pengiriman. */
class SettingsController extends SalesController
{
    public function __construct(private MidtransGateway $midtrans)
    {
        $this->middleware('permission:salesconfig.manage');
    }

    public function index()
    {
        return Inertia::render('Sales/Settings/Index', [
            'paymentMethods' => PaymentMethod::orderBy('sort_order')->orderBy('id')->get(),
            'shippingMethods' => ShippingMethod::orderBy('sort_order')->orderBy('id')->get(),
            'kinds' => PaymentMethod::KINDS,
            'midtransReady' => $this->midtrans->isConfigured(),
        ]);
    }

    public function storePayment(Request $request)
    {
        $m = PaymentMethod::create($this->paymentData($request));
        ActivityLogger::log('payment_method.created', "Metode bayar {$m->name} dibuat", $m);

        return back()->with('success', 'Metode pembayaran ditambahkan.');
    }

    public function updatePayment(Request $request, PaymentMethod $paymentMethod)
    {
        $data = $this->paymentData($request, $paymentMethod);
        if ($data['kind'] === 'gateway' && $data['is_active'] && ! $this->midtrans->isConfigured()) {
            return back()->with('error', 'Isi MIDTRANS_SERVER_KEY di .env dulu sebelum mengaktifkan pembayaran online.');
        }
        $paymentMethod->update($data);
        ActivityLogger::log('payment_method.updated', "Metode bayar {$paymentMethod->name} diperbarui", $paymentMethod);

        return back()->with('success', 'Metode pembayaran diperbarui.');
    }

    public function destroyPayment(PaymentMethod $paymentMethod)
    {
        if (Payment::query()->where('payment_method_id', $paymentMethod->id)->exists()) {
            return back()->with('error', 'Metode ini sudah dipakai transaksi, jadi tidak bisa dihapus. Nonaktifkan saja.');
        }
        $paymentMethod->delete();

        return back()->with('success', 'Metode pembayaran dihapus.');
    }

    public function storeShipping(Request $request)
    {
        $m = ShippingMethod::create($this->shippingData($request));
        ActivityLogger::log('shipping_method.created', "Metode kirim {$m->label()} dibuat", $m);

        return back()->with('success', 'Metode pengiriman ditambahkan.');
    }

    public function updateShipping(Request $request, ShippingMethod $shippingMethod)
    {
        $shippingMethod->update($this->shippingData($request));
        ActivityLogger::log('shipping_method.updated', "Metode kirim {$shippingMethod->label()} diperbarui", $shippingMethod);

        return back()->with('success', 'Metode pengiriman diperbarui.');
    }

    public function destroyShipping(ShippingMethod $shippingMethod)
    {
        $shippingMethod->delete(); // pesanan lama menyimpan label sendiri; FK diatur null on delete
        ActivityLogger::log('shipping_method.deleted', 'Metode kirim dihapus', null);

        return back()->with('success', 'Metode pengiriman dihapus.');
    }

    private function paymentData(Request $request, ?PaymentMethod $method = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_]+$/', Rule::unique('payment_methods', 'code')->ignore($method?->id)],
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in(array_keys(PaymentMethod::KINDS))],
            'for_pos' => ['required', 'boolean'],
            'for_online' => ['required', 'boolean'],
            'requires_reference' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'instructions' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function shippingData(Request $request): array
    {
        return $request->validate([
            'courier' => ['required', 'string', 'max:40'],
            'service' => ['required', 'string', 'max:40'],
            'is_pickup' => ['required', 'boolean'],
            'base_cost' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'cost_per_kg' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'free_over' => ['nullable', 'numeric', 'min:0'],
            'min_days' => ['nullable', 'integer', 'min:0', 'max:60'],
            'max_days' => ['nullable', 'integer', 'min:0', 'max:60', 'gte:min_days'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ]);
    }
}
