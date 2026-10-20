<?php

namespace App\Modules\Warehouse\Exceptions;

use App\Modules\Warehouse\Models\Product;

class InsufficientStockException extends StockException
{
    public function __construct(
        public readonly int $productId,
        public readonly int $warehouseId,
        public readonly int $requested,
        public readonly int $available,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function for(Product $product, int $warehouseId, int $requested, int $available): self
    {
        return new self(
            $product->id,
            $warehouseId,
            $requested,
            $available,
            "Stok \"{$product->name}\" tidak cukup: diminta {$requested}, tersedia {$available}."
        );
    }
}
