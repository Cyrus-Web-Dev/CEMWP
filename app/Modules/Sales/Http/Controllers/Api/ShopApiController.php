<?php

namespace App\Modules\Sales\Http\Controllers\Api;

use App\Modules\Crm\Models\Customer;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Models\ShippingMethod;
use App\Modules\Sales\Services\PricingService;
use App\Modules\Warehouse\Models\Category;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Http\Request;

/** Data pendukung checkout untuk aplikasi mobile: metode kirim/bayar, kategori, dan kuotasi harga (publik, dibatasi laju). */
class ShopApiController extends ApiBase
{
    public function __construct(private PricingService $pricing)
    {
    }

    public function shippingMethods()
    {
        return response()->json(['data' => ShippingMethod::active()->get()->map(fn ($m) => [
            'id' => $m->id, 'label' => $m->label(), 'is_pickup' => $m->is_pickup, 'min_days' => $m->min_days, 'max_days' => $m->max_days,
        ])]);
    }

    public function paymentMethods()
    {
        return response()->json(['data' => PaymentMethod::forOnline()->get(['code', 'name', 'kind', 'instructions'])]);
    }

    public function categories()
    {
        return response()->json(['data' => Category::orderBy('name')->get(['id', 'name', 'slug', 'parent_id'])]);
    }

    /** Hitung total keranjang (harga dari server) tanpa membuat pesanan. Token opsional (untuk kupon personal). */
    public function quote(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'coupon_code' => ['nullable', 'string', 'max:40'], 'shipping_method_id' => ['nullable', 'integer'],
        ]);
        $user = $request->user('sanctum');
        $customerId = $user instanceof Customer ? $user->id : null;

        return $this->guard(function () use ($data, $customerId) {
            $q = $this->pricing->quote([
                'items' => $data['items'], 'channel' => Order::ECOMMERCE, 'coupon_code' => $data['coupon_code'] ?? null,
                'customer_id' => $customerId, 'shipping_method_id' => $data['shipping_method_id'] ?? null,
                'warehouse_id' => Warehouse::onlineId(), 'strict_coupon' => false,
            ]);

            return response()->json([
                'lines' => array_map(fn ($l) => [
                    'product_id' => $l['product_id'], 'name' => $l['name'], 'unit_price' => $l['unit_price'],
                    'quantity' => $l['quantity'], 'discount' => $l['discount'], 'line_total' => $l['line_total'], 'available' => $l['available'],
                ], $q['lines']),
                'subtotal' => $q['subtotal'], 'discount_total' => $q['discount_total'], 'shipping_cost' => $q['shipping_cost'],
                'tax_total' => $q['tax_total'], 'grand_total' => $q['grand_total'],
                'coupon' => $q['coupon'], 'coupon_error' => $q['coupon_error'], 'shortages' => $q['shortages'],
            ]);
        });
    }
}
