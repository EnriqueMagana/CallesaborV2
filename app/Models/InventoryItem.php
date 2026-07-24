<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    public const UNITS = [
        'piece' => ['label' => 'Piezas', 'short' => 'pzas'],
        'box' => ['label' => 'Cajas', 'short' => 'cajas'],
        'gram' => ['label' => 'Gramos', 'short' => 'g'],
        'kilogram' => ['label' => 'Kilogramos', 'short' => 'kg'],
        'milliliter' => ['label' => 'Mililitros', 'short' => 'ml'],
        'liter' => ['label' => 'Litros', 'short' => 'L'],
        'package' => ['label' => 'Paquetes', 'short' => 'paq'],
    ];

    protected $guarded = [];

    protected $casts = [
        'current_stock' => 'decimal:3',
        'minimum_stock' => 'decimal:3',
        'estimated_unit_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(InventoryPurchaseItem::class);
    }

    public function getUnitLabelAttribute(): string
    {
        return self::UNITS[$this->unit]['label'] ?? ucfirst($this->unit);
    }

    public function getUnitShortAttribute(): string
    {
        return self::UNITS[$this->unit]['short'] ?? $this->unit;
    }

    public function getIsLowStockAttribute(): bool
    {
        return (float) $this->current_stock <= (float) $this->minimum_stock;
    }
}
