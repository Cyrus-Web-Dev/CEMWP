<?php

namespace App\Modules\Sales\Models;

use App\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    public const OPEN = 'open';
    public const CLOSED = 'closed';

    protected $fillable = ['user_id', 'warehouse_id', 'status', 'opened_at', 'closed_at', 'opening_cash', 'expected_cash', 'counted_cash', 'difference', 'notes'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime', 'closed_at' => 'datetime',
            'opening_cash' => 'decimal:2', 'expected_cash' => 'decimal:2', 'counted_cash' => 'decimal:2', 'difference' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', self::OPEN);
    }
}
