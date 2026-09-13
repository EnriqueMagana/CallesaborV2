<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class BusinessTime
{
    public static function timezone(): string
    {
        return (string) config('app.business_timezone', 'America/Mexico_City');
    }

    public static function now(): Carbon
    {
        return now(self::timezone());
    }

    public static function inTimezone(CarbonInterface $dateTime): CarbonInterface
    {
        return $dateTime->copy()->setTimezone(self::timezone());
    }

    public static function format(?CarbonInterface $dateTime, string $format = 'd/m/Y g:i A', string $fallback = '—'): string
    {
        return $dateTime
            ? self::inTimezone($dateTime)->format($format)
            : $fallback;
    }

    /**
     * Convert a business-local calendar range to storage-timezone boundaries.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function dateRange(string $from, string $to): array
    {
        return [
            Carbon::parse($from, self::timezone())->startOfDay()->setTimezone(config('app.timezone', 'UTC')),
            Carbon::parse($to, self::timezone())->endOfDay()->setTimezone(config('app.timezone', 'UTC')),
        ];
    }

    /**
     * Return business-local day boundaries converted to the storage timezone.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function periodBounds(string $period): array
    {
        $today = self::now();
        $from = match ($period) {
            'today' => $today->copy()->startOfDay(),
            '30' => $today->copy()->subDays(29)->startOfDay(),
            default => $today->copy()->subDays(6)->startOfDay(),
        };

        return [
            $from->setTimezone(config('app.timezone', 'UTC')),
            $today->copy()->endOfDay()->setTimezone(config('app.timezone', 'UTC')),
        ];
    }
}
