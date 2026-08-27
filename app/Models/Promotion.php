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

    public const FULFILLMENT_MODES = ['dine_in', 'takeaway', 'pickup', 'delivery'];

    public const POS_FULFILLMENT_MODES = ['takeaway', 'pickup', 'delivery'];

    public const KIOSK_FULFILLMENT_MODES = ['dine_in', 'takeaway', 'delivery'];

    protected $fillable = [
        'name', 'description', 'presentation_type', 'primary_product_id', 'short_description', 'image', 'price',
        'discount_percentage', 'pricing_rule_type', 'pricing_rule_config', 'auto_apply',
        'starts_on', 'ends_on', 'weekdays', 'fulfillment_modes',
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
        'fulfillment_modes' => 'array',
        'show_on_pos' => 'boolean',
        'show_on_digital_menu' => 'boolean',
        'show_on_kiosk' => 'boolean',
        'is_active' => 'boolean',
    ];

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
            && $this->pricing_rule_type === self::PRICING_RULE_BUY_X_GET_Y_DISCOUNT
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
            'max_applications_per_order' => filled($config['max_applications_per_order'] ?? null)
                ? max(1, min(99, (int) $config['max_applications_per_order']))
                : null,
            'apply_to_addons' => false,
            'reward_scope' => 'same_product',
        ];
    }

    public function pricingRuleLabel(): ?string
    {
        if (! $this->hasAutomaticPricingRule()) {
            return null;
        }

        $rule = $this->normalizedPricingRule();

        return "Compra {$rule['buy_quantity']} y recibe {$rule['reward_quantity']} al {$rule['reward_discount_percentage']}%";
    }

    public function pricingRuleShortLabel(): ?string
    {
        if (! $this->hasAutomaticPricingRule()) {
            return null;
        }

        $rule = $this->normalizedPricingRule();

        return $rule['buy_quantity'] === 1 && $rule['reward_quantity'] === 1
            ? "2.º al {$rule['reward_discount_percentage']}%"
            : "Compra {$rule['buy_quantity']} + {$rule['reward_quantity']} al {$rule['reward_discount_percentage']}%";
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
            ->where('pricing_rule_type', self::PRICING_RULE_BUY_X_GET_Y_DISCOUNT)
            ->whereNotNull('primary_product_id')
            ->where($column, true)
            ->when($fulfillment, fn (Builder $available) => $available->where(fn (Builder $modes) => $modes
                ->whereNull('fulfillment_modes')->orWhereJsonLength('fulfillment_modes', 0)
                ->orWhereJsonContains('fulfillment_modes', $fulfillment)))
            ->whereDate('starts_on', '<=', $at->toDateString())
            ->where(fn (Builder $dates) => $dates->whereNull('ends_on')->orWhereDate('ends_on', '>=', $at->toDateString()))
            ->where(fn (Builder $days) => $days->whereNull('weekdays')->orWhereJsonLength('weekdays', 0)->orWhereJsonContains('weekdays', $weekday));
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
            ->when(in_array($channel, ['pos', 'kiosk'], true), fn (Builder $available) => $available->where('presentation_type', '!=', 'new'))
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
            ->where(fn (Builder $days) => $days
                ->whereNull('weekdays')
                ->orWhereJsonLength('weekdays', 0)
                ->orWhereJsonContains('weekdays', $weekday));
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

        $weekdays = array_map('intval', $this->weekdays ?? []);

        return $weekdays === [] || in_array($at->dayOfWeekIso, $weekdays, true);
    }

    public function presentationLabel(): string
    {
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
}
