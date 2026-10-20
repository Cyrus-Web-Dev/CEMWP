# API v1 untuk aplikasi mobile (Project 5: React Native)

Base URL: `https://domain-kamu/api/v1`. Semua respons JSON. Kesalahan bisnis = HTTP 422 `{ "message": "..." }`;
stok kurang = HTTP 409 `{ "message", "code": "insufficient_stock", "product_id", "requested", "available" }`;
validasi = 422 standar Laravel `{ "message", "errors": {...} }`; tanpa/salah token = 401.

Batas laju: 60 permintaan/menit per token atau IP; endpoint `auth/*` 10 permintaan/menit per IP.

## 1. Katalog (publik, tanpa token)

| Endpoint | Fungsi |
|----------|--------|
| `GET /catalog/products?updated_since=ISO8601&per_page=200&cursor=…` | Sinkronisasi **delta** ke SQLite |
| `GET /catalog/availability?product_ids[]=1&product_ids[]=2` | Stok real-time (maks. `public_stock_cap`, default 50) |
| `GET /categories` | Kategori |
| `GET /shipping-methods`, `GET /payment-methods` | Metode kirim & bayar online yang aktif |
| `POST /quote` | Hitung total (harga dari server) tanpa membuat pesanan |

**Sinkronisasi delta yang benar**

```text
Pertama kali : GET /catalog/products                    -> simpan semua, simpan server_time
Berikutnya   : GET /catalog/products?updated_since={server_time tersimpan}
               - baris dengan deleted_at terisi  -> HAPUS dari SQLite
               - baris lain                      -> UPSERT
               - ada next_cursor?                -> ulangi dengan &cursor=next_cursor sampai null
               - setelah SEMUA halaman sukses    -> simpan server_time terbaru
```

Pakai `server_time` dari respons (bukan jam HP). Cukup catat `server_time` setelah seluruh halaman berhasil diproses.

`POST /quote`
```json
{ "items": [{ "product_id": 3, "quantity": 2 }], "coupon_code": "WELCOME10", "shipping_method_id": 3 }
```
Respons: `lines[]`, `subtotal`, `discount_total`, `shipping_cost`, `tax_total`, `grand_total`, `coupon`, `coupon_error`, `shortages[]`.
Kupon tidak valid **tidak** membuat error; lihat `coupon_error`. Sertakan token (opsional) agar kupon personal / batas per pelanggan ikut dihitung.

## 2. Akun pelanggan (verifikasi email via OTP)

| Endpoint | Body | Hasil |
|----------|------|-------|
| `POST /auth/register` | `name, email, phone?, password, marketing_consent?` | 202, kode OTP dikirim ke email |
| `POST /auth/register/verify` | `email, code` | 201 `{ token, customer }` |
| `POST /auth/login` | `email, password` | 200 `{ token, customer }` |
| `POST /auth/password/forgot` | `email` | 202 (selalu sama, tidak membocorkan akun) |
| `POST /auth/password/reset` | `email, code, password` | 200 |
| `POST /auth/logout` | — (Bearer) | mencabut token saat ini |
| `GET /me` | — (Bearer) | profil |

Simpan `token` di **MMKV terenkripsi** (kunci enkripsi dari Keychain/Keystore). Kirim sebagai `Authorization: Bearer <token>`.
`marketing_consent` = persetujuan promo WhatsApp/email (UU PDP); default `false`.

> Catatan: nomor HP yang sudah tercatat di toko (mis. pernah belanja di kasir) hanya bisa diklaim lewat email yang **sama**
> dengan yang tercatat. Ini mencegah orang lain mengambil alih riwayat belanja memakai nomor HP.

## 3. Pesanan (Bearer wajib)

### `POST /orders` — header **`Idempotency-Key`** wajib (8–100 karakter, UUID baru per checkout)

```json
{
  "items": [{ "product_id": 3, "quantity": 2 }],
  "shipping_method_id": 3,
  "payment_method": "BANK_TRANSFER",
  "coupon_code": "WELCOME10",
  "recipient_name": "Rina", "recipient_phone": "081234567890",
  "shipping_address": "Jl. Sudirman 12", "shipping_city": "Padang", "shipping_postal_code": "25111",
  "notes": "Tolong dikemas rapi"
}
```
(`recipient_*`, `shipping_*` tidak wajib bila metode pengiriman = ambil di toko.)

**201 Created** → `{ "data": { number, status, payment_status, grand_total, items[], shipping{}, payment{ method, kind, status, redirect_url }, timeline[] } }`

Perilaku per metode bayar:

| `payment.kind` | Yang terjadi | Langkah app |
|----------------|--------------|-------------|
| `cod` | Stok langsung terpotong, gudang langsung menyiapkan | tampilkan "Siapkan uang pas" |
| `manual` (transfer) | Stok **dicadangkan** sampai batas waktu (`expires_at`); petugas mengonfirmasi | tampilkan instruksi dari `GET /payment-methods` |
| `gateway` (Midtrans) | Stok dicadangkan; `payment.redirect_url` berisi halaman bayar | buka `redirect_url` (WebView / browser), lalu poll `GET /orders/{number}` |

**Mengapa `Idempotency-Key`?** Bila koneksi putus setelah server memproses tetapi sebelum app menerima respons, app mengulang
permintaan dengan key yang **sama**: server mengembalikan pesanan yang sama (tidak membuat ganda, tidak memotong stok dua kali).
Buat key baru hanya untuk checkout baru. Jangan simpan key setelah pesanan sukses.

```ts
// React Native (contoh)
const key = uuid(); // simpan di state selama checkout masih berlangsung
const res = await fetch(`${API}/orders`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}`, 'Idempotency-Key': key },
  body: JSON.stringify(payload),
});
if (res.status === 201) { cart.clear(); /* MMKV */ navigate('PesananBerhasil', (await res.json()).data); }
if (res.status === 409) { /* insufficient_stock: tampilkan pesan, perbarui keranjang */ }
```

### Lainnya
| Endpoint | Fungsi |
|----------|--------|
| `GET /orders` | Daftar pesanan milik pelanggan (paginasi) |
| `GET /orders/{number}` | Detail + `timeline` |
| `POST /orders/{number}/cancel` | Batalkan (hanya saat `awaiting_payment`) |

Status pesanan: `awaiting_payment` → `processing` → `shipped` → `completed`; cabang: `cancelled`, `expired`, `needs_review`
(dana masuk tetapi stok tidak lagi ada: toko akan menghubungi / refund).

## 4. Webhook Midtrans (untuk dikonfigurasi di dashboard Midtrans)

`POST https://domain-kamu/api/webhooks/midtrans` — Payment Notification URL.
Keaslian diverifikasi dengan `signature_key` (SHA-512) dan nominal harus cocok dengan tagihan. Notifikasi ganda aman (idempoten).
Aktifkan metode `MIDTRANS` di *Penjualan > Pengaturan penjualan* setelah `MIDTRANS_SERVER_KEY` terisi.
