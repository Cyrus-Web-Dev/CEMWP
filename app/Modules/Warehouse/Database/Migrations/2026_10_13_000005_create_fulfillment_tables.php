<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tugas gudang untuk menyiapkan & mengirim pesanan (dipicu modul Sales lewat FulfillmentGateway).
        Schema::create('fulfillments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('source_type', 30)->default('manual');    // order | manual
            $table->unsignedBigInteger('source_id')->nullable();      // id pesanan di modul Sales
            $table->string('source_number', 60)->nullable();          // nomor pesanan yang terbaca manusia
            $table->string('channel', 20)->default('ecommerce');      // ecommerce | pos | manual
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index(); // pending|picking|packed|shipped|delivered|cancelled
            $table->string('priority', 10)->default('normal');         // normal | urgent
            $table->string('recipient_name', 150)->nullable();
            $table->string('recipient_phone', 30)->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('courier', 40)->nullable();
            $table->string('tracking_number', 80)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('picked_at')->nullable();
            $table->timestamp('packed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            // Satu pesanan = satu tugas gudang (mencegah ganda bila Sales mengirim ulang).
            $table->unique(['source_type', 'source_id']);
            $table->index('created_at');
        });

        Schema::create('fulfillment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfillment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('sku', 40);
            $table->string('product_name', 200);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('qty_picked')->default(0);
            $table->timestamps();

            $table->unique(['fulfillment_id', 'product_id']);
        });
        DB::statement('ALTER TABLE fulfillment_items ADD CONSTRAINT fulfillment_items_qty_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE fulfillment_items ADD CONSTRAINT fulfillment_items_picked_lte_qty CHECK (qty_picked <= quantity)');
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_items');
        Schema::dropIfExists('fulfillments');
    }
};
