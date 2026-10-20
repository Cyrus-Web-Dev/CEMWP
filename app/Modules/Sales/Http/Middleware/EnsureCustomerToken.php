<?php

namespace App\Modules\Sales\Http\Middleware;

use App\Modules\Crm\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Endpoint pelanggan hanya menerima token milik Customer (token staf tidak boleh membuat/membaca pesanan pelanggan). */
class EnsureCustomerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');
        if (! $user instanceof Customer || ! $user->isActive() || ! $user->tokenCan('customer')) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
