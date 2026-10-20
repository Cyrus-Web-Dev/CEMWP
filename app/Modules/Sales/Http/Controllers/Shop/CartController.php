<?php

namespace App\Modules\Sales\Http\Controllers\Shop;

use App\Modules\Sales\Exceptions\SalesException;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Services\PricingService;
use App\Modules\Sales\Support\ShopCart;
use App\Modules\Warehouse\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CartController extends ShopBase
{
    public function __construct(private PricingService $pricing)
    {
    }

    public function show(Request $request)
    {
        $cart = new ShopCart($request);
        $cart->sanitize();

        $lines = [];
        $subtotal = 0.0;
        if ($cart->items() !== []) {
            try {
                $q = $this->pricing->quote([
                    'items' => $cart->asOrderItems(), 'channel' => Order::ECOMMERCE, 'warehouse_id' => Warehouse::onlineId(), 'strict_coupon' => false,
                ]);
                $lines = array_map(fn ($l) => [
                    'product_id' => $l['product_id'], 'name' => $l['name'], 'unit_price' => $l['unit_price'], 'quantity' => $l['quantity'],
                    'line_total' => $l['gross'], 'available' => $l['available'],
                ], $q['lines']);
                $subtotal = $q['subtotal'];
            } catch (SalesException $e) {
                session()->flash('error', $e->getMessage());
            }
        }

        return Inertia::render('Shop/Cart', ['lines' => $lines, 'subtotal' => $subtotal]);
    }

    public function add(Request $request)
    {
        $data = $request->validate(['product_id' => ['required', 'integer'], 'quantity' => ['nullable', 'integer', 'min:1', 'max:999']]);
        $product = Product::query()->active()->where('is_sellable_online', true)->find($data['product_id']);
        if (! $product) {
            return back()->with('error', 'Produk tidak tersedia.');
        }
        (new ShopCart($request))->add($product->id, $data['quantity'] ?? 1);

        return back()->with('success', "{$product->name} ditambahkan ke keranjang.");
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:999']]);
        (new ShopCart($request))->set($product->id, $data['quantity']);

        return back();
    }

    public function remove(Request $request, Product $product)
    {
        (new ShopCart($request))->remove($product->id);

        return back();
    }
}
