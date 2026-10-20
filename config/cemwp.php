<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Keamanan tambahan untuk PRODUCTION
    |--------------------------------------------------------------------------
    */
    'security' => [
        // off | report-only | enforce (lihat App\Http\Middleware\SecurityHeaders)
        'csp' => env('CEMWP_CSP', 'off'),
        // true HANYA bila situs sudah punya sertifikat HTTPS aktif (otomatis tidak aktif di APP_ENV=local)
        'force_https' => (bool) env('CEMWP_FORCE_HTTPS', false),
    ],

    // Akun super-admin awal, dibuat otomatis saat `php artisan migrate --seed` (tanpa perlu OTP pertama kali).
    'admin' => [
        'name' => env('CEMWP_ADMIN_NAME', 'Administrator'),
        'email' => env('CEMWP_ADMIN_EMAIL'),
        'password' => env('CEMWP_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Modul Warehouse
    |--------------------------------------------------------------------------
    */
    'warehouse' => [
        // Reservasi stok (checkout e-commerce yang belum dibayar) kedaluwarsa setelah N menit.
        'reservation_ttl_minutes' => (int) env('CEMWP_RESERVATION_TTL_MINUTES', 30),
        // Gudang bawaan untuk stok online & penerimaan barang (kode gudang).
        'default_warehouse_code' => env('CEMWP_DEFAULT_WAREHOUSE', 'GD-UTAMA'),
        // Jumlah baris per halaman di tabel besar (produk, stok, mutasi).
        'per_page' => (int) env('CEMWP_PER_PAGE', 25),
        // Token API pembaca katalog kedaluwarsa dalam N menit (default 30 hari).
        'api_token_ttl_minutes' => (int) env('CEMWP_API_TOKEN_TTL_MINUTES', 60 * 24 * 30),
        // Stok yang ditampilkan ke publik (API katalog / etalase) dibatasi agar jumlah persis tidak bocor ke kompetitor.
        'public_stock_cap' => (int) env('CEMWP_PUBLIC_STOCK_CAP', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Modul Sales (POS & E-commerce)
    |--------------------------------------------------------------------------
    */
    'sales' => [
        'store_name' => env('CEMWP_STORE_NAME', 'Toko Kopi CEMWP'),
        'store_address' => env('CEMWP_STORE_ADDRESS', ''),
        'store_phone' => env('CEMWP_STORE_PHONE', ''),
        'receipt_footer' => env('CEMWP_RECEIPT_FOOTER', 'Terima kasih sudah berbelanja!'),
        // Batas waktu pembayaran pesanan online (menit). Stok dicadangkan selama waktu ini.
        'order_ttl_minutes' => (int) env('CEMWP_ORDER_TTL_MINUTES', 60),
        // PPN tambahan di atas harga (persen). 0 = harga sudah final / toko belum PKP.
        'tax_rate' => (float) env('CEMWP_TAX_RATE', 0),
        'max_line_qty' => 999,
        'shop_per_page' => 24,
        // Informasi pembayaran manual (transfer bank) yang ditampilkan ke pembeli online.
        'bank_transfer_info' => env('CEMWP_BANK_TRANSFER_INFO', 'Transfer ke BCA 000-000-0000 a.n. Nama Toko'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment gateway
    |--------------------------------------------------------------------------
    */
    'payments' => [
        'midtrans' => [
            'server_key' => env('MIDTRANS_SERVER_KEY'),
            'client_key' => env('MIDTRANS_CLIENT_KEY'),
            'production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        ],
    ],
];
