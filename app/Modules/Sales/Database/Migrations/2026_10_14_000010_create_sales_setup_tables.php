<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            // cash = tunai di kasir; manual = dikonfirmasi manusia (QRIS statis, kartu EDC, transfer bank);
            // gateway = otomatis lewat webhook (Midtrans); cod = bayar di tempat saat barang tiba.
            $table->string('kind', 20);
            $table->boolean('for_pos')->default(false);
            $table->boolean('for_online')->default(false);
            $table->boolean('requires_reference')->default(false); // kasir wajib mengisi no. approval/ref
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('instructions')->nullable();               // mis. nomor rekening untuk transfer bank
            $table->timestamps();
        });

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('courier', 40);
            $table->string('service', 40)->default('Reguler');
            $table->boolean('is_pickup')->default(false);          // ambil di toko: tanpa alamat & ongkir
            $table->decimal('base_cost', 12, 2)->default(0);
            $table->decimal('cost_per_kg', 12, 2)->default(0);     // tarif per kg (dibulatkan ke atas)
            $table->decimal('free_over', 14, 2)->nullable();       // gratis ongkir bila subtotal >= nilai ini
            $table->unsignedSmallInteger('min_days')->nullable();
            $table->unsignedSmallInteger('max_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->string('type', 10);                            // percent | fixed
            $table->decimal('value', 14, 2);                       // percent: 0-100; fixed: rupiah
            $table->decimal('min_subtotal', 14, 2)->default(0);
            $table->decimal('max_discount', 14, 2)->nullable();    // batas potongan untuk tipe percent
            $table->string('channel', 10)->default('all');         // all | pos | online
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();    // total pemakaian maksimal
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            // Kupon personal (dibuat Marketing untuk satu pelanggan) & atribusi kampanye. Tanpa FK ke modul lain.
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        DB::statement("ALTER TABLE coupons ADD CONSTRAINT coupons_type_valid CHECK (type IN ('percent','fixed'))");
        DB::statement('ALTER TABLE coupons ADD CONSTRAINT coupons_value_positive CHECK (value > 0)');
        DB::statement('ALTER TABLE coupons ADD CONSTRAINT coupons_percent_max_100 CHECK (type <> \'percent\' OR value <= 100)');

        // Shift kasir: kas awal, rekap, selisih kas saat tutup.
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();   // outlet (lokasi stok bertipe toko)
            $table->string('status', 10)->default('open');                           // open | closed
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_cash', 14, 2)->default(0);
            $table->decimal('expected_cash', 14, 2)->nullable();
            $table->decimal('counted_cash', 14, 2)->nullable();
            $table->decimal('difference', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
        });
        // Satu kasir hanya boleh punya SATU shift terbuka.
        DB::statement("CREATE UNIQUE INDEX shifts_one_open_per_user ON shifts (user_id) WHERE status = 'open'");
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('payment_methods');
    }
};
