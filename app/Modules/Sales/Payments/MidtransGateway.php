<?php

namespace App\Modules\Sales\Payments;

use App\Modules\Sales\Exceptions\SalesException;
use App\Modules\Sales\Models\Order;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Klien Midtrans Snap (https://docs.midtrans.com). order_id yang dikirim ke Midtrans = nomor pesanan kita (unik).
 * Webhook diverifikasi dengan signature SHA-512(order_id + status_code + gross_amount + server_key).
 */
class MidtransGateway
{
    public function isConfigured(): bool
    {
        return (bool) config('cemwp.payments.midtrans.server_key');
    }

    private function baseUrl(): string
    {
        return config('cemwp.payments.midtrans.production') ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
    }

    /** @return array{token:string,redirect_url:string} */
    public function createTransaction(Order $order): array
    {
        if (! $this->isConfigured()) {
            throw new SalesException('Pembayaran online belum dikonfigurasi (MIDTRANS_SERVER_KEY kosong).');
        }

        $minutes = max(1, (int) ($order->expires_at ? now()->diffInMinutes($order->expires_at, false) : config('cemwp.sales.order_ttl_minutes', 60)));

        try {
            $response = Http::withBasicAuth((string) config('cemwp.payments.midtrans.server_key'), '')
                ->acceptJson()->asJson()->timeout(15)
                ->post($this->baseUrl().'/snap/v1/transactions', [
                    'transaction_details' => ['order_id' => $order->number, 'gross_amount' => (int) round((float) $order->grand_total)],
                    'customer_details' => array_filter([
                        'first_name' => $order->customer_name, 'email' => $order->customer_email, 'phone' => $order->customer_phone,
                    ]),
                    'expiry' => ['unit' => 'minutes', 'duration' => $minutes],
                    'callbacks' => ['finish' => route('shop.orders.show', $order->number)],
                ]);
        } catch (Throwable $e) {
            throw new SalesException('Tidak bisa menghubungi Midtrans: '.$e->getMessage());
        }

        if (! $response->successful() || ! $response->json('token')) {
            throw new SalesException('Midtrans menolak permintaan pembayaran ('.$response->status().').');
        }

        return ['token' => (string) $response->json('token'), 'redirect_url' => (string) $response->json('redirect_url')];
    }

    public function verifySignature(array $p): bool
    {
        $serverKey = (string) config('cemwp.payments.midtrans.server_key');
        if ($serverKey === '' || empty($p['signature_key']) || ! isset($p['order_id'], $p['status_code'], $p['gross_amount'])) {
            return false;
        }
        $expected = hash('sha512', $p['order_id'].$p['status_code'].$p['gross_amount'].$serverKey);

        return hash_equals($expected, (string) $p['signature_key']);
    }
}
