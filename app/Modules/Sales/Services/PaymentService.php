<?php

namespace App\Modules\Sales\Services;

use App\Models\User;
use App\Modules\Sales\Exceptions\SalesException;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\Payment;
use App\Modules\Sales\Payments\MidtransGateway;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(private OrderService $orders, private MidtransGateway $midtrans)
    {
    }

    /** Buat transaksi Snap untuk pesanan (dipanggil SETELAH order tersimpan; jangan di dalam transaksi DB). */
    public function startGateway(Order $order): Payment
    {
        $payment = $order->payments()->where('kind', 'gateway')->where('status', Payment::PENDING)->firstOrFail();
        if ($payment->redirect_url) {
            return $payment; // sudah dibuat (replay idempoten)
        }

        $snap = $this->midtrans->createTransaction($order);
        $payment->forceFill([
            'gateway' => 'midtrans', 'gateway_ref' => $order->number,
            'redirect_url' => $snap['redirect_url'], 'gateway_payload' => ['snap_token' => $snap['token']],
        ])->save();

        return $payment;
    }

    /** Petugas mengonfirmasi pembayaran manual (transfer bank) sudah masuk. */
    public function confirmManual(Order $order, ?string $reference, User $by): Order
    {
        $payment = $order->payments()->whereIn('kind', ['manual'])->whereIn('status', [Payment::PENDING, Payment::EXPIRED, Payment::FAILED])->first();
        if (! $payment) {
            throw new SalesException('Pesanan ini tidak punya pembayaran manual yang menunggu konfirmasi.');
        }
        if ($reference !== null && trim($reference) !== '') {
            $payment->forceFill(['reference' => mb_substr(trim($reference), 0, 100)])->save();
        }
        ActivityLogger::log('payment.confirmed', "Pembayaran manual {$order->number} dikonfirmasi petugas", $order, $by->id);

        return $this->orders->markPaid($order, $payment, $by->id);
    }

    /**
     * Notifikasi Midtrans (HTTP notification). Mengembalikan teks hasil untuk dicatat; melempar SalesException
     * bila tanda tangan tidak valid (controller membalas 403).
     */
    public function handleMidtrans(array $payload): string
    {
        if (! $this->midtrans->verifySignature($payload)) {
            throw new SalesException('Signature tidak valid.');
        }

        $payment = Payment::query()->where('gateway', 'midtrans')->where('gateway_ref', $payload['order_id'] ?? '')->first();
        if (! $payment) {
            return 'ignored: pembayaran tidak dikenal';
        }
        $order = $payment->order;

        // Perlindungan: nominal harus sama persis dengan tagihan.
        if ((int) round((float) $payload['gross_amount']) !== (int) round((float) $order->grand_total)) {
            Log::warning("[Midtrans] Nominal tidak cocok untuk {$order->number}: {$payload['gross_amount']} vs {$order->grand_total}");

            return 'ignored: nominal tidak cocok';
        }

        $status = $payload['transaction_status'] ?? '';
        $fraud = $payload['fraud_status'] ?? 'accept';
        $paid = $status === 'settlement' || ($status === 'capture' && $fraud === 'accept');

        if ($paid) {
            $this->orders->markPaid($order, $payment, null, $payload);

            return 'paid';
        }

        if (in_array($status, ['deny', 'cancel', 'expire', 'failure'], true)) {
            DB::transaction(function () use ($payment, $order, $status, $payload) {
                $payment->forceFill(['status' => $status === 'expire' ? Payment::EXPIRED : Payment::FAILED, 'gateway_payload' => $payload])->save();
                if ($order->fresh()->status === Order::AWAITING_PAYMENT) {
                    $this->orders->cancel($order, "Pembayaran {$status} (Midtrans)", null, $status === 'expire');
                }
            });

            return $status;
        }

        $payment->forceFill(['gateway_payload' => $payload])->save(); // pending dan sejenisnya

        return 'noted: '.$status;
    }
}
