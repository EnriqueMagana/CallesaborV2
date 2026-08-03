<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Mesa;
use App\Models\Reservation;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ReservationAvailabilityService
{
    public const OCCUPANCY_MINUTES = 90;

    public function forDates(BusinessSetting $business, array $dates, bool $lockReservations = false): array
    {
        $dates = collect($dates)->filter()->unique()->sort()->values();
        if ($dates->isEmpty()) {
            return [];
        }

        $tableCapacities = Mesa::query()
            ->where('status', '!=', 'bloqueada')
            ->pluck('capacity')
            ->map(fn ($capacity) => max(1, (int) $capacity))
            ->sortDesc()
            ->values();

        $rangeStart = Carbon::parse($dates->first())->startOfDay()->subMinutes(self::OCCUPANCY_MINUTES);
        $rangeEnd = Carbon::parse($dates->last())->endOfDay()->addMinutes(self::OCCUPANCY_MINUTES);
        $reservationQuery = Reservation::query()
            ->whereIn('status', ['pendiente', 'confirmada'])
            ->where('is_waitlist', false)
            ->whereBetween('reserved_at', [$rangeStart, $rangeEnd]);

        if ($lockReservations) {
            $reservationQuery->lockForUpdate();
        }

        $reservations = $reservationQuery->get(['reserved_at', 'guests']);

        return $dates->mapWithKeys(fn (string $date) => [
            $date => $this->slotsForDate($business, $date, $tableCapacities, $reservations),
        ])->all();
    }

    public function forMoment(BusinessSetting $business, CarbonInterface $moment, int $guests, bool $lockReservations = false): array
    {
        $slots = $this->forDates($business, [$moment->format('Y-m-d')], $lockReservations)[$moment->format('Y-m-d')] ?? [];
        $availability = collect($slots)->firstWhere('time', $moment->format('H:i'));

        if (! $availability) {
            return ['can_fit' => false, 'enforced' => false, 'remaining_seats' => null, 'remaining_tables' => null];
        }

        $tablesNeeded = $this->tablesNeeded($availability['table_capacities'] ?? [], $guests);
        $availability['can_fit'] = ! $availability['enforced'] || (
            $availability['remaining_seats'] >= $guests
            && $availability['remaining_tables'] >= $tablesNeeded
        );
        $availability['tables_needed'] = $tablesNeeded;

        return $availability;
    }

    private function slotsForDate(BusinessSetting $business, string $date, Collection $tableCapacities, Collection $reservations): array
    {
        $enforced = $tableCapacities->isNotEmpty();
        $totalSeats = $tableCapacities->sum();
        $totalTables = $tableCapacities->count();

        return collect($business->reservationSlots($date))->map(function (string $time) use ($date, $tableCapacities, $reservations, $enforced, $totalSeats, $totalTables): array {
            $startsAt = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$time);
            $endsAt = $startsAt->copy()->addMinutes(self::OCCUPANCY_MINUTES);
            $overlapping = $reservations->filter(function (Reservation $reservation) use ($startsAt, $endsAt): bool {
                $reservationEndsAt = $reservation->reserved_at->copy()->addMinutes(self::OCCUPANCY_MINUTES);

                return $reservation->reserved_at->lt($endsAt) && $reservationEndsAt->gt($startsAt);
            });
            $usedSeats = (int) $overlapping->sum('guests');
            $usedTables = (int) $overlapping->sum(fn (Reservation $reservation) => $this->tablesNeeded($tableCapacities->all(), $reservation->guests));

            return [
                'time' => $time,
                'label' => $startsAt->format('g:i A'),
                'enforced' => $enforced,
                'total_seats' => $enforced ? $totalSeats : null,
                'total_tables' => $enforced ? $totalTables : null,
                'remaining_seats' => $enforced ? max(0, $totalSeats - $usedSeats) : null,
                'remaining_tables' => $enforced ? max(0, $totalTables - $usedTables) : null,
                'max_table_capacity' => $enforced ? (int) $tableCapacities->max() : null,
                'table_capacities' => $tableCapacities->all(),
            ];
        })->all();
    }

    private function tablesNeeded(array $capacities, int $guests): int
    {
        if ($capacities === []) {
            return 0;
        }

        $remaining = max(1, $guests);
        foreach ($capacities as $index => $capacity) {
            $remaining -= max(1, (int) $capacity);
            if ($remaining <= 0) {
                return $index + 1;
            }
        }

        return count($capacities) + 1;
    }
}
