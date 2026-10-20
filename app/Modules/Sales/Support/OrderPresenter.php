<?php

namespace App\Modules\Sales\Support;

use App\Modules\Sales\Models\Order;

/** Bentuk JSON pesanan yang konsisten untuk etalase web & API mobile. */
class OrderPresenter
{
    public static function toArray(Order $order, bool $detail = true): array
    {
        $payment = $order->relationLoaded('payments') ? $order->payments->first() : $order->payments()->first();

        $base = [
            'number' => $order->number,
            'channel' => $order->channel,
            'status' => $order->status,
            'status_label' => Order::STATUS_LABELS[$order->status] ?? $order->status,
            'payment_status' => $order->payment_status,
            'payment_status_label' => Order::PAYMENT_LABELS[$order->payment_status] ?? $order->payment_status,
            'subtotal' => (float) $order->subtotal,
            'discount_total' => (float) $order->discount_total,
            'shipping_cost' => (float) $order->shipping_cost,
            'tax_total' => (float) $order->tax_total,
            'grand_total' => (float) $order->grand_total,
            'coupon_code' => $order->coupon_code,
            'placed_at' => $order->placed_at?->toIso8601String(),
            'expires_at' => $order->expires_at?->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(),
            'payment' => $payment ? [
                'method' => $payment->method_code, 'method_name' => $payment->method_name, 'kind' => $payment->kind,
                'status' => $payment->status, 'redirect_url' => $payment->status === 'pending' ? $payment->redirect_url : null,
            ] : null,
        ];
        if (! $detail) {
            return $base;
        }

        return $base + [
            'shipping' => [
                'label' => $order->shipping_label, 'recipient_name' => $order->recipient_name, 'recipient_phone' => $order->recipient_phone,
                'address' => $order->shipping_address, 'city' => $order->shipping_city, 'postal_code' => $order->shipping_postal_code,
                'courier' => $order->courier, 'tracking_number' => $order->tracking_number,
            ],
            'notes' => $order->notes,
            'items' => $order->items->map(fn ($i) => [
                'product_id' => $i->product_id, 'sku' => $i->sku, 'name' => $i->name, 'unit_price' => (float) $i->unit_price,
                'quantity' => $i->quantity, 'discount' => (float) $i->discount, 'line_total' => (float) $i->line_total,
            ])->values(),
            'timeline' => $order->relationLoaded('histories') ? $order->histories->map(fn ($h) => [
                'status' => $h->to_status, 'label' => Order::STATUS_LABELS[$h->to_status] ?? $h->to_status,
                'note' => $h->note, 'at' => $h->created_at?->toIso8601String(),
            ])->values() : [],
        ];
    }
}
