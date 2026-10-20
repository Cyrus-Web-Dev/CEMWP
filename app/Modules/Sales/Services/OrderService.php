<?php

namespace App\Modules\Sales\Services;

use App\Models\User;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Services\CustomerResolver;
use App\Modules\Sales\Events\OrderCancelled;
use App\Modules\Sales\Events\OrderNeedsReview;
use App\Modules\Sales\Events\OrderPaid;
use App\Modules\Sales\Events\OrderPlaced;
use App\Modules\Sales\Exceptions\SalesException;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderStatusHistory;
use App\Modules\Sales\Models\Payment;
use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Models\ShippingMethod;
use App\Modules\Warehouse\Contracts\FulfillmentGateway;
use App\Modules\Warehouse\Contracts\StockGateway;
use App\Modules\Warehouse\Exceptions\StockException;
use App\Modules\Warehouse\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;
use App\Services\ActivityLogger;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/**
 * Jantung modul Sales. Aturan keselamatan:
 *  - POS: order + pembayaran + pemotongan stok dalam SATU transaksi database. Stok kurang = tidak ada yang tersimpan.
 *  - Online: stok DICADANGKAN saat checkout (kedaluwarsa otomatis), dipotong saat dana diterima.
 *  - Semua pembuatan order idempoten (kunci advisory lock + unique idempotency_key): request kembar tidak membuat order ganda.
 *  - Event dikirim SETELAH commit; kegagalan listener (CRM, dll.) tidak membatalkan penjualan.
 */
class OrderService
{
    public function __construct(
        private PricingService $pricing,
        private CouponService $coupons,
        private ShiftService $shifts,
        private CustomerResolver $customers,
        private StockGateway $stock,
        private FulfillmentGateway $fulfillments,
    ) {
    }

    // ------------------------------------------------------------------ POS

    /**
     * Penjualan kasir (lunas di tempat).
     *
     * @param  array{items:array,payments?:array,customer_phone?:?string,customer_name?:?string,coupon_code?:?string,notes?:?string,idempotency_key?:?string}  $data
     */
    public function createPosSale(array $data, User $cashier): Order
    {
        $key = ! empty($data['idempotency_key']) ? 'pos:'.$cashier->id.':'.$data['idempotency_key'] : null;

        return DB::transaction(function () use ($data, $cashier, $key) {
            $this->lockKey($key);
            if ($key && ($existing = Order::query()->where('idempotency_key', $key)->first())) {
                return $existing->load(['items', 'payments']);
            }

            $shift = $this->shifts->current($cashier);
            if (! $shift) {
                throw new SalesException('Buka shift kasir dulu sebelum berjualan.');
            }

            $customer = $this->customers->findOrCreate([
                'name' => $data['customer_name'] ?? null, 'phone' => $data['customer_phone'] ?? null, 'source' => 'pos',
            ]);

            $quote = $this->pricing->quote([
                'items' => $data['items'] ?? [], 'channel' => Order::POS, 'coupon_code' => $data['coupon_code'] ?? null,
                'customer_id' => $customer?->id, 'warehouse_id' => $shift->warehouse_id,
            ]);

            [$allocations, $change] = $this->allocatePosPayments((float) $quote['grand_total'], $data['payments'] ?? []);

            $number = DocumentNumber::next('POS');
            $order = Order::create([
                'number' => $number, 'channel' => Order::POS, 'status' => Order::COMPLETED, 'payment_status' => 'paid',
                'stock_committed' => true,
                'customer_id' => $customer?->id, 'user_id' => $cashier->id, 'shift_id' => $shift->id, 'warehouse_id' => $shift->warehouse_id,
                'coupon_id' => $quote['coupon']['id'] ?? null, 'coupon_code' => $quote['coupon']['code'] ?? null,
                'subtotal' => $quote['subtotal'], 'discount_total' => $quote['discount_total'], 'shipping_cost' => 0,
                'tax_total' => $quote['tax_total'], 'grand_total' => $quote['grand_total'],
                'paid_total' => $quote['grand_total'], 'change_due' => $change,
                'customer_name' => $customer?->name ?? ($data['customer_name'] ?? null),
                'customer_phone' => $customer?->phone ?? Customer::normalizePhone($data['customer_phone'] ?? null),
                'customer_email' => $customer?->email,
                'total_weight_grams' => $quote['total_weight_grams'], 'notes' => $data['notes'] ?? null,
                'idempotency_key' => $key, 'placed_at' => now(), 'paid_at' => now(), 'completed_at' => now(),
            ]);

            $this->storeItems($order, $quote['lines']);

            // Stok terpotong di transaksi yang SAMA. InsufficientStockException membatalkan seluruh penjualan.
            $this->stock->deductForSale($shift->warehouse_id, $this->stockItems($quote['lines']), $number, "sale:{$number}", $cashier->id);

            foreach ($allocations as $a) {
                Payment::create([
                    'order_id' => $order->id, 'payment_method_id' => $a['method']->id, 'method_code' => $a['method']->code,
                    'method_name' => $a['method']->name, 'kind' => $a['method']->kind,
                    'amount' => $a['amount'], 'tendered' => $a['tendered'], 'status' => Payment::PAID,
                    'reference' => $a['reference'], 'paid_at' => now(),
                ]);
            }

            if (! empty($quote['coupon'])) {
                $this->coupons->redeem(\App\Modules\Sales\Models\Coupon::findOrFail($quote['coupon']['id']), $order, (float) $quote['discount_total'], $customer?->id);
            }

            $this->history($order, null, Order::COMPLETED, 'Penjualan kasir', $cashier->id);
            ActivityLogger::log('order.pos', "Penjualan {$number} Rp ".number_format((float) $order->grand_total, 0, ',', '.'), $order, $cashier->id);
            $this->dispatchPlaced($order);
            $this->dispatchPaid($order, $quote['lines']);

            return $order->load(['items', 'payments']);
        });
    }

    /**
     * Bagikan pembayaran kasir. Non-tunai dialokasikan apa adanya; tunai mengisi sisa dan menghasilkan kembalian.
     *
     * @return array{0:array<int,array<string,mixed>>,1:float} [alokasi, kembalian]
     */
    private function allocatePosPayments(float $total, array $payments): array
    {
        if ($total <= 0) {
            return [[], 0.0]; // gratis (mis. kupon 100%)
        }
        if ($payments === []) {
            throw new SalesException('Pilih metode pembayaran.');
        }

        $methods = PaymentMethod::query()->forPos()->whereIn('code', array_column($payments, 'method'))->get()->keyBy('code');

        $noncash = [];
        $cashTendered = 0.0;
        $cashMethod = null;
        foreach ($payments as $p) {
            $method = $methods->get($p['method'] ?? '');
            $amount = round((float) ($p['amount'] ?? 0), 0);
            if (! $method) {
                throw new SalesException('Metode pembayaran tidak tersedia di kasir.');
            }
            if ($amount <= 0) {
                throw new SalesException("Jumlah pembayaran {$method->name} harus lebih dari nol.");
            }
            if ($method->kind === 'cash') {
                $cashTendered += $amount;
                $cashMethod = $method;

                continue;
            }
            if ($method->requires_reference && trim((string) ($p['reference'] ?? '')) === '') {
                throw new SalesException("Isi nomor referensi / approval untuk {$method->name}.");
            }
            $noncash[] = ['method' => $method, 'amount' => $amount, 'tendered' => null, 'reference' => trim((string) ($p['reference'] ?? '')) ?: null];
        }

        $noncashSum = array_sum(array_column($noncash, 'amount'));
        if ($noncashSum > $total) {
            throw new SalesException('Pembayaran non-tunai melebihi total belanja.');
        }

        $cashAllocated = min($cashTendered, $total - $noncashSum);
        if ($noncashSum + $cashAllocated < $total) {
            throw new SalesException('Pembayaran kurang Rp '.number_format($total - $noncashSum - $cashAllocated, 0, ',', '.').'.');
        }

        $rows = $noncash;
        if ($cashMethod && $cashAllocated > 0) {
            $rows[] = ['method' => $cashMethod, 'amount' => $cashAllocated, 'tendered' => $cashTendered, 'reference' => null];
        }

        return [$rows, (float) max(0, $cashTendered - $cashAllocated)];
    }

    // ------------------------------------------------------------------ Online

    /**
     * Pesanan e-commerce / aplikasi mobile.
     * COD: stok langsung terpotong, pesanan langsung diproses gudang. Selain itu: stok dicadangkan sampai dibayar.
     *
     * @param  array{items:array,payment_method:string,shipping_method_id:int,coupon_code?:?string,idempotency_key?:?string,customer_name?:?string,customer_phone?:?string,customer_email?:?string,recipient_name?:?string,recipient_phone?:?string,shipping_address?:?string,shipping_city?:?string,shipping_postal_code?:?string,notes?:?string}  $data
     */
    public function createOnlineOrder(array $data, ?Customer $customer = null): Order
    {
        $key = ! empty($data['idempotency_key']) ? 'web:'.($customer?->id ?? 'guest').':'.$data['idempotency_key'] : null;

        return DB::transaction(function () use ($data, $customer, $key) {
            $this->lockKey($key);
            if ($key && ($existing = Order::query()->where('idempotency_key', $key)->first())) {
                return $existing->load(['items', 'payments']);
            }

            $method = PaymentMethod::query()->forOnline()->where('code', $data['payment_method'] ?? '')->first();
            if (! $method) {
                throw new SalesException('Metode pembayaran tidak tersedia.');
            }
            $shipping = ShippingMethod::query()->active()->find($data['shipping_method_id'] ?? 0);
            if (! $shipping) {
                throw new SalesException('Pilih metode pengiriman.');
            }
            if (! $shipping->is_pickup) {
                foreach (['recipient_name' => 'nama penerima', 'recipient_phone' => 'nomor HP penerima', 'shipping_address' => 'alamat', 'shipping_city' => 'kota'] as $field => $label) {
                    if (trim((string) ($data[$field] ?? '')) === '') {
                        throw new SalesException("Lengkapi {$label} pengiriman.");
                    }
                }
            }

            $warehouseId = Warehouse::onlineId();
            if (! $warehouseId) {
                throw new SalesException('Lokasi stok online belum diatur. Hubungi toko.');
            }

            $customer ??= $this->customers->findOrCreate([
                'name' => $data['customer_name'] ?? ($data['recipient_name'] ?? null),
                'phone' => $data['customer_phone'] ?? ($data['recipient_phone'] ?? null),
                'email' => $data['customer_email'] ?? null, 'source' => 'web',
            ]);

            $quote = $this->pricing->quote([
                'items' => $data['items'] ?? [], 'channel' => Order::ECOMMERCE, 'coupon_code' => $data['coupon_code'] ?? null,
                'customer_id' => $customer?->id, 'shipping_method_id' => $shipping->id, 'warehouse_id' => $warehouseId,
            ]);

            $isCod = $method->kind === 'cod';
            $number = DocumentNumber::next('ORD');
            $expiresAt = now()->addMinutes((int) config('cemwp.sales.order_ttl_minutes', 60));
            $recipientName = $shipping->is_pickup ? ($customer?->name ?? $data['customer_name'] ?? 'Pelanggan') : $data['recipient_name'];

            $order = Order::create([
                'number' => $number, 'channel' => Order::ECOMMERCE,
                'status' => $isCod ? Order::PROCESSING : Order::AWAITING_PAYMENT, 'payment_status' => 'unpaid',
                'customer_id' => $customer?->id, 'warehouse_id' => $warehouseId,
                'coupon_id' => $quote['coupon']['id'] ?? null, 'coupon_code' => $quote['coupon']['code'] ?? null,
                'subtotal' => $quote['subtotal'], 'discount_total' => $quote['discount_total'], 'shipping_cost' => $quote['shipping_cost'],
                'tax_total' => $quote['tax_total'], 'grand_total' => $quote['grand_total'],
                'customer_name' => $customer?->name ?? ($data['customer_name'] ?? $data['recipient_name'] ?? null),
                'customer_phone' => $customer?->phone ?? Customer::normalizePhone($data['customer_phone'] ?? $data['recipient_phone'] ?? null),
                'customer_email' => $customer?->email ?? Customer::normalizeEmail($data['customer_email'] ?? null),
                'shipping_method_id' => $shipping->id, 'shipping_label' => $shipping->label(),
                'recipient_name' => $recipientName,
                'recipient_phone' => $shipping->is_pickup ? ($customer?->phone) : $data['recipient_phone'],
                'shipping_address' => $shipping->is_pickup ? 'Ambil di toko' : $data['shipping_address'],
                'shipping_city' => $shipping->is_pickup ? null : $data['shipping_city'],
                'shipping_postal_code' => $data['shipping_postal_code'] ?? null,
                'total_weight_grams' => $quote['total_weight_grams'], 'notes' => $data['notes'] ?? null,
                'idempotency_key' => $key, 'expires_at' => $isCod ? null : $expiresAt, 'placed_at' => now(),
            ]);
            $this->storeItems($order, $quote['lines']);

            Payment::create([
                'order_id' => $order->id, 'payment_method_id' => $method->id, 'method_code' => $method->code,
                'method_name' => $method->name, 'kind' => $method->kind, 'amount' => $quote['grand_total'],
                'status' => Payment::PENDING, 'gateway' => $method->kind === 'gateway' ? 'midtrans' : null,
            ]);

            $items = $this->stockItems($quote['lines']);
            if ($isCod) {
                $this->stock->deductForSale($warehouseId, $items, $number, "sale:{$number}");
                $order->forceFill(['stock_committed' => true])->save();
                $this->createFulfillment($order);
            } else {
                $this->stock->reserve("order:{$number}", $warehouseId, $items, $expiresAt);
            }

            if (! empty($quote['coupon'])) {
                $this->coupons->redeem(\App\Modules\Sales\Models\Coupon::findOrFail($quote['coupon']['id']), $order, (float) $quote['discount_total'], $customer?->id);
            }

            $this->history($order, null, $order->status, $isCod ? 'Pesanan COD diterima' : 'Pesanan dibuat, menunggu pembayaran', null);
            ActivityLogger::log('order.online', "Pesanan online {$number} Rp ".number_format((float) $order->grand_total, 0, ',', '.'), $order);
            $this->dispatchPlaced($order);

            // Pesanan gratis (kupon 100% + ongkir 0): tidak ada yang perlu dibayar -> langsung diproses.
            if (! $isCod && (float) $quote['grand_total'] <= 0) {
                return $this->markPaid($order, $order->payments()->first(), null);
            }

            return $order->load(['items', 'payments']);
        });
    }

    // ------------------------------------------------------------------ Pembayaran

    /** Dana diterima (webhook gateway / konfirmasi petugas). Idempoten. */
    public function markPaid(Order $order, ?Payment $payment, ?int $userId = null, array $gatewayData = []): Order
    {
        return DB::transaction(function () use ($order, $payment, $userId, $gatewayData) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->payment_status === 'paid') {
                return $order; // sudah diproses (webhook ganda)
            }
            if (! in_array($order->status, [Order::AWAITING_PAYMENT, Order::EXPIRED, Order::CANCELLED], true)) {
                throw new SalesException('Pesanan ini tidak sedang menunggu pembayaran.');
            }

            $payment = $payment
                ? Payment::query()->lockForUpdate()->find($payment->id)
                : $order->payments()->lockForUpdate()->first();
            $wasLate = $order->status !== Order::AWAITING_PAYMENT;

            $allocated = $this->allocateStock($order, $userId);

            $payment?->forceFill([
                'status' => Payment::PAID, 'paid_at' => now(),
                'gateway_payload' => $gatewayData ?: $payment->gateway_payload,
            ])->save();

            $from = $order->status;
            $order->forceFill([
                'payment_status' => 'paid', 'paid_total' => $order->grand_total, 'paid_at' => now(), 'expires_at' => null,
                'status' => $allocated ? Order::PROCESSING : Order::NEEDS_REVIEW,
                'stock_committed' => $allocated, 'cancelled_at' => null, 'cancel_reason' => null,
            ])->save();

            if ($allocated) {
                $this->createFulfillment($order);
                $note = $wasLate ? 'Pembayaran terlambat diterima, stok dialokasikan ulang' : 'Pembayaran diterima';
                $this->history($order, $from, Order::PROCESSING, $note, $userId);
                ActivityLogger::log('order.paid', "Pembayaran {$order->number} diterima", $order, $userId);
                $this->dispatchPaid($order, $this->itemsPayload($order));
            } else {
                $this->history($order, $from, Order::NEEDS_REVIEW, 'Pembayaran masuk tetapi stok tidak lagi tersedia', $userId);
                ActivityLogger::log('order.needs_review', "Pesanan {$order->number}: dana diterima, stok habis", $order, $userId);
                $orderId = $order->id;
                DB::afterCommit(fn () => event(new OrderNeedsReview($orderId, 'Dana diterima tetapi stok tidak tersedia')));
            }

            return $order->load(['items', 'payments']);
        });
    }

    /** COD: barang diterima pelanggan dan uang diserahkan ke kurir -> dicatat lunas. */
    private function settleCod(Order $order): void
    {
        if ($order->payment_status === 'paid') {
            return;
        }
        $order->payments()->where('status', Payment::PENDING)->update(['status' => Payment::PAID, 'paid_at' => now()]);
        $order->forceFill(['payment_status' => 'paid', 'paid_total' => $order->grand_total, 'paid_at' => now()])->save();
        ActivityLogger::log('order.cod_paid', "COD {$order->number} lunas saat barang diterima", $order);
        $this->dispatchPaid($order, $this->itemsPayload($order));
    }

    // ------------------------------------------------------------------ Pembatalan

    /**
     * Batalkan pesanan yang belum dikirim. Stok dikembalikan/dilepas, kupon dipulihkan, tugas gudang dibatalkan.
     * Bila sudah dibayar, pembayaran ditandai "menunggu refund" (refund dilakukan manual oleh keuangan).
     */
    public function cancel(Order $order, string $reason, ?int $userId = null, bool $expired = false): Order
    {
        return DB::transaction(function () use ($order, $reason, $userId, $expired) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($order->status, [Order::AWAITING_PAYMENT, Order::PROCESSING, Order::NEEDS_REVIEW], true)) {
                throw new SalesException('Pesanan berstatus "'.(Order::STATUS_LABELS[$order->status] ?? $order->status).'" tidak bisa dibatalkan. Untuk pesanan yang sudah dikirim, gunakan retur.');
            }

            $from = $order->status;

            // 1. Tugas gudang (melempar StockException bila barang sudah dikirim)
            if ($order->isOnline() && $order->status !== Order::AWAITING_PAYMENT) {
                $this->fulfillments->cancelBySource('order', $order->id, $reason);
            }

            // 2. Stok: lepas cadangan, atau kembalikan yang sudah terpotong
            $this->stock->releaseReservation("order:{$order->number}");
            if ($order->stock_committed) {
                $this->stock->restock($order->warehouse_id, $this->stockItems($this->itemsPayload($order, true)), $order->number, "cancel:{$order->number}", $userId, 'order_cancelled');
            }

            // 3. Kupon & pembayaran
            $this->coupons->release($order);
            if ($order->payment_status === 'paid') {
                $order->payments()->where('status', Payment::PAID)->update(['status' => Payment::REFUND_PENDING]);
                $paymentStatus = 'refund_pending';
            } else {
                $order->payments()->where('status', Payment::PENDING)->update(['status' => $expired ? Payment::EXPIRED : Payment::FAILED]);
                $paymentStatus = $order->payment_status;
            }

            $to = $expired ? Order::EXPIRED : Order::CANCELLED;
            $order->forceFill([
                'status' => $to, 'payment_status' => $paymentStatus, 'stock_committed' => false,
                'cancelled_at' => now(), 'cancel_reason' => $reason, 'expires_at' => null,
            ])->save();

            $this->history($order, $from, $to, $reason, $userId);
            ActivityLogger::log('order.cancelled', "Pesanan {$order->number} dibatalkan: {$reason}", $order, $userId);
            $orderId = $order->id;
            DB::afterCommit(fn () => event(new OrderCancelled($orderId, $reason)));

            return $order->load(['items', 'payments']);
        });
    }

    /** Keuangan sudah mengembalikan dana secara manual untuk pesanan yang dibatalkan setelah dibayar. */
    public function markRefunded(Order $order, ?int $userId): Order
    {
        return DB::transaction(function () use ($order, $userId) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->payment_status !== 'refund_pending') {
                throw new SalesException('Pesanan ini tidak sedang menunggu refund.');
            }
            $order->payments()->where('status', Payment::REFUND_PENDING)->update(['status' => Payment::REFUNDED]);
            $order->forceFill(['payment_status' => 'refunded'])->save();
            $this->history($order, $order->status, $order->status, 'Dana sudah dikembalikan ke pelanggan', $userId);
            ActivityLogger::log('order.refunded', "Refund pesanan {$order->number} selesai", $order, $userId);

            return $order;
        });
    }

    /** Dijalankan scheduler: pesanan yang lewat batas waktu bayar -> kedaluwarsa (stok dilepas). */
    public function expireOverdue(): int
    {
        $ids = Order::query()
            ->where('status', Order::AWAITING_PAYMENT)->whereNotNull('expires_at')->where('expires_at', '<', now())
            ->limit(200)->pluck('id');

        $n = 0;
        foreach ($ids as $id) {
            try {
                $this->cancel(Order::findOrFail($id), 'Batas waktu pembayaran habis', null, true);
                $n++;
            } catch (SalesException|StockException) {
                // status berubah di tengah jalan (dibayar tepat waktu): lewati
            }
        }

        return $n;
    }

    // ------------------------------------------------------------------ Sinkron dari gudang

    /**
     * Status pengiriman dari Warehouse (event FulfillmentStatusChanged) -> status pesanan.
     * shipped -> Dikirim; delivered -> Selesai (+COD lunas); cancelled oleh gudang -> perlu ditinjau.
     */
    public function syncFromFulfillment(int $orderId, string $to, ?string $courier, ?string $tracking): void
    {
        DB::transaction(function () use ($orderId, $to, $courier, $tracking) {
            $order = Order::query()->lockForUpdate()->find($orderId);
            if (! $order) {
                return;
            }
            $from = $order->status;

            if ($to === 'shipped' && $from === Order::PROCESSING) {
                $order->forceFill(['status' => Order::SHIPPED, 'courier' => $courier, 'tracking_number' => $tracking])->save();
                $this->history($order, $from, Order::SHIPPED, 'Diserahkan ke kurir'.($courier ? " ({$courier})" : ''), null);
            } elseif ($to === 'delivered' && in_array($from, [Order::PROCESSING, Order::SHIPPED], true)) {
                $order->forceFill(['status' => Order::COMPLETED, 'completed_at' => now()])->save();
                $this->history($order, $from, Order::COMPLETED, 'Pesanan diterima pelanggan', null);
                $this->settleCod($order);
            } elseif ($to === 'cancelled' && $from === Order::PROCESSING) {
                // Gudang membatalkan tugas (barang rusak/hilang) padahal pesanan masih aktif: perlu keputusan manusia.
                $order->forceFill(['status' => Order::NEEDS_REVIEW])->save();
                $this->history($order, $from, Order::NEEDS_REVIEW, 'Gudang membatalkan tugas pengiriman', null);
                $id = $order->id;
                DB::afterCommit(fn () => event(new OrderNeedsReview($id, 'Gudang membatalkan tugas pengiriman')));
            }
        });
    }

    // ------------------------------------------------------------------ Internal

    private function storeItems(Order $order, array $lines): void
    {
        foreach ($lines as $l) {
            $order->items()->create([
                'product_id' => $l['product_id'], 'sku' => $l['sku'], 'name' => $l['name'],
                'unit_price' => $l['unit_price'], 'cost_price' => $l['cost_price'], 'quantity' => $l['quantity'],
                'discount' => $l['discount'], 'line_total' => $l['line_total'], 'weight_grams' => $l['weight_grams'],
            ]);
        }
    }

    /** @return array<int,array{product_id:int,quantity:int}> */
    private function stockItems(array $lines): array
    {
        return array_map(fn ($l) => ['product_id' => $l['product_id'], 'quantity' => $l['quantity']], $lines);
    }

    /** Baris pesanan sebagai array sederhana: [product_id, quantity, ...]. */
    private function itemsPayload(Order $order, bool $withLines = false): array
    {
        return $order->items()->get(['product_id', 'quantity'])->map(fn ($i) => ['product_id' => $i->product_id, 'quantity' => $i->quantity])->all();
    }

    /**
     * Potong stok pesanan online yang sudah dibayar. Memakai reservasi bila masih aktif; bila sudah kedaluwarsa,
     * mencoba mencadangkan ulang. Mengembalikan false bila stok sudah tidak ada (pesanan perlu ditinjau).
     */
    private function allocateStock(Order $order, ?int $userId): bool
    {
        $ref = "order:{$order->number}";
        $moved = $this->stock->commitReservation($ref, $order->number, $userId);

        $items = $this->itemsPayload($order);
        $tracked = Product::withTrashed()->whereIn('id', array_column($items, 'product_id'))->where('track_stock', true)->exists();
        if ($moved->isNotEmpty() || ! $tracked) {
            return true;
        }

        try {
            $this->stock->reserve($ref, $order->warehouse_id, $items, now()->addMinutes(5));
            $this->stock->commitReservation($ref, $order->number, $userId);

            return true;
        } catch (StockException) {
            return false;
        }
    }

    private function createFulfillment(Order $order): void
    {
        $order->loadMissing('items');
        $this->fulfillments->createFromOrder([
            'source_type' => 'order', 'source_id' => $order->id, 'source_number' => $order->number, 'channel' => 'ecommerce',
            'warehouse_id' => $order->warehouse_id,
            'recipient_name' => $order->recipient_name, 'recipient_phone' => $order->recipient_phone,
            'shipping_address' => trim($order->shipping_address.($order->shipping_city ? ', '.$order->shipping_city : '').($order->shipping_postal_code ? ' '.$order->shipping_postal_code : '')),
            'notes' => $order->notes,
            'items' => $order->items->map(fn ($i) => ['product_id' => $i->product_id, 'quantity' => $i->quantity])->all(),
        ]);
    }

    private function history(Order $order, ?string $from, string $to, ?string $note, ?int $userId): void
    {
        OrderStatusHistory::create([
            'order_id' => $order->id, 'from_status' => $from, 'to_status' => $to, 'note' => $note ? mb_substr($note, 0, 250) : null,
            'user_id' => $userId ?? auth()->id(), 'created_at' => now(),
        ]);
    }

    private function dispatchPlaced(Order $order): void
    {
        $id = $order->id;
        $channel = $order->channel;
        DB::afterCommit(fn () => event(new OrderPlaced($id, $channel)));
    }

    /** @param array<int,array<string,mixed>> $lines */
    private function dispatchPaid(Order $order, array $lines): void
    {
        $event = new OrderPaid(
            $order->id, $order->customer_id, $order->channel, (float) $order->grand_total, now()->toDateTimeString(),
            array_map(fn ($l) => ['product_id' => (int) $l['product_id'], 'quantity' => (int) $l['quantity']], $lines)
        );
        DB::afterCommit(fn () => event($event));
    }

    /** Serialisasi permintaan dengan kunci idempotensi yang sama (kunci advisory transaksi PostgreSQL). */
    private function lockKey(?string $key): void
    {
        if ($key) {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [$key]);
        }
    }
}
