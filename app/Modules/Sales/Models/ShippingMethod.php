<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    protected $fillable = ['courier', 'service', 'is_pickup', 'base_cost', 'cost_per_kg', 'free_over', 'min_days', 'max_days', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_pickup' => 'boolean', 'is_active' => 'boolean',
            'base_cost' => 'decimal:2', 'cost_per_kg' => 'decimal:2', 'free_over' => 'decimal:2',
        ];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function label(): string
    {
        return $this->is_pickup ? 'Ambil di toko' : trim($this->courier.' '.$this->service);
    }

    /** Ongkir: tarif dasar + tarif/kg (dibulatkan ke atas per kg, minimal 1 kg); gratis bila subtotal melewati batas. */
    public function costFor(int $weightGrams, float $subtotal): float
    {
        if ($this->is_pickup) {
            return 0.0;
        }
        if ($this->free_over !== null && $subtotal >= (float) $this->free_over) {
            return 0.0;
        }
        $kg = max(1, (int) ceil($weightGrams / 1000));

        return round((float) $this->base_cost + $kg * (float) $this->cost_per_kg, 2);
    }
}
