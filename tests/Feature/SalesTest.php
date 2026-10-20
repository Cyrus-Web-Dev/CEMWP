<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Crm\Models\Customer;
use App\Modules\Sales\Exceptions\SalesException;
use App\Modules\Sales\Models\Coupon;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\Payment;
use App\Modules\Sales\Models\PaymentMethod;
use App\Modules\Sales\Models\ShippingMethod;
use App\Modules\Sales\Services\CheckoutService;
use App\Modules\Sales\Services\OrderService;
use App\Modules\Sales\Services\PaymentService;
use App\Modules\Sales\Services\ReturnService;
use App\Modules\Sales\Services\ShiftService;
use App\Modules\Warehouse\Exceptions\InsufficientStockException;
use App\Modules\Warehouse\Models\Fulfillment;
use App\Modules\Warehouse\Models\Inventory;
use App\Modules\Warehouse\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Services\FulfillmentService;
use App\Modules\Warehouse\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Menguji alur POS & e-commerce end-to-end di level service + beberapa endpoint HTTP.
 * Jalankan: php artisan test   (butuh PostgreSQL kosong "cemwp_test")
 */
class SalesTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;
    private OrderService $orders;
    private Warehouse $store;
    private Warehouse $online;
    private Product $coffee;
    private Product $filter;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); // halaman Inertia dirender tanpa manifest build
        $this->seed([RolePermissionSeeder::class, SalesSeeder::class]);

        $this->stock = app(StockService::class);
        $this->orders = app(OrderService::class);

        $this->store = Warehouse::create(['code' => 'TK-1', 'name' => 'Toko 1', 'type' => 'store']);
        $this->online = Warehouse::create(['code' => 'ONL', 'name' => 'Online', 'type' => 'online']);

        $this->coffee = $this->product('KOPI-1', 'Kopi Gayo', 50000);
        $this->filter = $this->product('FLT-1', 'Filter V60', 20000);
        foreach ([$this->coffee, $this->filter] as $p) {
            $this->stock->receive($p->id, $this->store->id, 10, 10000.0);
            $this->stock->receive($p->id, $this->online->id, 10, 10000.0);
        }

        $this->cashier = User::factory()->create();
        $this->cashier->assignRole('cashier');
    }

    private function product(string $sku, string $name, float $price): Product
    {
        return Product::create([
            'sku' => $sku, 'name' => $name, 'selling_price' => $price, 'cost_price' => $price / 2,
            'weight_grams' => 300, 'is_active' => true, 'is_sellable_online' => true, 'track_stock' => true,
        ]);
    }

    private function onHand(Product $p, Warehouse $w): int
    {
        return (int) Inventory::where('product_id', $p->id)->where('warehouse_id', $w->id)->value('qty_on_hand');
    }

    private function reserved(Product $p, Warehouse $w): int
    {
        return (int) Inventory::where('product_id', $p->id)->where('warehouse_id', $w->id)->value('qty_reserved');
    }

    private function openShift(): void
    {
        app(ShiftService::class)->open($this->cashier, $this->store->id, 100000);
    }

    private function posSale(array $overrides = []): Order
    {
        return $this->orders->createPosSale($overrides + [
            'idempotency_key' => 'key-'.uniqid(),
            'items' => [['product_id' => $this->coffee->id, 'quantity' => 2], ['product_id' => $this->filter->id, 'quantity' => 1]],
            'payments' => [['method' => 'CASH', 'amount' => 150000]],
        ], $this->cashier);
    }

    private function onlineData(array $overrides = []): array
    {
        return $overrides + [
            'idempotency_key' => 'web-'.uniqid(),
            'items' => [['product_id' => $this->coffee->id, 'quantity' => 2]],
            'payment_method' => 'BANK_TRANSFER',
            'shipping_method_id' => ShippingMethod::where('is_pickup', true)->value('id'),
            'customer_name' => 'Rina', 'customer_phone' => '0812-3456-7890',
        ];
    }

    // ------------------------------------------------------------------ POS

    public function test_pos_sale_deducts_stock_records_payment_and_change(): void
    {
        $this->openShift();
        $order = $this->posSale(); // total 2x50.000 + 20.000 = 120.000, tunai 150.000

        $this->assertSame(Order::COMPLETED, $order->status);
        $this->assertSame('120000.00', $order->grand_total);
        $this->assertSame('30000.00', $order->change_due);
        $this->assertSame(8, $this->onHand($this->coffee, $this->store));
        $this->assertSame(9, $this->onHand($this->filter, $this->store));
        $this->assertSame('120000.00', $order->payments->first()->amount);
        $this->assertSame('150000.00', $order->payments->first()->tendered);
    }

    public function test_pos_sale_requires_an_open_shift(): void
    {
        $this->expectException(SalesException::class);
        $this->posSale();
    }

    public function test_pos_sale_with_insufficient_stock_rolls_back_everything(): void
    {
        $this->openShift();

        try {
            $this->posSale(['items' => [['product_id' => $this->coffee->id, 'quantity' => 11]], 'payments' => [['method' => 'CASH', 'amount' => 999999]]]);
            $this->fail('Seharusnya gagal karena stok kurang');
        } catch (InsufficientStockException) {
            $this->assertSame(0, Order::count());
            $this->assertSame(10, $this->onHand($this->coffee, $this->store));
        }
    }

    public function test_pos_same_idempotency_key_creates_one_order_and_deducts_once(): void
    {
        $this->openShift();
        $a = $this->posSale(['idempotency_key' => 'retry-me-123']);
        $b = $this->posSale(['idempotency_key' => 'retry-me-123']);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Order::count());
        $this->assertSame(8, $this->onHand($this->coffee, $this->store));
    }

    public function test_pos_split_payment_validates_reference_and_amount(): void
    {
        $this->openShift();

        try { // QRIS wajib referensi
            $this->posSale(['payments' => [['method' => 'QRIS', 'amount' => 120000]]]);
            $this->fail('Seharusnya butuh referensi');
        } catch (SalesException $e) {
            $this->assertStringContainsString('referensi', $e->getMessage());
        }

        try { // pembayaran kurang
            $this->posSale(['payments' => [['method' => 'CASH', 'amount' => 100000]]]);
            $this->fail('Seharusnya kurang bayar');
        } catch (SalesException $e) {
            $this->assertStringContainsString('kurang', $e->getMessage());
        }

        $order = $this->posSale(['payments' => [['method' => 'QRIS', 'amount' => 70000, 'reference' => 'REF1'], ['method' => 'CASH', 'amount' => 60000]]]);
        $this->assertCount(2, $order->payments);
        $this->assertSame('10000.00', $order->change_due); // tunai 60.000, perlu 50.000
    }

    // ------------------------------------------------------------------ Kupon

    public function test_coupon_discounts_and_per_customer_limit_and_release_on_cancel(): void
    {
        Coupon::create(['code' => 'HEMAT10', 'name' => 'Hemat', 'type' => 'percent', 'value' => 10, 'min_subtotal' => 0, 'channel' => 'all', 'usage_limit_per_customer' => 1, 'is_active' => true]);

        $first = $this->orders->createOnlineOrder($this->onlineData(['coupon_code' => 'hemat10']));
        $this->assertSame('10000.00', $first->discount_total); // 10% dari 100.000
        $this->assertSame('90000.00', $first->grand_total);
        $this->assertSame(1, Coupon::first()->used_count);

        try { // pelanggan yang sama tidak boleh memakai lagi
            $this->orders->createOnlineOrder($this->onlineData(['coupon_code' => 'HEMAT10']));
            $this->fail('Seharusnya ditolak');
        } catch (SalesException $e) {
            $this->assertStringContainsString('batas', $e->getMessage());
        }

        $this->orders->cancel($first, 'tes');
        $this->assertSame(0, Coupon::first()->used_count); // kuota pulih
    }

    // ------------------------------------------------------------------ Online

    public function test_online_order_reserves_then_payment_commits_stock_and_creates_fulfillment(): void
    {
        $order = $this->orders->createOnlineOrder($this->onlineData());
        $this->assertSame(Order::AWAITING_PAYMENT, $order->status);
        $this->assertSame(2, $this->reserved($this->coffee, $this->online));
        $this->assertSame(10, $this->onHand($this->coffee, $this->online)); // belum terpotong

        $staff = User::factory()->create();
        $paid = app(PaymentService::class)->confirmManual($order, 'TF-123', $staff);

        $this->assertSame(Order::PROCESSING, $paid->status);
        $this->assertSame('paid', $paid->payment_status);
        $this->assertSame(8, $this->onHand($this->coffee, $this->online));
        $this->assertSame(0, $this->reserved($this->coffee, $this->online));
        $this->assertTrue($paid->stock_committed);
        $this->assertSame(1, Fulfillment::where('source_type', 'order')->where('source_id', $order->id)->count());

        // CRM: profil pelanggan ikut terisi (event OrderPaid)
        $customer = Customer::first();
        $this->assertSame(1, $customer->orders_count);
        $this->assertSame(1, $customer->online_orders_count);
    }

    public function test_online_order_cannot_oversell_reserved_stock(): void
    {
        $this->orders->createOnlineOrder($this->onlineData(['items' => [['product_id' => $this->coffee->id, 'quantity' => 8]]]));

        $this->expectException(InsufficientStockException::class);
        $this->orders->createOnlineOrder($this->onlineData(['items' => [['product_id' => $this->coffee->id, 'quantity' => 3]], 'customer_phone' => '0813-0000-1111']));
    }

    public function test_online_same_idempotency_key_creates_one_order(): void
    {
        $data = $this->onlineData(['idempotency_key' => 'same-web-key-1']);
        $a = $this->orders->createOnlineOrder($data);
        $b = $this->orders->createOnlineOrder($data);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(2, $this->reserved($this->coffee, $this->online)); // bukan 4
    }

    public function test_cod_deducts_immediately_and_delivery_settles_payment(): void
    {
        $order = $this->orders->createOnlineOrder($this->onlineData(['payment_method' => 'COD']));
        $this->assertSame(Order::PROCESSING, $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame(8, $this->onHand($this->coffee, $this->online));

        $svc = app(FulfillmentService::class);
        $f = Fulfillment::where('source_id', $order->id)->firstOrFail();
        $svc->start($f, null);
        $svc->savePicked($f, [$f->items()->first()->id => 2]);
        $svc->pack($f);
        $svc->ship($f, 'JNE', 'RESI-1');
        $this->assertSame(Order::SHIPPED, $order->fresh()->status);
        $this->assertSame('RESI-1', $order->fresh()->tracking_number);

        $svc->deliver($f);
        $done = $order->fresh();
        $this->assertSame(Order::COMPLETED, $done->status);
        $this->assertSame('paid', $done->payment_status); // COD lunas saat barang diterima
    }

    public function test_cancel_unpaid_releases_reservation_and_cancel_paid_restocks(): void
    {
        $a = $this->orders->createOnlineOrder($this->onlineData());
        $this->orders->cancel($a, 'salah pesan');
        $this->assertSame(0, $this->reserved($this->coffee, $this->online));
        $this->assertSame(Order::CANCELLED, $a->fresh()->status);

        $b = $this->orders->createOnlineOrder($this->onlineData(['customer_phone' => '0813-0000-2222']));
        app(PaymentService::class)->confirmManual($b, null, User::factory()->create());
        $this->assertSame(8, $this->onHand($this->coffee, $this->online));

        $cancelled = $this->orders->cancel($b->fresh(), 'minta batal');
        $this->assertSame(10, $this->onHand($this->coffee, $this->online)); // stok kembali
        $this->assertSame('refund_pending', $cancelled->payment_status);
        $this->assertSame('cancelled', Fulfillment::where('source_id', $b->id)->first()->status->value);
    }

    public function test_unpaid_orders_expire_and_late_payment_is_handled(): void
    {
        $order = $this->orders->createOnlineOrder($this->onlineData());
        $order->update(['expires_at' => now()->subMinute()]);

        $this->assertSame(1, $this->orders->expireOverdue());
        $this->assertSame(Order::EXPIRED, $order->fresh()->status);
        $this->assertSame(0, $this->reserved($this->coffee, $this->online));

        // pembayaran terlambat, stok masih ada -> dialokasikan ulang
        $late = app(PaymentService::class)->confirmManual($order->fresh(), null, User::factory()->create());
        $this->assertSame(Order::PROCESSING, $late->status);
        $this->assertSame(8, $this->onHand($this->coffee, $this->online));

        // pembayaran terlambat, stok SUDAH habis -> perlu ditinjau (bukan oversell)
        $second = $this->orders->createOnlineOrder($this->onlineData(['customer_phone' => '0813-0000-3333']));
        $second->update(['expires_at' => now()->subMinute()]);
        $this->orders->expireOverdue();
        $this->stock->deductForSale($this->online->id, [['product_id' => $this->coffee->id, 'quantity' => 8]], 'HABIS-1'); // sisa 8 -> 0
        $review = app(PaymentService::class)->confirmManual($second->fresh(), null, User::factory()->create());
        $this->assertSame(Order::NEEDS_REVIEW, $review->status);
        $this->assertSame(0, $this->onHand($this->coffee, $this->online));
    }

    // ------------------------------------------------------------------ Retur

    public function test_return_restocks_and_marks_order_refunded(): void
    {
        $this->openShift();
        $order = $this->posSale(['items' => [['product_id' => $this->coffee->id, 'quantity' => 2]], 'payments' => [['method' => 'CASH', 'amount' => 100000]]]);
        $item = $order->items->first();
        $manager = $this->cashier; // butuh shift terbuka untuk refund tunai

        $return = app(ReturnService::class)->create($order, [$item->id => 1], 'Kemasan rusak', 'cash', true, $manager);
        $this->assertSame('50000.00', $return->refund_amount);
        $this->assertSame(9, $this->onHand($this->coffee, $this->store)); // 10 - 2 + 1
        $this->assertSame('partially_refunded', $order->fresh()->payment_status);

        try { // tidak boleh meretur lebih dari yang dibeli
            app(ReturnService::class)->create($order->fresh(), [$item->id => 2], 'lagi', 'cash', true, $manager);
            $this->fail('Seharusnya ditolak');
        } catch (SalesException $e) {
            $this->assertStringContainsString('melebihi', $e->getMessage());
        }

        app(ReturnService::class)->create($order->fresh(), [$item->id => 1], 'sisa', 'cash', true, $manager);
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    // ------------------------------------------------------------------ Shift

    public function test_shift_close_computes_expected_cash_and_difference(): void
    {
        $this->openShift(); // kas awal 100.000
        $this->posSale(); // tunai 120.000 (uang diterima 150.000, kembali 30.000)

        $shift = app(ShiftService::class)->current($this->cashier);
        $closed = app(ShiftService::class)->close($shift, 215000, null, $this->cashier);

        $this->assertSame('220000.00', $closed->expected_cash); // 100.000 + 120.000
        $this->assertSame('-5000.00', $closed->difference);
    }

    // ------------------------------------------------------------------ Midtrans

    public function test_midtrans_webhook_rejects_bad_signature_and_confirms_valid_payment(): void
    {
        config(['cemwp.payments.midtrans.server_key' => 'SB-test-key']);
        PaymentMethod::where('code', 'MIDTRANS')->update(['is_active' => true]);
        Http::fake(['*/snap/v1/transactions' => Http::response(['token' => 'tok123', 'redirect_url' => 'https://pay.example/tok123'], 201)]);

        $order = app(CheckoutService::class)->placeOnlineOrder($this->onlineData(['payment_method' => 'MIDTRANS']), null);
        $payment = $order->payments->first();
        $this->assertSame('https://pay.example/tok123', $payment->redirect_url);

        $payload = ['order_id' => $order->number, 'status_code' => '200', 'gross_amount' => number_format((float) $order->grand_total, 2, '.', ''), 'transaction_status' => 'settlement'];

        $this->postJson('/api/webhooks/midtrans', $payload + ['signature_key' => 'palsu'])->assertForbidden();
        $this->assertSame(Order::AWAITING_PAYMENT, $order->fresh()->status);

        $sig = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'SB-test-key');
        $this->postJson('/api/webhooks/midtrans', $payload + ['signature_key' => $sig])->assertOk();
        $this->assertSame(Order::PROCESSING, $order->fresh()->status);
        $this->assertSame(Payment::PAID, $payment->fresh()->status);

        // notifikasi ganda tidak memproses ulang
        $this->postJson('/api/webhooks/midtrans', $payload + ['signature_key' => $sig])->assertOk();
        $this->assertSame(8, $this->onHand($this->coffee, $this->online));
    }

    // ------------------------------------------------------------------ HTTP

    public function test_public_catalog_and_shop_are_open_but_orders_api_requires_a_customer_token(): void
    {
        $this->getJson('/api/v1/catalog/products')->assertOk()->assertJsonStructure(['data', 'next_cursor', 'server_time']);
        $this->get('/shop')->assertOk();
        $this->postJson('/api/v1/orders', [])->assertUnauthorized();

        $staff = User::factory()->create();
        $token = $staff->createToken('x', ['catalog:read'])->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/orders', [])->assertUnauthorized(); // token staf tidak boleh membuat pesanan pelanggan
    }

    public function test_cashier_role_cannot_see_back_office_orders_or_reports(): void
    {
        $this->actingAs($this->cashier)->get(route('sales.orders.index'))->assertForbidden();
        $this->actingAs($this->cashier)->get(route('sales.reports.index'))->assertForbidden();
        $this->actingAs($this->cashier)->get(route('sales.shifts.index'))->assertOk();
    }
}
