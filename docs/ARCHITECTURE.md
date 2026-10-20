# Arsitektur CEMWP — modular monolith

Satu aplikasi Laravel, satu database PostgreSQL, modul-modul bisnis terpisah rapi di `app/Modules/*`.

```
app/
  Http/ Models/ Services/      <- INTI: login (OTP Gmail), pengguna & role, log aktivitas
  Modules/
    Warehouse/                 <- SELESAI (modul ini)
    Sales/                     <- SELESAI: POS, etalase online, pesanan, pembayaran, kupon, retur, laporan, API mobile
    Crm/                       <- AWAL: identitas pelanggan + profil perilaku belanja (Project 3 melanjutkan)
    Marketing/                 <- Project 4 (belum)
```

Tiap modul punya `ServiceProvider` sendiri (rute, migrasi, kontrak) yang didaftarkan di `bootstrap/providers.php`.

## Mengapa A (monolith modular), bukan 4 aplikasi

Alur inti ("potong stok + catat order + perbarui pelanggan") harus atomik. Dalam satu database kita dapat memakai
satu transaksi PostgreSQL dan `SELECT ... FOR UPDATE`; pada empat aplikasi terpisah itu menjadi saga yang rumit.
Modul tetap bisa dipisah menjadi layanan sendiri kelak **selama aturan batas di bawah dipatuhi**.

### Aturan batas modul (jaga disiplin ini)

1. Modul lain **tidak** menulis ke tabel milik modul lain. Ubah stok hanya lewat `StockGateway`; minta tugas gudang hanya
   lewat `FulfillmentGateway`.
2. Modul lain **tidak** `use` model modul lain untuk menulis. Membaca lewat kontrak atau event.
3. Komunikasi lintas modul yang tidak perlu atomik = **event Laravel** (dikirim setelah commit).
4. Tabel inti `users`/`roles` boleh direferensikan semua modul.

## Alur "The Connected Loop" dan siapa pemiliknya

| # | Langkah | Modul | Mekanisme |
|---|---------|-------|-----------|
| 1 | Transaksi POS / E-commerce | Sales | `OrderService` (transaksi DB) |
| 2 | Potong stok real-time + siapkan barang | **Warehouse** | `StockGateway` (sinkron, atomik) + `FulfillmentGateway` |
| 3 | Profil pelanggan & status kirim | Crm | mendengarkan event `OrderPlaced`, `FulfillmentStatusChanged` (asinkron) |
| 4 | Kupon WhatsApp berdasar pola beli | Marketing | membaca data CRM, membuat kupon, kirim via WhatsApp provider |
| 5 | Kupon dipakai belanja lagi | Sales | validasi kupon di server saat checkout |

**Atomik:** langkah 1 + 2 (order dan stok dalam satu transaksi). **Eventual (boleh terlambat):** langkah 3–4.
Karena itu CRM **tidak** diperbarui di dalam transaksi order: bug di CRM tidak boleh menggagalkan penjualan.

## Modul Sales (Project 1) — ringkasan alur

* **POS:** buka shift → pilih produk / scan barcode → bayar (tunai, QRIS, kartu, bisa dipecah) → struk. Order + pembayaran +
  pemotongan stok dalam **satu transaksi** (`OrderService::createPosSale`); stok kurang = tidak ada yang tersimpan.
* **Online:** keranjang (sesi / MMKV) → checkout → `CheckoutService` → stok **dicadangkan** (`reserve`) sampai bayar;
  dana masuk (webhook Midtrans / konfirmasi petugas) → `commitReservation` → tugas gudang dibuat (`FulfillmentGateway`).
  COD memotong stok langsung. Pesanan tak dibayar → kedaluwarsa otomatis (stok & kupon dilepas).
* **Gudang → Sales:** event `FulfillmentStatusChanged` (shipped / delivered / cancelled) → status pesanan diperbarui
  (`SyncOrderFromFulfillment`); COD otomatis lunas saat barang diterima.
* **Sales → CRM:** event `OrderPaid` → `UpdateCustomerPurchaseStats` mengisi `customers` (jumlah beli per saluran, total belanja)
  dan `customer_product_stats` (produk yang dibeli). Marketing kelak membaca tabel ini. Kegagalan CRM **tidak** membatalkan penjualan.
* **Harga hanya dihitung di server** (`PricingService`); keranjang klien hanya membawa `product_id` + `quantity`.
* **Idempotensi:** `pg_advisory_xact_lock` per kunci + `idempotency_key` unik → request kembar (retry jaringan) tidak membuat order ganda.
* **Pembayaran terlambat** (dana masuk setelah pesanan kedaluwarsa): stok dialokasikan ulang bila masih ada; bila tidak,
  status `needs_review` (tidak pernah oversell).
* **Retur:** per barang, opsi kembalikan ke stok atau tidak; refund tunai mengurangi kas shift.

Status pesanan: `awaiting_payment → processing → shipped → completed`; cabang `cancelled`, `expired`, `needs_review`.
Status bayar: `unpaid | paid | refund_pending | refunded | partially_refunded`.

Peran pelanggan vs staf: staf memakai guard `web` (+ 2FA OTP), pelanggan etalase memakai guard `customer`, pelanggan mobile memakai
token Sanctum milik `Customer`. Token staf **tidak** bisa dipakai di endpoint pesanan pelanggan (`customer.token`).

## Kontrak Warehouse untuk modul Sales

```php
use App\Modules\Warehouse\Contracts\StockGateway;
use App\Modules\Warehouse\Contracts\FulfillmentGateway;

// POS (bayar langsung di kasir): satu transaksi, idempoten
DB::transaction(function () use ($stock, $order) {
    $order->save();
    $stock->deductForSale($outletId, $items, $order->number, idempotencyKey: $order->uuid, userId: $cashierId);
});

// E-commerce: checkout -> cadangkan (kedaluwarsa otomatis) -> bayar -> potong -> minta gudang menyiapkan
$stock->reserve("order:{$order->number}", $onlineWarehouseId, $items);        // saat checkout
$moved = $stock->commitReservation("order:{$order->number}", $order->number); // saat pembayaran masuk (webhook)
if ($moved->isEmpty()) { /* reservasi sudah kedaluwarsa: reserve ulang atau refund */ }
$fulfillment->createFromOrder([... 'source_type' => 'order', 'source_id' => $order->id ...]);
```

`StockException` (dan `InsufficientStockException` dengan `requested`/`available`) membawa pesan siap tampil ke pengguna.

## Event yang dikirim Warehouse (semua SETELAH commit)

| Event | Pendengar yang direncanakan |
|-------|-----------------------------|
| `StockChanged` | Sales: segarkan ketersediaan katalog |
| `LowStockReached` | notifikasi tim gudang / pembelian |
| `FulfillmentCreated` | notifikasi tim gudang |
| `FulfillmentStatusChanged` | **CRM**: catat status pengiriman ke profil pelanggan |

## Keputusan desain stok (dan alasannya)

* **Ledger + saldo.** `stock_movements` (ledger, append-only dijaga trigger PostgreSQL) adalah sumber kebenaran;
  `inventories` adalah saldo berjalan yang dikunci per baris. `php artisan cemwp:stock-verify` mencocokkan keduanya
  (dijadwalkan harian; juga mencocokkan reservasi aktif dengan `qty_reserved`).
* **Pagar di database.** `CHECK (qty_on_hand >= 0)` dan `CHECK (qty_reserved <= qty_on_hand)`: stok tidak bisa
  negatif walau ada bug aplikasi.
* **Anti deadlock.** Operasi multi-item mengunci baris berurutan menurut `product_id`.
* **Idempotency.** `idempotency_key` unik pada ledger; pengecekan dilakukan SETELAH baris terkunci, sehingga request
  kembar yang bersamaan tidak melanggar unique index dan tidak menggandakan stok.
* **Harga pokok.** Rata-rata bergerak (moving average), diperbarui saat penerimaan barang.
* **Reservasi** hanya untuk e-commerce (checkout belum dibayar). POS memotong langsung.
* **Satuan** bilangan bulat. Produk curah (gram/liter) belum didukung (perlu kolom desimal).
* **Varian** (mis. "Kopi varian A/B") untuk sekarang = SKU terpisah. Tabel varian bisa ditambah tanpa mengubah kontrak stok
  karena stok selalu per `product_id`.

## Pemetaan ke Project 5 (React Native)

RN hanya bicara dengan **satu API** `/api/v1/*` pada aplikasi ini.

| Kebutuhan RN | Endpoint | Status |
|--------------|----------|--------|
| Cache katalog SQLite (delta sync) | `GET /api/v1/catalog/products?updated_since=...&cursor=...` | ada |
| Ketersediaan stok real-time | `GET /api/v1/catalog/availability` | ada |
| Login pelanggan (token Sanctum → MMKV) | `POST /api/v1/auth/*` | ada |
| Checkout (`POST /api/v1/orders`, wajib `Idempotency-Key`) | | ada |
| Hitung total + validasi kupon | `POST /api/v1/quote` | ada |
| Riwayat & detail pesanan | `GET /api/v1/orders[/{number}]` | ada |

Panduan lengkap dengan contoh: [docs/API.md](API.md).

Catatan delta sync: respons menyertakan produk yang dihapus (`deleted_at` terisi) supaya klien menghapusnya dari SQLite.
Simpan `server_time` dari respons sebagai `updated_since` berikutnya (jangan pakai jam HP).

Keamanan mobile: token disimpan di MMKV **terenkripsi** (`encryptionKey` dari Keychain/Keystore); harga, total, dan
diskon selalu dihitung ulang di server (jangan percaya angka dari keranjang).

## Roadmap yang disarankan

1. ✅ Fondasi + **Warehouse**
2. ✅ **Sales** — POS, etalase web, Midtrans, kupon, retur, laporan, API mobile
3. **Project 5** — aplikasi React Native memakai `docs/API.md`
4. **CRM** (lanjutan) — halaman admin pelanggan, segmentasi, riwayat interaksi
5. **Marketing** — kampanye, kupon personal otomatis (`coupons.customer_id` + `campaign_id` sudah disiapkan),
   WhatsApp Business API (template disetujui, hanya pelanggan dengan `marketing_consent`)

Belum ada (sengaja ditunda): tarif ongkir real-time (RajaOngkir/Biteship; sekarang tarif tabel sederhana), QRIS dinamis di kasir,
upload bukti transfer, notifikasi email/WhatsApp ke pembeli, varian produk, satuan desimal.
