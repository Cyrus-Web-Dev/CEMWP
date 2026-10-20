<?php

namespace App\Modules\Sales\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Modules\Crm\Models\Customer;
use App\Modules\Sales\Exceptions\SalesException;
use App\Modules\Sales\Models\Order;
use App\Modules\Warehouse\Exceptions\StockException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Dasar controller etalase publik. */
abstract class ShopBase extends Controller
{
    protected function customer(): ?Customer
    {
        $c = auth('customer')->user();

        return $c instanceof Customer && $c->isActive() ? $c : null;
    }

    /** Pesanan boleh dilihat oleh pemiliknya (login) atau tamu yang baru memesan / lolos verifikasi nomor+HP di sesi ini. */
    protected function canView(Request $request, Order $order): bool
    {
        $customer = $this->customer();
        if ($customer && $order->customer_id === $customer->id) {
            return true;
        }

        return in_array($order->number, $request->session()->get('shop.granted', []), true);
    }

    protected function grant(Request $request, Order $order): void
    {
        $granted = $request->session()->get('shop.granted', []);
        $granted[] = $order->number;
        $request->session()->put('shop.granted', array_slice(array_values(array_unique($granted)), -20));
    }

    /** @param callable():mixed $action */
    protected function attempt(callable $action, ?string $success = null, ?callable $then = null): RedirectResponse
    {
        try {
            $result = $action();
        } catch (SalesException|StockException $e) {
            return back()->with('error', $e->getMessage());
        }
        $response = $then ? $then($result) : back();

        return $success ? $response->with('success', $success) : $response;
    }
}
