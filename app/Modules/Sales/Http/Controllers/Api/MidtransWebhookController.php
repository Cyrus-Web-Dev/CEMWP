<?php

namespace App\Modules\Sales\Http\Controllers\Api;

use App\Modules\Sales\Exceptions\SalesException;
use App\Modules\Sales\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Notifikasi HTTP Midtrans. Selalu balas 200 untuk notifikasi sah (supaya Midtrans berhenti mengulang); 403 bila signature palsu. */
class MidtransWebhookController extends ApiBase
{
    public function __invoke(Request $request, PaymentService $payments)
    {
        try {
            $result = $payments->handleMidtrans($request->all());
        } catch (SalesException $e) {
            Log::warning('[Midtrans] Notifikasi ditolak: '.$e->getMessage().' ip='.$request->ip());

            return response()->json(['message' => 'forbidden'], 403);
        }

        return response()->json(['status' => $result]);
    }
}
