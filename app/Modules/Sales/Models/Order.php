<?php

namespace App\Modules\Sales\Models;

use App\Models\User;
use App\Modules\Crm\Models\Customer;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const POS = 'pos';
    public const ECOMMERCE = 'ecommerce';

    public const AWAITING_PAYMENT = 'awaiting_payment';
    public const PROCESSING = 'processing';
    public const SHIPPED = 'shipped';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';
    public const NEEDS_REVIEW = 'needs_review';

    public const STATUS_LABELS = [
        self::AWAITING_PAYMENT => 'Menunggu pembayaran', self::PROCESSING => 'Diproses', self::SHIPPED => 'Dikirim',
        self::COMPLETED => 'Selesai', self::CANCELLED => 'Dibatalkan', self::EXPIRED => 'Kedaluwarsa', self::NEEDS_REVIEW => 'Perlu ditinjau',
    ];

    public const PAYMENT_LABELS = [
        'unpaid' => 'Belum dibayar', 'paid' => 'Lunas', 'refund_pending' => 'Menunggu refund',
        'refunded' => 'Dikembalikan', 'partially_refunded' => 'Dikembalikan sebagian',
    ];

    protected $fillable = [
        'number', 'channel', 'status', 'payment_status', 'stock_committed', 'customer_id', 'user_id', 'shift_id', 'warehouse_id', 'coupon_id', 'coupon_code',
        'subtotal', 'discount_total', 'shipping_cost', 'tax_total', 'grand_total', 'paid_total', 'change_due',
        'customer_name', 'customer_phone', 'customer_email',
        'shipping_method_id', 'shipping_label', 'recipient_name', 'recipient_phone', 'shipping_address', 'shipping_city',
        'shipping_postal_code', 'total_weight_grams', 'courier', 'tracking_number',
        'notes', 'idempotency_key', 'expires_at', 'placed_at', 'paid_at', 'completed_at', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'stock_committed' => 'boolean',
            'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'shipping_cost' => 'decimal:2', 'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2', 'paid_total' => 'decimal:2', 'change_due' => 'decimal:2',
            'expires_at' => 'datetime', 'placed_at' => 'datetime', 'paid_at' => 'datetime',
            'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function isOnline(): bool
    {
        return $this->channel === self::ECOMMERCE;
    }

    /** Pesanan yang dihitung sebagai penjualan (uang masuk / barang keluar). */
    public function scopeRevenue(Builder $q): Builder
    {
        return $q->whereIn('status', [self::PROCESSING, self::SHIPPED, self::COMPLETED]);
    }
}
