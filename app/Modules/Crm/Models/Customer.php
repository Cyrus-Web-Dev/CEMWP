<?php

namespace App\Modules\Crm\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Pelanggan (tamu atau berakun). Login etalase web memakai guard "customer"; aplikasi mobile memakai token Sanctum.
 */
class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'email_verified_at', 'status', 'source', 'marketing_consent', 'marketing_consent_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'marketing_consent' => 'boolean',
            'marketing_consent_at' => 'datetime',
            'total_spent' => 'decimal:2',
            'first_order_at' => 'datetime',
            'last_order_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasAccount(): bool
    {
        return $this->password !== null;
    }

    /** "0812-3456-789" / "+62 812 3456 789" / "812..." -> "62812..." (null bila tidak masuk akal). */
    public static function normalizePhone(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '' || $digits === null) {
            return null;
        }
        if (str_starts_with($digits, '62')) {
            $n = $digits;
        } elseif (str_starts_with($digits, '0')) {
            $n = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $n = '62'.$digits;
        } else {
            $n = $digits;
        }

        return (strlen($n) >= 10 && strlen($n) <= 15) ? $n : null;
    }

    public static function normalizeEmail(?string $raw): ?string
    {
        $e = $raw === null ? '' : strtolower(trim($raw));

        return $e === '' ? null : $e;
    }
}
