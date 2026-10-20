<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 40)->unique();
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('name', 200)->index();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('unit', 20)->default('pcs');
            $table->decimal('cost_price', 14, 2)->default(0);
            $table->decimal('selling_price', 14, 2)->default(0);
            $table->unsignedInteger('min_stock')->default(0);       // titik pesan ulang (reorder point)
            $table->unsignedInteger('weight_grams')->default(0);    // dipakai ongkir e-commerce
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_sellable_online')->default(true);
            $table->boolean('track_stock')->default(true);          // false = jasa/barang tanpa stok
            $table->string('image_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('updated_at');                            // sinkronisasi delta (?updated_since=) untuk app mobile
        });

        // Lokasi stok: gudang, toko/outlet (stok yang dijual POS), atau stok khusus online.
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('type', 20)->default('warehouse');       // warehouse | store | online
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
        // Hanya boleh ada SATU gudang bawaan.
        DB::statement('CREATE UNIQUE INDEX warehouses_single_default ON warehouses (is_default) WHERE is_default');

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('contact_name', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Penomoran dokumen berurutan per bulan (PO-202610-0001), aman dari nomor ganda (dikunci per baris).
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->string('seq_key', 60)->primary();
            $table->unsignedBigInteger('last_value')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
