<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    public const PRESENTATION_TYPES = ['promotion', 'discount', 'new'];

    public const PRICING_RULE_BUY_X_GET_Y_DISCOUNT = 'buy_x_get_y_discount';

    public const PRICING_RULE_PERCENTAGE_DISCOUNT = 'percentage_discount';

    public const PRICING_RULE_FIXED_PRODUCT_PRICE = 'fixed_product_price';

    public const AUTOMATIC_PRICING_RULES = [
        self::PRICING_RULE_BUY_X_GET_Y_DISCOUNT,
        self::PRICING_RULE_PERCENTAGE_DISCOUNT,
        self::PRICING_RULE_FIXED_PRODUCT_PRICE,
    ];

    public const RECURRENCE_TYPES = ['date_range', 'weekdays', 'monthly'];

    public const FULFILLMENT_MODES = ['dine_in', 'takeaway', 'pickup', 'delivery'];

    public const POS_FULFILLMENT_MODES = ['takeaway', 'pickup', 'delivery'];

    public const KIOSK_FULFILLMENT_MODES = ['dine_in', 'takeaway', 'delivery'];

    protected $fillable = [
        'name', 'description', 'presentation_type', 'primary_product_id', 'short_description', 'image', 'price',
        'discount_percentage', 'pricing_rule_type', 'pricing_rule_config', 'auto_apply',
        'starts_on', 'ends_on', 'recurrence_type', 'weekdays', 'monthly_day', 'fulfillment_modes',
        'terms_and_conditions', 'show_on_pos', 'show_on_digital_menu', 'show_on_kiosk', 'is_active', 'created_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_percentage' => 'integer',
        'pricing_rule_config' => 'array',
        'auto_apply' => 'boolean',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'weekdays' => 'array',
        'monthly_day' => 'integer',
        'fulfillment_modes' => 'array',
        'show_on_pos' => 'boolean',
        'show_on_digital_menu' => 'boolean',
        'show_on_kiosk' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Promotion $promotion): void {
            if ($promotion->isDirty('weekdays') && ! $promotion->isDirty('recurrence_type')) {
                $promotion->recurrence_type = ($promotion->weekdays ?? []) === [] ? 'date_range' : 'weekdays';
            }
        });
    }

    public function groups(): HasMany
    {
        return $this->hasMany(PromotionGroup::class)->orderBy('sort_order')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function primaryProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'primary_product_id');
    }

    public function isProductLaunch(): bool
    {
        return $this->presentation_type === 'new';
    }

    public function hasAutomaticPricingRule(): bool
    {
        return $this->auto_apply
            && in_array($this->pricing_rule_type, self::AUTOMATIC_PRICING_RULES, true)
            && $this->primary_product_id !== null;
    }

    public function normalizedPricingRule(): array
    {
        $config = $this->pricing_rule_config ?? [];

        return [
            'version' => 1,
            'buy_quantity' => max(1, min(99, (int) ($config['buy_quantity'] ?? 1))),
            'reward_quantity' => max(1, min(99, (int) ($config['reward_quantity'] ?? 1))),
            'reward_discount_percentage' => max(1, min(100, (int) ($config['reward_discount_percentage'] ?? 50))),
            'discount_percentage' => max(1, min(100, (int) ($config['discount_percentage'] ?? $this->discount_percentage ?? 10))),
            'fixed_price' => max(0.01, round((float) ($config['fixed_price'] ?? $this->price), 2)),
            'max_applications_per_order' => filled($config['max_applications_per_order'] ?? null)
                ? max(1, min(99, (int) $config['max_applications_per_order']))
                : null,
            'apply_to_addons' => false,
            'reward_scope' => ($config['reward_scope'] ?? 'same_product') === 'eligible_group'
                ? 'eligible_group'
                : 'same_product',
        ];
    }

    public function pricingRuleLabel(): ?string
    {
        if (! $this->hasAutomaticPricingRule()) {
            return null;
        }

        $rule = $this->normalizedPricingRule();

        if ($this->pricing_rule_type === self::PRICING_RULE_PERCENTAGE_DISCOUNT) {
            return "{$rule['discount_percentage']}% de descuento";
        }

        if ($this->pricing_rule_type === self::PRICING_RULE_FIXED_PRODUCT_PRICE) {
            return 'Precio especial $'.number_format($rule['fixed_price'], 2);
        }

        if ($rule['reward_discount_percentage'] === 100) {
            return ($rule['buy_quantity'] + $rule['reward_quantity']).'x'.$rule['buy_quantity'];
        }

        if ($rule['buy_quantity'] === 1 && $rule['reward_quantity'] === 1 && $rule['reward_discount_percentage'] === 50) {
            return 'Compra 1 y el segundo a mitad de precio';
        }

        return "Compra {$rule['buy_quantity']} y recibe {$rule['reward_quantity']} con {$rule['reward_discount_percentage']}% de descuento";
    }

    public function pricingRuleShortLabel(): ?string
    {
        if (! $this->hasAutomaticPricingRule()) {
            return null;
        }

        $rule = $this->normalizedPricingRule();

        if ($this->pricing_rule_type === self::PRICING_RULE_PERCENTAGE_DISCOUNT) {
            return "-{$rule['discount_percentage']}%";
        }

        if ($this->pricing_rule_type === self::PRICING_RULE_FIXED_PRODUCT_PRICE) {
            return '$'.number_format($rule['fixed_price'], 2);
        }

        if ($rule['reward_discount_percentage'] === 100) {
            return ($rule['buy_quantity'] + $rule['reward_quantity']).'x'.$rule['buy_quantity'];
        }

        if ($rule['buy_quantity'] === 1 && $rule['reward_quantity'] === 1 && $rule['reward_discount_percentage'] === 50) {
            return '2.º al 50%';
        }

        return "Compra {$rule['buy_quantity']} + {$rule['reward_quantity']} con -{$rule['reward_discount_percentage']}%";
    }

    public function scopeAutomaticPricingAvailable(
        Builder $query,
        string $channel,
        ?CarbonInterface $at = null,
        ?string $fulfillment = null,
    ): Builder {
        $at ??= now();
        $column = match ($channel) {
            'digital_menu' => 'show_on_digital_menu',
            'kiosk' => 'show_on_kiosk',
            default => 'show_on_pos',
        };
        $weekday = $at->dayOfWeekIso;

        return $query->where('is_active', true)
            ->where('auto_apply', true)
            ->whereIn('pricing_rule_type', self::AUTOMATIC_PRICING_RULES)
            ->whereNotNull('primary_product_id')
            ->where($column, true)
            ->when($fulfillment, fn (Builder $available) => $available->where(fn (Builder $modes) => $modes
                ->whereNull('fulfillment_modes')->orWhereJsonLength('fulfillment_modes', 0)
                ->orWhereJsonContains('fulfillment_modes', $fulfillment)))
            ->whereDate('starts_on', '<=', $at->toDateString())
            ->where(fn (Builder $dates) => $dates->whereNull('ends_on')->orWhereDate('ends_on', '>=', $at->toDateString()))
            ->where(fn (Builder $schedule) => $this->applyRecurrenceQuery($schedule, $weekday, $at->day));
    }

    public function scopeAvailable(
        Builder $query,
        string $channel,
        ?CarbonInterface $at = null,
        ?string $fulfillment = null,
    ): Builder
    {
        $at ??= now();
        $column = match ($channel) {
            'digital_menu' => 'show_on_digital_menu',
            'kiosk' => 'show_on_kiosk',
            default => 'show_on_pos',
        };
        $weekday = $at->dayOfWeekIso;

        return $query
            ->where('is_active', true)
            ->where($column, true)
            ->when(in_array($channel, ['pos', 'kiosk'], true), fn (Builder $available) => $available
                ->where('presentation_type', '!=', 'new')
                ->where(fn (Builder $manual) => $manual->whereNull('auto_apply')->orWhere('auto_apply', false)))
            ->when(
                $fulfillment && in_array($fulfillment, self::FULFILLMENT_MODES, true),
                fn (Builder $available) => $available->where(fn (Builder $modes) => $modes
                    ->whereNull('fulfillment_modes')
                    ->orWhereJsonLength('fulfillment_modes', 0)
                    ->orWhereJsonContains('fulfillment_modes', $fulfillment))
            )
            ->whereDate('starts_on', '<=', $at->toDateString())
            ->where(fn (Builder $dates) => $dates
                ->whereNull('ends_on')
                ->orWhereDate('ends_on', '>=', $at->toDateString()))
            ->where(fn (Builder $schedule) => $this->applyRecurrenceQuery($schedule, $weekday, $at->day));
    }

    public function isAvailableFor(string $channel, ?CarbonInterface $at = null, ?string $fulfillment = null): bool
    {
        return static::query()->whereKey($this->getKey())->available($channel, $at, $fulfillment)->exists();
    }

    public function scopeForAnyFulfillment(Builder $query, array $fulfillments): Builder
    {
        $fulfillments = array_values(array_intersect(
            self::FULFILLMENT_MODES,
            array_map('strval', $fulfillments)
        ));

        if ($fulfillments === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $modes) use ($fulfillments): void {
            $modes->whereNull('fulfillment_modes')
                ->orWhereJsonLength('fulfillment_modes', 0);

            foreach ($fulfillments as $fulfillment) {
                $modes->orWhereJsonContains('fulfillment_modes', $fulfillment);
            }
        });
    }

    public function appliesToFulfillment(string $fulfillment): bool
    {
        if ($this->isProductLaunch() && ! $this->hasAutomaticPricingRule()) {
            return true;
        }

        $modes = array_values(array_intersect(
            self::FULFILLMENT_MODES,
            array_map('strval', $this->fulfillment_modes ?? [])
        ));

        return $modes === [] || in_array($fulfillment, $modes, true);
    }

    public function fulfillmentLabels(): array
    {
        $labels = [
            'dine_in' => 'Comer aquí',
            'takeaway' => 'Para llevar',
            'pickup' => 'Pasar a buscar',
            'delivery' => 'Entrega a domicilio',
        ];
        $modes = $this->isProductLaunch() && ! $this->hasAutomaticPricingRule()
            ? self::FULFILLMENT_MODES
            : ($this->fulfillment_modes ?: self::FULFILLMENT_MODES);

        return collect($modes)->map(fn (string $mode) => $labels[$mode] ?? null)->filter()->values()->all();
    }

    public function fulfillmentSummary(): string
    {
        $labels = $this->fulfillmentLabels();

        if (count($labels) === 1) {
            return match (array_key_first(array_filter([
                'dine_in' => $this->appliesToFulfillment('dine_in'),
                'takeaway' => $this->appliesToFulfillment('takeaway'),
                'pickup' => $this->appliesToFulfillment('pickup'),
                'delivery' => $this->appliesToFulfillment('delivery'),
            ]))) {
                'dine_in' => 'Solo para comer aquí',
                'takeaway' => 'Solo para llevar',
                'pickup' => 'Solo para pasar a buscar',
                'delivery' => 'Solo con entrega a domicilio',
                default => 'Modalidad específica',
            };
        }

        return count($labels) === count(self::FULFILLMENT_MODES)
            ? 'Disponible en todas las modalidades'
            : 'Disponible en: '.collect($labels)->join(', ', ' y ');
    }

    public function isScheduledFor(CarbonInterface $at, ?string $channel = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($channel === 'pos' && ! $this->show_on_pos) {
            return false;
        }

        if ($channel === 'digital_menu' && ! $this->show_on_digital_menu) {
            return false;
        }

        if ($channel === 'kiosk' && ! $this->show_on_kiosk) {
            return false;
        }

        $date = $at->toDateString();
        if ($this->starts_on->toDateString() > $date || ($this->ends_on && $this->ends_on->toDateString() < $date)) {
            return false;
        }

        return match ($this->recurrence_type ?: 'date_range') {
            'weekdays' => in_array($at->dayOfWeekIso, array_map('intval', $this->weekdays ?? []), true),
            'monthly' => (int) $this->monthly_day === $at->day,
            default => true,
        };
    }

    public function presentationLabel(): string
    {
        if ($this->hasAutomaticPricingRule()) {
            return $this->pricingRuleLabel() ?? 'Beneficio automático';
        }

        return match ($this->presentation_type) {
            'discount' => $this->discount_percentage ? "{$this->discount_percentage}% de descuento" : 'Precio con descuento',
            'new' => 'Nuevo en el menú',
            default => 'Promoción especial',
        };
    }

    public function presentationIcon(): string
    {
        return match ($this->presentation_type) {
            'discount' => 'bx-purchase-tag',
            'new' => 'bx-star',
            default => 'bx-gift',
        };
    }

    public function weekdayLabel(): string
    {
        $labels = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
        $weekdays = array_map('intval', $this->weekdays ?? []);

        return $weekdays === []
            ? 'Todos los días'
            : collect($weekdays)->map(fn (int $day) => $labels[$day] ?? null)->filter()->join(', ');
    }

    public function scheduleSummary(): string
    {
        return match ($this->recurrence_type ?: 'date_range') {
            'weekdays' => $this->weekdayLabel(),
            'monthly' => 'Cada mes, el día '.(int) $this->monthly_day,
            default => $this->ends_on
                ? 'Del '.$this->starts_on->format('d/m/Y').' al '.$this->ends_on->format('d/m/Y')
                : 'Desde el '.$this->starts_on->format('d/m/Y').' sin fecha final',
        };
    }

    private function applyRecurrenceQuery(Builder $query, int $weekday, int $monthDay): Builder
    {
        return $query
            ->where(fn (Builder $range) => $range->whereNull('recurrence_type')->orWhere('recurrence_type', 'date_range'))
            ->orWhere(fn (Builder $weekly) => $weekly->where('recurrence_type', 'weekdays')->whereJsonContains('weekdays', $weekday))
            ->orWhere(fn (Builder $monthly) => $monthly->where('recurrence_type', 'monthly')->where('monthly_day', $monthDay));
    }
}
