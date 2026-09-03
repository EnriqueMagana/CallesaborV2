<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Discount extends Model
{
    public const AUDIENCE_EMPLOYEES = 'employees';

    public const AUDIENCE_SELECTED_EMPLOYEES = 'selected_employees';

    public const CATEGORIES = ['occasional', 'seasonal', 'associate', 'customer', 'employee', 'other'];

    public const VALUE_TYPES = ['percentage', 'fixed'];

    public const SCOPES = ['order', 'products'];

    public const AUDIENCES = ['everyone', 'customers', 'selected_customers', self::AUDIENCE_EMPLOYEES, self::AUDIENCE_SELECTED_EMPLOYEES];

    public const FULFILLMENT_MODES = ['dine_in', 'takeaway', 'pickup', 'delivery'];

    protected $fillable = [
        'name', 'description', 'category', 'value_type', 'value', 'scope', 'audience',
        'minimum_purchase', 'maximum_discount', 'fulfillment_modes', 'starts_at', 'ends_at',
        'priority', 'combine_with_promotions', 'auto_apply', 'is_active', 'created_by',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'minimum_purchase' => 'decimal:2',
        'maximum_discount' => 'decimal:2',
        'fulfillment_modes' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'priority' => 'integer',
        'combine_with_promotions' => 'boolean',
        'auto_apply' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'discount_user');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeAvailable(Builder $query, string $fulfillment, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query->where('is_active', true)
            ->where('auto_apply', true)
            ->where(fn (Builder $dates) => $dates->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $dates) => $dates->whereNull('ends_at')->orWhere('ends_at', '>=', $at))
            ->where(fn (Builder $modes) => $modes
                ->whereNull('fulfillment_modes')
                ->orWhereJsonLength('fulfillment_modes', 0)
                ->orWhereJsonContains('fulfillment_modes', $fulfillment));
    }

    public function benefitLabel(): string
    {
        return $this->value_type === 'percentage'
            ? rtrim(rtrim(number_format((float) $this->value, 2, '.', ''), '0'), '.').'% de descuento'
            : '$'.number_format((float) $this->value, 2).' de descuento';
    }

    public function audienceLabel(): string
    {
        return match ($this->audience) {
            'customers' => 'Clientes registrados',
            'selected_customers' => 'Clientes seleccionados',
            'employees' => 'Todo el personal',
            'selected_employees' => 'Personal seleccionado',
            default => 'Todo público',
        };
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'seasonal' => 'Temporada / Buen Fin',
            'associate' => 'Asociado',
            'customer' => 'Cliente',
            'employee' => 'Empleado',
            'other' => 'Otro',
            default => 'Ocasional',
        };
    }
}
