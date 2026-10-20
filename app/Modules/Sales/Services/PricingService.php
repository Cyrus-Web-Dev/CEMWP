<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Exceptions\SalesException;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\ShippingMethod;
use App\Modules\Warehouse\Contracts\StockGateway;
use App\Modules\Warehouse\Models\Product;

/**
 * SATU-SATUNYA tempat harga, diskon, ongkir, dan total dihitung. Harga selalu dari database —
 * angka dari keranjang klien (web / HP) tidak pernah dipercaya.
 */
class PricingService
{
    public function __construct(private CouponService $coupons, private StockGateway $stock)
    {
    }

    /**
     * @param  array{items:array<int,array{product_id:int,quantity:int}>,channel:string,coupon_code?:?string,customer_id?:?int,shipping_method_id?:?int,warehouse_id?:?int,strict_coupon?:bool}  $input
     * @return array{lines:array<int,array<string,mixed>>,subtotal:float,discount_total:float,coupon:?array{id:int,code:string,name:string},coupon_error:?string,shipping_cost:float,tax_total:float,grand_total:float,total_weight_grams:int,shortages:array<int,array<string,mixed>>}
     */
    public function quote(array $input): array
    {
        $channel = $input['channel'];
        $scope = $channel === Order::POS ? 'pos' : 'online';
        $maxQty = (int) config('cemwp.sales.max_line_qty', 999);

        // 1. Gabungkan baris kembar, validasi jumlah
        $wanted = [];
        foreach ($input['items'] ?? [] as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 0);
            if ($productId <= 0 || $qty < 1) {
                throw new SalesException('Jumlah setiap produk harus minimal 1.');
            }
            $wanted[$productId] = ($wanted[$productId] ?? 0) + $qty;
        }
        if ($wanted === []) {
            throw new SalesException('Keranjang masih kosong.');
        }
        foreach ($wanted as $productId => $qty) {
            if ($qty > $maxQty) {
                throw new SalesException("Jumlah per produk maksimal {$maxQty}.");
            }
        }
        ksort($wanted);

        // 2. Ambil produk dari DB (soft-deleted otomatis tidak ikut)
        $products = Product::query()->whereIn('id', array_keys($wanted))->get()->keyBy('id');
        $available = $input['warehouse_id'] ?? null
            ? $this->stock->availabilityMap(array_keys($wanted), (int) $input['warehouse_id'])
            : [];

        $lines = [];
        $shortages = [];
        $subtotal = 0.0;
        $weight = 0;
        foreach ($wanted as $productId => $qty) {
            /** @var Product|null $p */
            $p = $products->get($productId);
            if (! $p || ! $p->is_active) {
                throw new SalesException('Salah satu produk sudah tidak dijual. Hapus dari keranjang lalu coba lagi.');
            }
            if ($channel === Order::ECOMMERCE && ! $p->is_sellable_online) {
                throw new SalesException("\"{$p->name}\" tidak dijual secara online.");
            }

            $unit = round((float) $p->selling_price, 0);
            $gross = $unit * $qty;
            $subtotal += $gross;
            $weight += $p->weight_grams * $qty;

            $avail = $p->track_stock && isset($input['warehouse_id']) ? max(0, (int) ($available[$productId] ?? 0)) : null;
            if ($avail !== null && $avail < $qty) {
                $shortages[] = ['product_id' => $productId, 'name' => $p->name, 'requested' => $qty, 'available' => $avail];
            }

            $lines[] = [
                'product_id' => $productId, 'sku' => $p->sku, 'name' => $p->name,
                'unit_price' => $unit, 'cost_price' => (float) $p->cost_price,
                'quantity' => $qty, 'gross' => $gross, 'discount' => 0.0, 'line_total' => $gross,
                'weight_grams' => (int) $p->weight_grams, 'track_stock' => (bool) $p->track_stock, 'available' => $avail,
            ];
        }

        // 3. Kupon
        $discount = 0.0;
        $couponInfo = null;
        $couponError = null;
        if (! empty($input['coupon_code'])) {
            try {
                $result = $this->coupons->evaluate($input['coupon_code'], $subtotal, $scope, $input['customer_id'] ?? null);
                $discount = $result['discount'];
                $couponInfo = ['id' => $result['coupon']->id, 'code' => $result['coupon']->code, 'name' => $result['coupon']->name];
            } catch (SalesException $e) {
                if ($input['strict_coupon'] ?? true) {
                    throw $e;
                }
                $couponError = $e->getMessage();
            }
        }

        // 4. Bagi potongan ke tiap baris secara proporsional (sisa pembulatan ke baris terakhir)
        if ($discount > 0) {
            $remaining = $discount;
            $last = count($lines) - 1;
            foreach ($lines as $i => &$line) {
                $share = $i === $last ? $remaining : min($remaining, floor($discount * $line['gross'] / $subtotal));
                $line['discount'] = $share;
                $line['line_total'] = $line['gross'] - $share;
                $remaining -= $share;
            }
            unset($line);
        }

        // 5. Ongkir (hanya online)
        $shipping = 0.0;
        $shippingMethod = null;
        if ($channel === Order::ECOMMERCE && ! empty($input['shipping_method_id'])) {
            $shippingMethod = ShippingMethod::query()->active()->find($input['shipping_method_id']);
            if (! $shippingMethod) {
                throw new SalesException('Metode pengiriman tidak tersedia.');
            }
            $shipping = $shippingMethod->costFor($weight, $subtotal - $discount);
        }

        // 6. Pajak (opsional, di atas harga)
        $rate = (float) config('cemwp.sales.tax_rate', 0);
        $tax = $rate > 0 ? round(($subtotal - $discount) * $rate / 100, 0) : 0.0;

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'coupon' => $couponInfo,
            'coupon_error' => $couponError,
            'shipping_cost' => $shipping,
            'shipping_method' => $shippingMethod,
            'tax_total' => $tax,
            'grand_total' => $subtotal - $discount + $shipping + $tax,
            'total_weight_grams' => $weight,
            'shortages' => $shortages,
        ];
    }
}
