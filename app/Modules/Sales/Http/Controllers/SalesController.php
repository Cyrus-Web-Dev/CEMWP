<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Exceptions\SalesException;
use App\Modules\Warehouse\Exceptions\StockException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Throwable;

/** Dasar controller Sales: kesalahan bisnis (SalesException / StockException) menjadi pesan ramah, bukan error 500. */
abstract class SalesController extends Controller
{
    /**
     * @param  callable():mixed  $action
     * @param  (callable(mixed):RedirectResponse)|null  $then
     */
    protected function attempt(callable $action, string $success, ?callable $then = null): RedirectResponse
    {
        try {
            $result = $action();
        } catch (SalesException|StockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return ($then ? $then($result) : back())->with('success', $success);
    }

    /** Untuk endpoint XHR (POS): pesan bisnis -> 422 JSON; selain itu biarkan naik sebagai error sungguhan. */
    protected function json(callable $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (SalesException|StockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
