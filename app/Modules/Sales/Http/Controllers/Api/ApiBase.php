<?php

namespace App\Modules\Sales\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Crm\Models\Customer;
use App\Modules\Sales\Exceptions\SalesException;
use App\Modules\Warehouse\Exceptions\InsufficientStockException;
use App\Modules\Warehouse\Exceptions\StockException;
use Illuminate\Http\JsonResponse;

abstract class ApiBase extends Controller
{
    /** @param callable():JsonResponse $action */
    protected function guard(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (InsufficientStockException $e) {
            return response()->json([
                'message' => $e->getMessage(), 'code' => 'insufficient_stock',
                'product_id' => $e->productId, 'requested' => $e->requested, 'available' => $e->available,
            ], 409);
        } catch (SalesException|StockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    protected function tokenResponse(Customer $customer, int $status = 200): JsonResponse
    {
        $ttl = (int) config('cemwp.warehouse.api_token_ttl_minutes', 43200);
        $token = $customer->createToken('mobile', ['customer', 'catalog:read'], $ttl > 0 ? now()->addMinutes($ttl) : null);

        return response()->json(['token' => $token->plainTextToken, 'token_type' => 'Bearer', 'customer' => $this->customerArray($customer)], $status);
    }

    protected function customerArray(Customer $c): array
    {
        return ['id' => $c->id, 'name' => $c->name, 'email' => $c->email, 'phone' => $c->phone, 'marketing_consent' => (bool) $c->marketing_consent];
    }
}
