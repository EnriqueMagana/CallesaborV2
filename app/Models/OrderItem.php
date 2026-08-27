<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'promotion_id', 'product_name', 'product_price',
        'quantity', 'subtotal', 'promotion_discount', 'notes', 'promotion_selections', 'promotion_rule_snapshot',
        'is_cancelled', 'cancelled_by', 'cancelled_at',
    ];

    protected $casts = [
        'product_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'promotion_discount' => 'decimal:2',
        'is_cancelled' => 'boolean',
        'cancelled_at' => 'datetime',
        'promotion_selections' => 'array',
        'promotion_rule_snapshot' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(OrderItemAddon::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(OrderItemIngredient::class);
    }
}
