<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('channel', 12);                                 // pos | ecommerce
            // awaiting_payment | processing | shipped | completed | cancelled | expired | needs_review
            $table->string('status', 20)->index();
            // unpaid | paid | refund_pending | refunded | partially_refunded
            $table->string('payment_status', 20)->default('unpaid');
            // true = stok fisik sudah terpotong untuk pesanan ini (harus dikembalikan bila dibatalkan/diretur)
            $table->boolean('stock_committed')->default(false);

            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();      // kasir (POS)
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();                 // lokasi stok yang dipotong
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 40)->nullable();

            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('shipping_cost', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->decimal('change_due', 14, 2)->default(0);

            // Snapshot pembeli (tetap benar walau data pelanggan berubah)
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->string('customer_email', 190)->nullable();

            // Pengiriman (e-commerce)
            $table->foreignId('shipping_method_id')->nullable()->constrained('shipping_methods')->nullOnDelete();
            $table->string('shipping_label', 100)->nullable();
            $table->string('recipient_name', 150)->nullable();
            $table->string('recipient_phone', 30)->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_postal_code', 10)->nullable();
            $table->unsignedInteger('total_weight_grams')->default(0);
            $table->string('courier', 40)->nullable();
            $table->string('tracking_number', 80)->nullable();

            $table->text('notes')->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('placed_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['channel', 'placed_at']);
            $table->index('customer_id');
            $table->index(['status', 'expires_at']);
        });
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_totals_nonneg CHECK (subtotal >= 0 AND discount_total >= 0 AND shipping_cost >= 0 AND tax_total >= 0 AND grand_total >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_discount_lte_subtotal CHECK (discount_total <= subtotal)');

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('sku', 40);
            $table->string('name', 200);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('cost_price', 14, 2)->default(0);       // snapshot harga pokok untuk laporan margin
            $table->unsignedInteger('quantity');
            $table->decimal('discount', 14, 2)->default(0);         // bagian potongan kupon untuk baris ini
            $table->decimal('line_total', 14, 2);
            $table->unsignedInteger('weight_grams')->default(0);
            $table->timestamps();

            $table->index('product_id');
        });
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_qty_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_line_nonneg CHECK (line_total >= 0 AND discount >= 0)');

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->string('method_code', 30);
            $table->string('method_name', 100);
            $table->string('kind', 20);
            $table->decimal('amount', 14, 2);                       // jumlah yang dialokasikan ke order
            $table->decimal('tendered', 14, 2)->nullable();         // uang diterima (tunai); selisih = kembalian
            // pending | paid | failed | expired | refund_pending | refunded
            $table->string('status', 20)->default('pending')->index();
            $table->string('reference', 100)->nullable();           // no. approval EDC / ref QRIS / ref transfer
            $table->string('gateway', 20)->nullable();
            $table->string('gateway_ref', 120)->nullable();
            $table->text('redirect_url')->nullable();
            $table->jsonb('gateway_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_ref']);
        });
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_nonneg CHECK (amount >= 0)');

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('order_id');
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['coupon_id', 'order_id']);
            $table->index(['coupon_id', 'customer_id']);
        });

        // Retur / pengembalian (kasir atau back-office). Barang boleh dikembalikan ke stok atau tidak (rusak).
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->string('reason', 255);
            $table->string('refund_method', 30);                    // cash | original | manual
            $table->decimal('refund_amount', 14, 2);
            $table->boolean('restocked')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('order_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('amount', 14, 2);
        });
        DB::statement('ALTER TABLE order_return_items ADD CONSTRAINT return_items_qty_positive CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_items');
        Schema::dropIfExists('order_returns');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
