<?php

namespace App\Modules\Sales\Services;

use App\Modules\Crm\Models\Customer;
use App\Modules\Sales\Exceptions\SalesException;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\Payment;
use Throwable;

/**
 * Pintu tunggal checkout online (dipakai etalase web dan API mobile):
 * buat pesanan (transaksi DB) -> bila gateway, buat tagihan di luar transaksi; gagal = pesanan dibatalkan rapi.
 */
class CheckoutService
{
    public function __construct(private OrderService $orders, private PaymentService $payments)
    {
    }

    public function placeOnlineOrder(array $data, ?Customer $customer): Order
    {
        $order = $this->orders->createOnlineOrder($data, $customer);

        $payment = $order->payments->first();
        if ($payment && $payment->kind === 'gateway' && $payment->status === Payment::PENDING && ! $payment->redirect_url
            && $order->status === Order::AWAITING_PAYMENT) {
            try {
                $this->payments->startGateway($order);
            } catch (Throwable $e) {
                report($e);
                $this->orders->cancel($order->fresh(), 'Gagal membuat tagihan pembayaran online', null);
                throw new SalesException('Pembayaran online sedang bermasalah. Coba lagi atau pilih metode pembayaran lain.');
            }
        }

        return $order->fresh(['items', 'payments']);
    }
}
