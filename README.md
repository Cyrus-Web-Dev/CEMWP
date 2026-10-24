# CEMWP — Connected Commerce

Laravel 11 · Inertia · Vue 3 · Tailwind · PostgreSQL · Sanctum · Gmail API · Midtrans.

Satu aplikasi, satu database, modul bisnis di `app/Modules/*` (lihat [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)).

---

## Apa itu CEMWP?

**Connected Commerce OS** untuk ritel barang fisik: menyatukan **kasir (POS)**, **toko online**, **gudang/inventori**, dan **CRM** dalam satu sistem yang saling terhubung secara real-time.

Alur inti (*Connected Loop*) yang sudah berjalan:

> Transaksi POS / online → stok terpotong real-time → tugas gudang (pick → pack → ship) → status kirim kembali ke pesanan → profil pelanggan (CRM) terisi otomatis.

Marketing (kupon WhatsApp otomatis) adalah Project 4 (roadmap).

---

## Tujuan (Goals)

- **Digitalisasi operasional ritel** — mengganti kasir manual + catatan stok kertas menjadi ekosistem digital terintegrasi berbasis data real-time.
- **Efisiensi & transparansi stok dan keuangan** — menghilangkan selisih stok, penjualan tidak tercatat, dan kebocoran margin lewat ledger mutasi yang immutable serta kalkulasi otomatis (HPP moving average, margin, retur, kupon).
- **Optimalisasi inventori & penjualan** — produk tersedia di lokasi yang tepat (gudang / toko / online), fulfillment lebih cepat, dan frekuensi belanja pelanggan meningkat lewat profil perilaku (CRM).

---

## Manfaat (Benefits)

| Pihak | Manfaat |
|-------|---------|
| **Pemilik / Manajemen** | Visibilitas penuh atas omzet, margin, stok mati, performa kasir & channel; keputusan lebih cepat berbasis dashboard. |
| **Admin / Keuangan / Gudang** | Input produk, penerimaan, transfer, opname, pesanan online, HPP, dan laporan tanpa rekap manual. |
| **Kasir & Pelanggan** | POS cepat (scan, split payment, hold/resume, struk); pelanggan belanja online, lacak pesanan, checkout aman (Midtrans / transfer / COD). |

---

## Kelebihan (Strengths)

- **Modular monolith fokus** — satu DB, modul terpisah di `app/Modules/*`. Order + potong stok **atomik** dalam satu transaksi PostgreSQL (tanpa saga antar-service).
- **Connected Loop sudah jalan** — dari penjualan sampai profil CRM terisi otomatis.
- **Mobile-ready & API-first** — `/api/v1/*` siap untuk React Native (katalog delta-sync, availability, checkout + `Idempotency-Key`).
- **SPA interaktif tanpa beban API internal** — Inertia.js untuk dashboard admin.
- **Idempotensi & ledger konsisten** — mutasi stok lewat `StockService` + `FOR UPDATE` + `idempotency_key`; mencegah double-cut.

---

## Kekurangan & solusi

| Kekurangan | Solusi / mitigasi |
|------------|-------------------|
| Satuan masih bilangan bulat; varian = SKU terpisah | Roadmap: kolom desimal + tabel varian (stok tetap per `product_id`) |
| Bergantung Midtrans & Gmail API untuk fitur penuh | Mode lokal: OTP di log; bayar manual (transfer, COD, QRIS manual) tetap aktif |
| Adaptasi kasir/gudang yang terbiasa kertas | UI minimalis, alur POS linear, seeder demo untuk latihan |
| Ongkir masih tabel sederhana | Cukup untuk fase awal; endpoint siap diganti RajaOngkir/Biteship |

---

## Isi rilis ini

| Modul | Isi |
|-------|-----|
| **Inti** | Login staf 2FA (OTP Gmail), pengguna & role, log aktivitas |
| **Warehouse** (Project 2) | Produk, kategori, lokasi stok, stok + ledger mutasi, penyesuaian, supplier, PO & penerimaan, transfer, stok opname, pengiriman pesanan (pick → pack → ship), dashboard, API katalog |
| **Sales** (Project 1) | **Kasir/POS** (shift, scan barcode, pecah pembayaran, tahan/lanjutkan, struk), **toko online** (katalog, keranjang, checkout, akun, lacak pesanan), pesanan back-office, kupon, retur, laporan, pengaturan bayar/kirim, **Midtrans**, **API v1 untuk React Native** |
| **CRM** (awal) | Identitas pelanggan + profil perilaku belanja otomatis dari tiap pesanan lunas |
| **Marketing** | Project 4 (roadmap): kampanye, kupon personal, WhatsApp |
| **Laporan / Profit** | Agregasi omzet, HPP, diskon, retur, margin per produk/kategori/channel |

### Hubungan antar-modul (ringkas)

1. **Inti** — autentikasi, role, audit untuk semua modul.
2. **Warehouse** — `StockGateway` (potong stok atomik) + `FulfillmentGateway` (tugas kirim) dipakai Sales.
3. **Sales** — menerbitkan event order lunas → CRM; memanggil gateway Warehouse.
4. **CRM** — mengisi profil dari event Sales; menjadi sumber segmentasi Marketing.
5. **Marketing** (nanti) — kupon personal → divalidasi lagi di Sales saat checkout.
6. **Laporan** — muara data dari semua modul (setara “Cost per KM” di sistem logistik).

Disiplin arsitektur: modul lain **tidak** menulis langsung ke tabel modul lain; komunikasi non-atomik lewat Laravel Event setelah commit.

---

## Alur kerja di localhost (Connected Loop)

1. Siapkan master: gudang, kategori, produk, stok awal (penerimaan), metode bayar & kirim, kupon.
2. Kasir buka shift → scan → bayar → stok potong real-time → struk.
3. Atau: pelanggan di `/shop` → checkout → stok direservasi → bayar → order lunas → tugas gudang dibuat.
4. Gudang: pick → pack → ship → status kembali ke pesanan.
5. Event order/fulfillment → CRM memperbarui profil pelanggan.
6. Min stok & mutasi memicu peringatan stok rendah.
7. Owner buka dashboard: omzet, margin, top product, stok mati, POS vs online.

---

## Pasang (lokal)

Prasyarat: PHP 8.2+ (`pdo_pgsql`, `mbstring`, `intl`, `curl`), Composer, Node 18+, PostgreSQL 14+.

```bash
composer install
cp .env.example .env
php artisan key:generate

# buat database kosong:  CREATE DATABASE cemwp;   lalu isi DB_USERNAME / DB_PASSWORD di .env
# (opsional) super-admin awal:  CEMWP_ADMIN_EMAIL=kamu@email.com  CEMWP_ADMIN_PASSWORD=PasswordKuat123

php artisan migrate --seed
php artisan db:seed --class=DemoSeeder      # opsional: data contoh toko kopi + akun demo
npm install && npm run build                # atau: npm run dev
php artisan serve                           # http://127.0.0.1:8000
```

- Halaman depan `/` = **toko online** (`/shop`). Staf masuk di `/login`.
- Akun demo (hanya bila `APP_ENV=local` dan `DemoSeeder` dijalankan), password `Password123`:
  - `kasir@cemwp.test` (kasir)
  - `sales@cemwp.test` (manajer penjualan)
  - `gudang@cemwp.test` (staf gudang)
- Login staf memakai OTP email; bila Gmail belum siap, kode tercatat di `storage/logs/laravel.log` (mode lokal).
- Belum punya admin?  
  `php artisan cemwp:user kamu@email.com --name="Nama" --password="PasswordKuat123" --role=super-admin`

### Konfigurasi toko (.env penting)

```env
CEMWP_STORE_NAME="Nama Toko Anda"
CEMWP_STORE_ADDRESS="Alamat"
CEMWP_STORE_PHONE="08xxxxxxxxxx"
CEMWP_RECEIPT_FOOTER="Terima kasih sudah berbelanja!"
CEMWP_BANK_TRANSFER_INFO="Transfer ke BCA 1234567890 a.n. Nama Toko"
CEMWP_TAX_RATE=0
CEMWP_ORDER_TTL_MINUTES=60
CEMWP_RESERVATION_TTL_MINUTES=30
CEMWP_DEFAULT_WAREHOUSE=GD-UTAMA
```

**Email OTP (Gmail API):** salin `credentials.json` + `token.json` ke `storage/app/credentials/`.  
OTP dipakai login staf **dan** verifikasi email pelanggan. Cek: `php artisan cemwp:mail-test emailkamu@gmail.com`.

**Scheduler (wajib di production):**

```cron
* * * * * cd /path/ke/cemwp && php artisan schedule:run >> /dev/null 2>&1
```

Menjalankan: pelepasan reservasi stok, kedaluwarsa pesanan online yang tak dibayar, rekonsiliasi stok malam.

**Midtrans (opsional):** isi `MIDTRANS_SERVER_KEY` / `MIDTRANS_CLIENT_KEY`, aktifkan metode `MIDTRANS` di *Penjualan → Pengaturan penjualan*, set Payment Notification URL ke  
`https://domain-kamu/api/webhooks/midtrans`.  
Tanpa Midtrans, pembeli tetap bisa **transfer bank** (dikonfirmasi petugas) atau **COD**.

---

## Role bawaan

| Role | Untuk |
|------|-------|
| `super-admin` | semua akses |
| `sales-manager` | pesanan, retur, kupon, laporan, pengaturan penjualan; melihat produk/stok/pengiriman |
| `cashier` | mengoperasikan kasir (shift, transaksi, struk miliknya) |
| `warehouse-manager` | seluruh operasional gudang |
| `warehouse-staff` | kerja harian gudang (terima, transfer, hitung opname, ambil/kemas/kirim) |

Tambah permission modul baru di `database/seeders/RolePermissionSeeder.php`.

---

## Perintah berguna

| Perintah | Fungsi |
|----------|--------|
| `php artisan cemwp:stock-verify` | Rekonsiliasi saldo stok vs ledger & reservasi (exit ≠ 0 bila ada selisih) |
| `php artisan cemwp:expire-reservations` / `cemwp:expire-orders` | Pelepasan reservasi / kedaluwarsa pesanan (dijadwalkan tiap menit) |
| `php artisan cemwp:user email --role=...` | Buat/ubah user staf tanpa OTP |
| `php artisan test` | Test suite (butuh PostgreSQL kosong `cemwp_test`) |

---

## Multi-toko / jenis bisnis lain

Sample `DemoSeeder` bertema **toko kopi**, tetapi model data **generik** (SKU, kategori, gudang, supplier, order). Cocok untuk fashion, minimarket, elektronik, F&B kemasan, dll.

- Ganti identitas lewat `.env` di atas.
- Input kategori & produk sendiri (atau pakai seeder alternatif: Fashion / Minimarket / Elektronik).
- Batasan saat ini: satuan bulat, varian = SKU terpisah, belum batch/expired.

Detail panduan multi-toko ada di paket terpisah `PANDUAN_CEMWP_MULTI_TOKO.md` (jika disediakan).

---

## Untuk Project 5 (React Native)

Lihat **[docs/API.md](docs/API.md)**:

- Katalog delta-sync untuk SQLite
- Auth pelanggan (OTP email)
- `POST /api/v1/orders` dengan `Idempotency-Key`
- Status pesanan & webhook pembayaran

Keamanan mobile: token di MMKV terenkripsi; harga/total/diskon selalu dihitung ulang di server.

---

## Roadmap

1. ✅ Fondasi + Warehouse  
2. ✅ Sales — POS, etalase web, Midtrans, kupon, retur, laporan, API mobile  
3. **Project 5** — React Native memakai `docs/API.md`  
4. **CRM lanjutan** — admin pelanggan, segmentasi, riwayat interaksi  
5. **Marketing** — kampanye, kupon personal, WhatsApp Business API  
6. Nanti: ongkir real-time, varian native, satuan desimal, notifikasi ke pembeli  

---

## Status verifikasi (jujur)

Sudah diperiksa di lingkungan pembuatan:

- sintaks seluruh berkas PHP dan **build Vite/Vue seluruh halaman**;
- pemeriksaan silang statis: rute Vue↔PHP, controller, Inertia, permission, relasi Eloquent;
- pengujian SQL/constraint/penguncian pada **PostgreSQL sungguhan**: stok tidak negatif; ledger tidak bisa diubah; dua kasir berebut unit terakhir → satu berhasil; request kembar tidak menggandakan stok/order; kupon terakhir hanya terpakai satu pembeli; shift tunggal per kasir; listener CRM idempoten.

**Belum dijalankan di lingkungan tersebut:** aplikasi Laravel utuh end-to-end (browser, `php artisan test` penuh, email OTP nyata, HTTP ke Midtrans).  

Langkah paling berharga sebelum audit: `php artisan migrate --seed` lalu `php artisan test`.
