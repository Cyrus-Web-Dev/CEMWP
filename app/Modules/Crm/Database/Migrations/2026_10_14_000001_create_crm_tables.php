<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Identitas + profil perilaku belanja pelanggan. Dimiliki modul CRM; modul lain hanya lewat CustomerResolver / event.
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 190)->nullable();
            $table->string('phone', 20)->nullable();          // format normal: 62812xxxxxxx (tanpa + / spasi)
            $table->string('password')->nullable();           // null = tamu (belanja tanpa akun / dari kasir)
            $table->timestamp('email_verified_at')->nullable();
            $table->string('status', 20)->default('active');  // active | blocked
            $table->string('source', 20)->default('web');     // pos | web | app
            $table->boolean('marketing_consent')->default(false); // opt-in WhatsApp/email (UU PDP)
            $table->timestamp('marketing_consent_at')->nullable();

            // Profil perilaku (diisi listener OrderPaid)
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('pos_orders_count')->default(0);
            $table->unsignedInteger('online_orders_count')->default(0);
            $table->decimal('total_spent', 16, 2)->default(0);
            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->string('last_channel', 20)->nullable();

            $table->rememberToken();
            $table->timestamps();
        });
        // Email & HP unik (email tidak peka huruf besar-kecil); NULL boleh banyak.
        DB::statement('CREATE UNIQUE INDEX customers_email_unique_lower ON customers (lower(email)) WHERE email IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX customers_phone_unique ON customers (phone) WHERE phone IS NOT NULL');

        // Apa yang dibeli tiap pelanggan (dasar rekomendasi / kupon personal: "sering beli kopi varian A").
        // product_id sengaja tanpa foreign key: CRM tidak terikat tabel modul lain.
        Schema::create('customer_product_stats', function (Blueprint $table) {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('quantity_total')->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('pos_orders_count')->default(0);
            $table->unsignedInteger('online_orders_count')->default(0);
            $table->timestamp('last_purchased_at')->nullable();
            $table->primary(['customer_id', 'product_id']);
            $table->index('product_id');
        });

        // Penanda idempoten: satu pesanan hanya dihitung sekali ke profil pelanggan.
        Schema::create('crm_processed_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->primary();
            $table->timestamp('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_processed_orders');
        Schema::dropIfExists('customer_product_stats');
        Schema::dropIfExists('customers');
    }
};
