<?php

namespace App\Modules\Warehouse\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * KONTRAK PUBLIK modul Warehouse untuk modul lain (Sales/POS/E-commerce).
 * Modul lain HANYA boleh mengubah stok lewat interface ini — jangan menulis ke tabel inventories langsung.
 *
 * Format $items: [['product_id' => 12, 'quantity' => 2], ...]  (produk dengan track_stock=false dilewati otomatis)
 */
interface StockGateway
{
    /** Stok tersedia (on_hand - reserved). $warehouseId null = jumlahkan semua lokasi aktif. */
    public function availability(int $productId, ?int $warehouseId = null): int;

    /** @return array<int,int> [product_id => tersedia] */
    public function availabilityMap(array $productIds, ?int $warehouseId = null): array;

    /**
     * Cadangkan stok (checkout e-commerce yang belum dibayar). Idempoten per ($reference, produk, lokasi).
     * Melempar InsufficientStockException bila stok tidak cukup (tidak ada yang tercadangkan sebagian).
     */
    public function reserve(string $reference, int $warehouseId, array $items, ?CarbonInterface $expiresAt = null): Collection;

    /**
     * Pembayaran diterima: potong stok sesungguhnya dari reservasi aktif $reference.
     * Mengembalikan koleksi StockMovement; KOSONG bila tidak ada reservasi aktif (mis. sudah kedaluwarsa) —
     * pemanggil harus menanganinya (reserve ulang atau refund).
     */
    public function commitReservation(string $reference, ?string $referenceNumber = null, ?int $userId = null): Collection;

    /** Lepas reservasi aktif (checkout dibatalkan). Mengembalikan jumlah baris yang dilepas. */
    public function releaseReservation(string $reference): int;

    /** Penjualan langsung tanpa reservasi (POS). Atomik: semua item berhasil atau tidak sama sekali. */
    public function deductForSale(int $warehouseId, array $items, string $referenceNumber, ?string $idempotencyKey = null, ?int $userId = null): Collection;

    /** Barang kembali ke stok (retur/pembatalan setelah stok terpotong). */
    public function restock(int $warehouseId, array $items, string $referenceNumber, ?string $idempotencyKey = null, ?int $userId = null, ?string $reason = null): Collection;
}
