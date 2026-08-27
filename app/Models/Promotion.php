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

    protected $fillable = [
        'name', 'description', 'presentation_type', 'primary_product_id', 'short_description', 'image', 'price',
        'discount_percentage', 'starts_on', 'ends_on', 'weekdays',
        'show_on_pos', 'show_on_digital_menu', 'is_active', 'created_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_percentage' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'weekdays' => 'array',
        'show_on_pos' => 'boolean',
        'show_on_digital_menu' => 'boolean',
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

    public function scopeAvailable(Builder $query, string $channel, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();
        $column = $channel === 'digital_menu' ? 'show_on_digital_menu' : 'show_on_pos';
        $weekday = $at->dayOfWeekIso;

        return $query
            ->where('is_active', true)
            ->where($column, true)
            ->when($channel === 'pos', fn (Builder $available) => $available->where('presentation_type', '!=', 'new'))
            ->whereDate('starts_on', '<=', $at->toDateString())
            ->where(fn (Builder $dates) => $dates
                ->whereNull('ends_on')
                ->orWhereDate('ends_on', '>=', $at->toDateString()))
            ->where(fn (Builder $days) => $days
                ->whereNull('weekdays')
                ->orWhereJsonLength('weekdays', 0)
                ->orWhereJsonContains('weekdays', $weekday));
    }

    public function isAvailableFor(string $channel, ?CarbonInterface $at = null): bool
    {
        return static::query()->whereKey($this->getKey())->available($channel, $at)->exists();
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
