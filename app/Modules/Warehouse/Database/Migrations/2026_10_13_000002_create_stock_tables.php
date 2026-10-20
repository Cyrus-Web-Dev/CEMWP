<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Saldo stok per produk per lokasi. Sumber kebenaran ANGKA = ledger stock_movements;
        // tabel ini adalah saldo berjalan yang dikunci (SELECT ... FOR UPDATE) saat stok berubah.
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->integer('qty_on_hand')->default(0);
            $table->integer('qty_reserved')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
            $table->index('warehouse_id');
        });
        // Pagar terakhir di level database: stok TIDAK BISA negatif / reservasi TIDAK BISA melebihi stok,
        // walau ada bug di kode aplikasi.
        DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_on_hand_nonneg CHECK (qty_on_hand >= 0)');
        DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_reserved_nonneg CHECK (qty_reserved >= 0)');
        DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_reserved_lte_on_hand CHECK (qty_reserved <= qty_on_hand)');

        // Buku besar stok: SETIAP perubahan stok = satu baris, tidak pernah diubah/dihapus (dijaga trigger di bawah).
        // Catatan: user_id sengaja TANPA foreign key, karena FK "on delete set null" akan meng-UPDATE baris ledger.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);                      // receipt|sale|sale_return|transfer_out|transfer_in|adjustment|opname
            $table->integer('quantity');                     // bertanda: + masuk, - keluar
            $table->integer('qty_before');
            $table->integer('qty_after');
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->string('reason', 40)->nullable();        // untuk adjustment: rusak, hilang, kedaluwarsa, ...
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 80)->nullable(); // nomor dokumen yg terbaca manusia (PO-..., ORD-...)
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'warehouse_id', 'id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('type');
            $table->index('created_at');
        });
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_qty_nonzero CHECK (quantity <> 0)');
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_balance_consistent CHECK (qty_after = qty_before + quantity)');
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION cemwp_block_ledger_mutation() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'stock_movements bersifat append-only: baris tidak boleh diubah atau dihapus (koreksi dengan membuat mutasi baru).';
END;
$$ LANGUAGE plpgsql
SQL);
        DB::statement('CREATE TRIGGER stock_movements_append_only BEFORE UPDATE OR DELETE ON stock_movements FOR EACH ROW EXECUTE FUNCTION cemwp_block_ledger_mutation()');

        // Reservasi stok: stok "dicadangkan" untuk checkout e-commerce yang belum dibayar.
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('reference', 80);                 // mis. "order:ORD-202610-0001" atau "cart:abc123"
            $table->unsignedInteger('quantity');
            $table->string('status', 20)->default('active'); // active | committed | released | expired
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['reference', 'product_id', 'warehouse_id']);
            $table->index(['status', 'expires_at']);
        });
        DB::statement('ALTER TABLE stock_reservations ADD CONSTRAINT stock_reservations_qty_positive CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
        DB::statement('DROP TRIGGER IF EXISTS stock_movements_append_only ON stock_movements');
        Schema::dropIfExists('stock_movements');
        DB::statement('DROP FUNCTION IF EXISTS cemwp_block_ledger_mutation()');
        Schema::dropIfExists('inventories');
    }
};
