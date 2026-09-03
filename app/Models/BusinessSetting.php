<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'business_hours' => 'array',
        'gallery_paths' => 'array',
        'featured_product_ids' => 'array',
        'delivery_management_enabled' => 'boolean',
        'ticket_font_size' => 'integer',
    ];

    public const DEFAULT_HOURS = [
        ['key' => 'monday', 'label' => 'Lunes', 'enabled' => true, 'opens' => '09:00', 'closes' => '18:00'],
        ['key' => 'tuesday', 'label' => 'Martes', 'enabled' => true, 'opens' => '09:00', 'closes' => '18:00'],
        ['key' => 'wednesday', 'label' => 'Miércoles', 'enabled' => true, 'opens' => '09:00', 'closes' => '18:00'],
        ['key' => 'thursday', 'label' => 'Jueves', 'enabled' => true, 'opens' => '09:00', 'closes' => '18:00'],
        ['key' => 'friday', 'label' => 'Viernes', 'enabled' => true, 'opens' => '09:00', 'closes' => '18:00'],
        ['key' => 'saturday', 'label' => 'Sábado', 'enabled' => true, 'opens' => '09:00', 'closes' => '18:00'],
        ['key' => 'sunday', 'label' => 'Domingo', 'enabled' => false, 'opens' => '09:00', 'closes' => '18:00'],
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'business_name' => config('app.name', 'Calle Sabor'),
            'platform_name' => config('app.name', 'Calle Sabor'),
            'business_hours' => self::DEFAULT_HOURS,
        ]);
    }

    public function getFullAddressAttribute(): string
    {
        if (substr_count((string) $this->address, ',') >= 2) {
            return trim($this->address);
        }

        return collect([$this->address, $this->city, $this->state, $this->postal_code])
            ->filter()->implode(', ');
    }

    public function getMapLinkAttribute(): ?string
    {
        if ($this->maps_url) {
            return $this->maps_url;
        }

        if (! $this->full_address) {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($this->full_address);
    }

    public function galleryItems(): array
    {
        return collect($this->gallery_paths ?? [])
            ->map(function (mixed $item): ?array {
                if (is_string($item)) {
                    return ['path' => $item, 'caption' => ''];
                }

                if (! is_array($item) || ! is_string($item['path'] ?? null)) {
                    return null;
                }

                return [
                    'path' => $item['path'],
                    'caption' => is_string($item['caption'] ?? null) ? trim($item['caption']) : '',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function openingStatus(?CarbonInterface $moment = null): array
    {
        $now = $moment?->copy() ?? now();
        $hours = collect($this->weeklySchedule($now))->keyBy('key');

        for ($offset = -1; $offset <= 7; $offset++) {
            $date = $now->copy()->startOfDay()->addDays($offset);
            $day = $hours->get(strtolower($date->englishDayOfWeek));

            if (! ($day['enabled'] ?? false)) {
                continue;
            }

            $opens = $date->copy()->setTimeFromTimeString($day['opens']);
            $closes = $date->copy()->setTimeFromTimeString($day['closes']);

            if ($closes->lessThanOrEqualTo($opens)) {
                $closes->addDay();
            }

            if ($now->betweenIncluded($opens, $closes)) {
                return [
                    'is_open' => true,
                    'label' => 'Abierto ahora',
                    'detail' => 'Cierra a las '.$closes->format('H:i'),
                    'opens_at' => $opens->format('H:i'),
                    'closes_at' => $closes->format('H:i'),
                    'day_label' => $day['label'],
                    'closes_next_day' => ! $opens->isSameDay($closes),
                ];
            }

            if ($opens->isAfter($now)) {
                return [
                    'is_open' => false,
                    'label' => 'Cerrado ahora',
                    'detail' => ($opens->isToday() ? 'Abre hoy' : 'Abre '.$day['label']).' a las '.$opens->format('H:i'),
                    'opens_at' => $opens->format('H:i'),
                    'closes_at' => $closes->format('H:i'),
                    'day_label' => $day['label'],
                    'closes_next_day' => ! $opens->isSameDay($closes),
                ];
            }
        }

        return [
            'is_open' => false,
            'label' => 'Horario no disponible',
            'detail' => 'Consulta nuestros horarios',
            'opens_at' => null,
            'closes_at' => null,
            'day_label' => null,
            'closes_next_day' => false,
        ];
    }

    /**
     * Return the administrator-configured schedule in a stable Monday-Sunday order.
     */
    public function weeklySchedule(?CarbonInterface $moment = null): array
    {
        $todayKey = strtolower(($moment?->copy() ?? now())->englishDayOfWeek);
        $configured = collect($this->business_hours ?: self::DEFAULT_HOURS)->keyBy('key');

        return collect(self::DEFAULT_HOURS)
            ->map(function (array $default) use ($configured, $todayKey): array {
                $saved = $configured->get($default['key'], []);
                $day = array_merge($default, is_array($saved) ? $saved : []);
                $day['enabled'] = (bool) ($day['enabled'] ?? false);
                $day['is_today'] = $day['key'] === $todayKey;
                $day['is_overnight'] = $day['enabled'] && $day['closes'] <= $day['opens'];

                return $day;
            })
            ->values()
            ->all();
    }

    public function reservationSlots(CarbonInterface|string $date, int $intervalMinutes = 30): array
    {
        $target = $date instanceof CarbonInterface ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();

        if ($target->isBefore(now()->startOfDay()) || $target->isAfter(now()->addDays(90)->endOfDay())) {
            return [];
        }

        $hours = collect($this->business_hours ?: self::DEFAULT_HOURS)->keyBy('key');
        $slots = collect();

        foreach ([$target->copy()->subDay(), $target->copy()] as $serviceDate) {
            $day = $hours->get(strtolower($serviceDate->englishDayOfWeek));

            if (! ($day['enabled'] ?? false)) {
                continue;
            }

            $opens = $serviceDate->copy()->setTimeFromTimeString($day['opens']);
            $closes = $serviceDate->copy()->setTimeFromTimeString($day['closes']);

            if ($closes->lessThanOrEqualTo($opens)) {
                $closes->addDay();
            }

            for ($slot = $opens->copy(); $slot->lessThan($closes); $slot->addMinutes($intervalMinutes)) {
                if ($slot->isSameDay($target) && $slot->greaterThanOrEqualTo(now()->addMinutes(30))) {
                    $slots->push($slot->format('H:i'));
                }
            }
        }

        return $slots->unique()->sort()->values()->all();
    }

    public function acceptsReservationAt(CarbonInterface $moment): bool
    {
        return in_array($moment->format('H:i'), $this->reservationSlots($moment), true);
    }
}
