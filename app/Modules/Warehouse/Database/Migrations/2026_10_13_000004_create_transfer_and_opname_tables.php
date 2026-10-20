<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('status', 20)->default('draft')->index(); // draft|in_transit|received|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE stock_transfers ADD CONSTRAINT stock_transfers_distinct_locations CHECK (from_warehouse_id <> to_warehouse_id)');

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('qty_received')->default(0);
            $table->timestamps();

            $table->unique(['stock_transfer_id', 'product_id']);
        });
        DB::statement('ALTER TABLE stock_transfer_items ADD CONSTRAINT transfer_items_qty_positive CHECK (quantity > 0)');

        // Stok opname (hitung fisik).
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft')->index(); // draft|finalized|cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('system_qty')->default(0);
            $table->integer('counted_qty')->nullable();
            $table->integer('difference')->nullable();       // counted - system (diisi saat finalisasi)
            $table->timestamps();

            $table->unique(['stock_opname_id', 'product_id']);
        });
        DB::statement('ALTER TABLE stock_opname_items ADD CONSTRAINT opname_items_counted_nonneg CHECK (counted_qty IS NULL OR counted_qty >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
