<?php

namespace App\Modules\Warehouse\Enums;

enum FulfillmentStatus: string
{
    case Pending = 'pending';
    case Picking = 'picking';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Picking, self::Cancelled],
            self::Picking => [self::Packed, self::Cancelled],
            self::Packed => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Picking => 'Sedang diambil',
            self::Packed => 'Dikemas',
            self::Shipped => 'Dikirim',
            self::Delivered => 'Diterima',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
