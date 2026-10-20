<?php

namespace App\Modules\Warehouse\Exceptions;

use RuntimeException;

/** Kesalahan bisnis pada operasi gudang. Pesannya aman & ramah untuk ditampilkan ke pengguna. */
class StockException extends RuntimeException
{
}
