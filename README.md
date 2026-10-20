# CEMWP — Connected Commerce

Laravel 11 · Inertia · Vue 3 · Tailwind · PostgreSQL · Sanctum · Gmail API · Midtrans.
Satu aplikasi, satu database, modul bisnis di `app/Modules/*` (lihat [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)).

## Isi rilis ini

| Modul | Isi |
|-------|-----|
| **Inti** | Login staf 2FA (OTP Gmail), pengguna & role, log aktivitas |
| **Warehouse** (Project 2) | Produk, kategori, lokasi stok, stok + ledger mutasi, penyesuaian, supplier, PO & penerimaan, transfer, stok opname, pengiriman pesanan (pick → pack → ship), dashboard, API katalog |
| **Sales** (Project 1) | **Kasir/POS** (shift, scan barcode, pecah pembayaran, tahan/lanjutkan, struk), **toko online** (katalog, keranjang, checkout, akun, lacak pesanan), pesanan back-office, kupon, retur, laporan, pengaturan bayar/kirim, **Midtrans**, **API v1 untuk React Native** |
| **CRM** (awal) | Identitas pelanggan + profil perilaku belanja otomatis dari tiap pesanan lunas |

Alur "Connected Loop" yang sudah berjalan: transaksi POS/online → stok terpotong real-time → tugas gudang → status kirim
kembali ke pesanan → profil pelanggan (CRM) terisi. Marketing (kupon WhatsApp otomatis) adalah Project 4.

## Pasang (lokal)

Prasyarat: PHP 8.2+ (pdo_pgsql, mbstring, intl, curl), Composer, Node 18+, PostgreSQL 14+.

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

* Halaman depan `/` = **toko online** (`/shop`). Staf masuk di `/login`.
* Akun demo (hanya bila `APP_ENV=local` dan `DemoSeeder` dijalankan), password `Password123`:
  `kasir@cemwp.test` (kasir), `sales@cemwp.test` (manajer penjualan), `gudang@cemwp.test` (staf gudang).
  Login staf memakai kode OTP email; bila Gmail belum siap, kode tercatat di `storage/logs/laravel.log` (mode lokal).
* Belum punya admin? `php artisan cemwp:user kamu@email.com --name="Nama" --password="PasswordKuat123" --role=super-admin`

**Email OTP (Gmail API):** salin `credentials.json` + `token.json` dari proyek SDM/MKP ke `storage/app/credentials/`.
OTP dipakai untuk login staf **dan** verifikasi email pelanggan (daftar / lupa password). Cek: `php artisan cemwp:mail-test emailkamu@gmail.com`.

**Scheduler (wajib):** `* * * * * cd /path/ke/cemwp && php artisan schedule:run >> /dev/null 2>&1`
Menjalankan: pelepasan reservasi stok, kedaluwarsa pesanan online yang tak dibayar, rekonsiliasi stok malam.

**Midtrans (opsional):** isi `MIDTRANS_SERVER_KEY` / `MIDTRANS_CLIENT_KEY`, aktifkan metode `MIDTRANS` di
*Penjualan → Pengaturan penjualan*, dan set Payment Notification URL di dashboard Midtrans ke
`https://domain-kamu/api/webhooks/midtrans`. Tanpa Midtrans, pembeli online tetap bisa memakai **transfer bank** (dikonfirmasi petugas) atau **COD**.

## Role bawaan

| Role | Untuk |
|------|-------|
| `super-admin` | semua akses |
| `sales-manager` | pesanan, retur, kupon, laporan, pengaturan penjualan; melihat produk/stok/pengiriman |
| `cashier` | mengoperasikan kasir (shift, transaksi, struk miliknya) |
| `warehouse-manager` | seluruh operasional gudang |
| `warehouse-staff` | kerja harian gudang (terima, transfer, hitung opname, ambil/kemas/kirim) |

Tambah permission modul baru di `database/seeders/RolePermissionSeeder.php`.

## Perintah berguna

| Perintah | Fungsi |
|----------|--------|
| `php artisan cemwp:stock-verify` | Rekonsiliasi saldo stok vs ledger & reservasi (exit code ≠ 0 bila ada selisih) |
| `php artisan cemwp:expire-reservations` / `cemwp:expire-orders` | Pelepasan reservasi / kedaluwarsa pesanan (dijadwalkan tiap menit) |
| `php artisan cemwp:user email --role=...` | Buat/ubah user staf tanpa OTP |
| `php artisan test` | Test suite (butuh PostgreSQL kosong `cemwp_test`) |

## Untuk Project 5 (React Native)

Lihat **[docs/API.md](docs/API.md)**: katalog delta-sync untuk SQLite, auth pelanggan (OTP email), `POST /api/v1/orders`
dengan `Idempotency-Key`, status pesanan, dan webhook pembayaran.

## Status verifikasi (jujur)

Sudah diperiksa di lingkungan pembuatan:
* sintaks seluruh berkas PHP (145 berkas) dan **build Vite/Vue seluruh 46 halaman**;
* pemeriksaan silang statis: nama rute Vue↔PHP (151 rute), metode controller, kelas yang di-`use`, halaman Inertia, kelas CSS, permission, relasi Eloquent;
* pengujian SQL/constraint/trigger/penguncian pada **PostgreSQL 16 sungguhan**: stok tidak bisa negatif; ledger tidak bisa diubah;
  dua kasir berebut unit terakhir → satu berhasil; request kembar tidak menggandakan stok/order; kupon terakhir hanya terpakai satu pembeli;
  indeks unik parsial pelanggan; shift tunggal per kasir; SQL asli listener CRM menghasilkan profil yang benar dan idempoten.

**Belum bisa dijalankan di sana:** aplikasi Laravel utuh (Packagist tidak terjangkau dari lingkungan itu). Artinya alur lewat browser,
`php artisan test` (28 test untuk stok, POS, online, kupon, retur, shift, Midtrans, hak akses), pengiriman email OTP, dan panggilan
HTTP nyata ke Midtrans **belum pernah dieksekusi**. Jalankan `php artisan migrate --seed` lalu `php artisan test` — itu langkah paling berharga
sebelum audit menyeluruh.
