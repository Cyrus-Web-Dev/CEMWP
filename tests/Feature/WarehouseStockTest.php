<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Warehouse\Exceptions\InsufficientStockException;
use App\Modules\Warehouse\Exceptions\StockException;
use App\Modules\Warehouse\Models\Inventory;
use App\Modules\Warehouse\Models\Product;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Models\StockReservation;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Warehouse\Services\FulfillmentService;
use App\Modules\Warehouse\Services\StockService;
use App\Modules\Warehouse\Services\TransferService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menguji aturan keselamatan stok (lihat docblock StockService).
 * Jalankan: php artisan test   (butuh database PostgreSQL kosong "cemwp_test")
 */
class WarehouseStockTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;
    private Warehouse $main;
    private Warehouse $store;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stock = app(StockService::class);
        $this->main = Warehouse::create(['code' => 'GD-1', 'name' => 'Gudang 1', 'type' => 'warehouse']);
        $this->store = Warehouse::create(['code' => 'TK-1', 'name' => 'Toko 1', 'type' => 'store']);
        $this->product = Product::create(['sku' => 'P-1', 'name' => 'Kopi Tes', 'cost_price' => 10, 'selling_price' => 20, 'min_stock' => 0]);
    }

    private function onHand(?Warehouse $wh = null): int
    {
        return (int) Inventory::where('product_id', $this->product->id)->where('warehouse_id', ($wh ?? $this->main)->id)->value('qty_on_hand');
    }

    private function reserved(): int
    {
        return (int) Inventory::where('product_id', $this->product->id)->where('warehouse_id', $this->main->id)->value('qty_reserved');
    }

    private function items(int $qty): array
    {
        return [['product_id' => $this->product->id, 'quantity' => $qty]];
    }

    public function test_receive_adds_stock_writes_ledger_and_averages_cost(): void
    {
        $this->stock->receive($this->product->id, $this->main->id, 10, 10.0);
        $this->stock->receive($this->product->id, $this->main->id, 10, 20.0);

        $this->assertSame(20, $this->onHand());
        $this->assertSame(2, StockMovement::count());
        $this->assertSame('15.00', $this->product->fresh()->cost_price); // (10*10 + 10*20) / 20
    }

    public function test_deduct_beyond_stock_is_rejected_and_changes_nothing(): void
    {
        $this->stock->receive($this->product->id, $this->main->id, 2, 10.0);

        try {
            $this->stock->deductForSale($this->main->id, $this->items(3), 'POS-1');
            $this->fail('Seharusnya melempar InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertSame(3, $e->requested);
            $this->assertSame(2, $e->available);
        }

        $this->assertSame(2, $this->onHand());
        $this->assertSame(1, StockMovement::count()); // hanya penerimaan awal
    }

    public function test_same_idempotency_key_deducts_only_once(): void
    {
        $this->stock->receive($this->product->id, $this->main->id, 10, 10.0);

        $this->stock->deductForSale($this->main->id, $this->items(3), 'POS-1', 'order-77');
        $this->stock->deductForSale($this->main->id, $this->items(3), 'POS-1', 'order-77'); // retry jaringan

        $this->assertSame(7, $this->onHand());
    }

    public function test_reservation_blocks_other_buyers_then_commit_becomes_sale(): void
    {
        $this->stock->receive($this->product->id, $this->main->id, 5, 10.0);

        $this->stock->reserve('order:A', $this->main->id, $this->items(4));
        $this->assertSame(4, $this->reserved());
        $this->assertSame(1, $this->stock->availability($this->product->id, $this->main->id));

        // Pembeli lain tidak bisa memesan 2 (hanya 1 tersedia)
        try {
            $this->stock->reserve('order:B', $this->main->id, $this->items(2));
            $this->fail('Seharusnya melempar InsufficientStockException');
        } catch (InsufficientStockException $e) {
            $this->assertSame(1, $e->available);
        }

        $moved = $this->stock->commitReservation('order:A', 'ORD-A');
        $this->assertCount(1, $moved);
        $this->assertSame(1, $this->onHand());
        $this->assertSame(0, $this->reserved());
    }

    public function test_release_frees_reserved_stock_and_commit_after_expiry_returns_empty(): void
    {
        $this->stock->receive($this->product->id, $this->main->id, 5, 10.0);
        $this->stock->reserve('order:A', $this->main->id, $this->items(3), now()->subMinute());

        $this->assertSame(1, $this->stock->expireReservations());
        $this->assertSame(0, $this->reserved());
        $this->assertSame(StockReservation::EXPIRED, StockReservation::first()->status);

        // Pembayaran datang terlambat: tidak ada reservasi aktif -> kosong, stok tidak berubah
        $this->assertCount(0, $this->stock->commitReservation('order:A'));
        $this->assertSame(5, $this->onHand());
    }

    public function test_cannot_deduct_stock_that_is_reserved_by_someone_else(): void
    {
        $this->stock->receive($this->product->id, $this->main->id, 5, 10.0);
        $this->stock->reserve('order:A', $this->main->id, $this->items(4));

        $this->expectException(InsufficientStockException::class);
        $this->stock->deductForSale($this->main->id, $this->items(2), 'POS-9'); // hanya 1 yang bebas
    }

    public function test_ledger_rows_cannot_be_updated(): void
    {
        $this->stock->receive($this->product->id, $this->main->id, 5, 10.0);

        $this->expectException(QueryException::class); // trigger append-only di PostgreSQL
        StockMovement::first()->update(['note' => 'diubah diam-diam']);
    }

    public function test_transfer_moves_stock_between_locations(): void
    {
        $this->stock->receive($this->product->id, $this->main->id, 10, 10.0);
        $transfers = app(TransferService::class);

        $t = $transfers->create(['from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $this->store->id, 'items' => $this->items(4)], null);
        $transfers->ship($t, null);
        $this->assertSame(6, $this->onHand($this->main));
        $this->assertSame(0, $this->onHand($this->store)); // masih di perjalanan

        $transfers->receive($t->fresh(), null);
        $this->assertSame(4, $this->onHand($this->store));
    }

    public function test_transfer_with_insufficient_stock_fails_whole(): void
    {
        $this->stock->receive($this->product->id, $this->main->id, 2, 10.0);
        $transfers = app(TransferService::class);
        $t = $transfers->create(['from_warehouse_id' => $this->main->id, 'to_warehouse_id' => $this->store->id, 'items' => $this->items(5)], null);

        try {
            $transfers->ship($t, null);
            $this->fail('Seharusnya gagal');
        } catch (StockException $e) {
            $this->assertSame(2, $this->onHand($this->main));
            $this->assertSame('draft', $t->fresh()->status);
        }
    }

    public function test_fulfillment_flow_and_idempotent_creation(): void
    {
        $svc = app(FulfillmentService::class);
        $data = [
            'source_type' => 'order', 'source_id' => 501, 'source_number' => 'ORD-501', 'channel' => 'ecommerce',
            'warehouse_id' => $this->main->id, 'recipient_name' => 'Rina', 'items' => $this->items(2),
        ];

        $f = $svc->createFromOrder($data);
        $this->assertSame($f->id, $svc->createFromOrder($data)->id); // pesanan sama tidak membuat tugas kedua

        try {
            $svc->pack($f); // belum mulai ambil
            $this->fail('Seharusnya menolak lompat status');
        } catch (StockException $e) {
            $this->assertStringContainsString('tidak bisa berubah', $e->getMessage());
        }

        $svc->start($f, null);
        $svc->savePicked($f, [$f->items()->first()->id => 2]);
        $svc->pack($f);
        $svc->ship($f, 'JNE', 'RESI123');
        $done = $svc->deliver($f);

        $this->assertSame('delivered', $done->status->value);
        $this->assertSame('RESI123', $done->tracking_number);
    }

    public function test_warehouse_staff_cannot_adjust_stock_but_manager_can(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->stock->receive($this->product->id, $this->main->id, 5, 10.0);

        $payload = ['product_id' => $this->product->id, 'warehouse_id' => $this->main->id, 'direction' => 'out', 'quantity' => 1, 'reason' => 'damaged'];

        $staff = User::factory()->create();
        $staff->assignRole('warehouse-staff');
        $this->actingAs($staff)->post(route('warehouse.inventory.adjust'), $payload)->assertForbidden();
        $this->assertSame(5, $this->onHand());

        $manager = User::factory()->create();
        $manager->assignRole('warehouse-manager');
        $this->actingAs($manager)->post(route('warehouse.inventory.adjust'), $payload)->assertRedirect();
        $this->assertSame(4, $this->onHand());
    }
}
