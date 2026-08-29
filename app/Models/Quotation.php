<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    protected $fillable = [
        'created_by', 'customer_id', 'name', 'notes',
        'customer_name', 'customer_phone', 'order_type', 'draft_version',
        'checkout_state', 'total', 'status',
    ];

    protected $casts = [
        'checkout_state' => 'array',
        'draft_version' => 'integer',
        'total' => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name ?? ($this->customer_name ? "Cliente: {$this->customer_name}" : "Borrador #{$this->id}");
    }
}
