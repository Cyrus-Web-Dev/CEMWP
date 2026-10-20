<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Exceptions\SalesException;
use App\Modules\Sales\Models\Coupon;
use App\Modules\Sales\Models\CouponRedemption;
use App\Modules\Sales\Models\Order;
use Illuminate\Support\Facades\DB;

class CouponService
{
    /**
     * Cek kupon & hitung potongan (tanpa mencatat pemakaian).
     *
     * @param  string  $scope  'pos' | 'online'
     * @return array{coupon:Coupon,discount:float}
     */
    public function evaluate(string $code, float $subtotal, string $scope, ?int $customerId): array
    {
        $coupon = Coupon::query()->where('code', Coupon::normalizeCode($code))->first();
        if (! $coupon || ! $coupon->is_active) {
            throw new SalesException('Kode kupon tidak ditemukan atau sudah tidak aktif.');
        }

        $this->assertUsable($coupon, $subtotal, $scope, $customerId);

        return ['coupon' => $coupon, 'discount' => $this->discountFor($coupon, $subtotal)];
    }

    public function discountFor(Coupon $coupon, float $subtotal): float
    {
        $discount = $coupon->type === 'percent'
            ? floor($subtotal * (float) $coupon->value / 100)
            : (float) $coupon->value;

        if ($coupon->type === 'percent' && $coupon->max_discount !== null) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        return (float) max(0, min($discount, $subtotal));
    }

    private function assertUsable(Coupon $coupon, float $subtotal, string $scope, ?int $customerId): void
    {
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            throw new SalesException('Kupon ini belum berlaku.');
        }
        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            throw new SalesException('Kupon ini sudah berakhir.');
        }
        if ($coupon->channel !== 'all' && $coupon->channel !== $scope) {
            throw new SalesException($coupon->channel === 'online' ? 'Kupon ini hanya berlaku untuk belanja online.' : 'Kupon ini hanya berlaku di kasir.');
        }
        if ($subtotal < (float) $coupon->min_subtotal) {
            throw new SalesException('Belanja minimal Rp '.number_format((float) $coupon->min_subtotal, 0, ',', '.').' untuk memakai kupon ini.');
        }
        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw new SalesException('Kupon ini sudah habis dipakai.');
        }
        if ($coupon->customer_id !== null && $coupon->customer_id !== $customerId) {
            throw new SalesException('Kupon ini khusus untuk pelanggan tertentu.');
        }
        if ($coupon->usage_limit_per_customer !== null) {
            if ($customerId === null) {
                throw new SalesException('Kupon ini memerlukan data pelanggan (nomor HP) agar bisa dipakai.');
            }
            $used = CouponRedemption::query()->where('coupon_id', $coupon->id)->where('customer_id', $customerId)->count();
            if ($used >= $coupon->usage_limit_per_customer) {
                throw new SalesException('Kamu sudah mencapai batas pemakaian kupon ini.');
            }
        }
    }

    /**
     * Catat pemakaian kupon di dalam transaksi order. Mengunci baris kupon dan MENGEVALUASI ULANG batas pemakaian,
     * sehingga dua pembeli bersamaan tidak bisa sama-sama memakai kupon terakhir.
     */
    public function redeem(Coupon $coupon, Order $order, float $amount, ?int $customerId): void
    {
        DB::transaction(function () use ($coupon, $order, $amount, $customerId) {
            $locked = Coupon::query()->lockForUpdate()->findOrFail($coupon->id);

            if ($locked->usage_limit !== null && $locked->used_count >= $locked->usage_limit) {
                throw new SalesException('Kupon ini baru saja habis dipakai.');
            }
            if ($locked->usage_limit_per_customer !== null && $customerId !== null) {
                $used = CouponRedemption::query()->where('coupon_id', $locked->id)->where('customer_id', $customerId)->count();
                if ($used >= $locked->usage_limit_per_customer) {
                    throw new SalesException('Kamu sudah mencapai batas pemakaian kupon ini.');
                }
            }

            CouponRedemption::create([
                'coupon_id' => $locked->id, 'order_id' => $order->id, 'customer_id' => $customerId,
                'amount' => $amount, 'created_at' => now(),
            ]);
            $locked->increment('used_count');
        });
    }

    /** Batalkan pemakaian (pesanan dibatalkan / kedaluwarsa) agar kuota kupon pulih. */
    public function release(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $redemption = CouponRedemption::query()->where('order_id', $order->id)->lockForUpdate()->first();
            if (! $redemption) {
                return;
            }
            $coupon = Coupon::query()->lockForUpdate()->find($redemption->coupon_id);
            $redemption->delete();
            if ($coupon && $coupon->used_count > 0) {
                $coupon->decrement('used_count');
            }
        });
    }
}
