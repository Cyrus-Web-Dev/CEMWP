<?php

namespace App\Modules\Warehouse\Enums;

/** Jenis mutasi di buku besar stok (stock_movements.type). */
enum MovementType: string
{
    case Receipt = 'receipt';           // barang masuk dari supplier
    case Sale = 'sale';                 // keluar karena penjualan (POS / e-commerce)
    case SaleReturn = 'sale_return';    // masuk karena retur / pembatalan penjualan
    case TransferOut = 'transfer_out';  // keluar karena dikirim ke lokasi lain
    case TransferIn = 'transfer_in';    // masuk dari lokasi lain
    case Adjustment = 'adjustment';     // koreksi manual (rusak, hilang, dll.)
    case Opname = 'opname';             // koreksi hasil hitung fisik

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Penerimaan barang',
            self::Sale => 'Penjualan',
            self::SaleReturn => 'Retur penjualan',
            self::TransferOut => 'Transfer keluar',
            self::TransferIn => 'Transfer masuk',
            self::Adjustment => 'Penyesuaian',
            self::Opname => 'Stok opname',
        };
    }
}
