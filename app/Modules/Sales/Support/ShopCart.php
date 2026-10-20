<?php

namespace App\Modules\Sales\Support;

use App\Modules\Warehouse\Models\Product;
use Illuminate\Http\Request;

/**
 * Keranjang etalase web, disimpan di sesi: [product_id => jumlah]. Hanya menyimpan id & jumlah —
 * harga dan stok SELALU dibaca ulang dari database saat ditampilkan / checkout.
 */
class ShopCart
{
    private const KEY = 'shop.cart';
    private const MAX_LINES = 50;

    public function __construct(private Request $request)
    {
    }

    /** @return array<int,int> */
    public function items(): array
    {
        return $this->request->session()->get(self::KEY, []);
    }

    public function count(): int
    {
        return (int) array_sum($this->items());
    }

    public function add(int $productId, int $qty): void
    {
        $items = $this->items();
        if (! isset($items[$productId]) && count($items) >= self::MAX_LINES) {
            return;
        }
        $items[$productId] = min((int) config('cemwp.sales.max_line_qty', 999), ($items[$productId] ?? 0) + max(1, $qty));
        $this->request->session()->put(self::KEY, $items);
    }

    public function set(int $productId, int $qty): void
    {
        $items = $this->items();
        if ($qty <= 0) {
            unset($items[$productId]);
        } else {
            $items[$productId] = min((int) config('cemwp.sales.max_line_qty', 999), $qty);
        }
        $this->request->session()->put(self::KEY, $items);
    }

    public function remove(int $productId): void
    {
        $this->set($productId, 0);
    }

    public function clear(): void
    {
        $this->request->session()->forget(self::KEY);
    }

    /** Buang produk yang sudah tidak dijual online (dihapus / nonaktif) agar harga & checkout tidak error. */
    public function sanitize(): void
    {
        $items = $this->items();
        if ($items === []) {
            return;
        }
        $valid = Product::query()->active()->where('is_sellable_online', true)->whereIn('id', array_keys($items))->pluck('id')->all();
        $clean = array_intersect_key($items, array_flip($valid));
        if (count($clean) !== count($items)) {
            $this->request->session()->put(self::KEY, $clean);
        }
    }

    /** @return array<int,array{product_id:int,quantity:int}> */
    public function asOrderItems(): array
    {
        return array_map(fn ($id, $qty) => ['product_id' => (int) $id, 'quantity' => (int) $qty], array_keys($this->items()), array_values($this->items()));
    }
}
