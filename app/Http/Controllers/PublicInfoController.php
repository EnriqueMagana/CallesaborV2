<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\DigitalMenuSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PublicInfoController extends Controller
{
    public function hours(): View
    {
        return view('public-menu.hours', $this->sharedData());
    }

    public function gallery(): View
    {
        $data = $this->sharedData();
        abort_unless($data['menuSettings']->show_gallery, 404);

        return view('public-menu.gallery', $data);
    }

    public function contact(): View
    {
        $data = $this->sharedData();
        $data['locationMap'] = $this->locationMap($data['business']);

        return view('public-menu.contact', $data);
    }

    private function locationMap(BusinessSetting $business): array
    {
        $destination = trim($business->full_address);
        $placeQuery = collect([$business->business_name, $destination])
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->implode(', ');
        $coordinates = $this->coordinatesFromMapUrl($business->maps_url);
        $mapTarget = $coordinates ?? $placeQuery;

        return [
            'destination' => $destination,
            'place_url' => $business->map_link,
            'embed_url' => $destination !== ''
                ? 'https://www.google.com/maps?q='.rawurlencode($mapTarget).'&output=embed'
                : null,
            'directions_url' => $destination !== ''
                ? 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($mapTarget).'&travelmode=driving&dir_action=navigate'
                : $business->map_link,
        ];
    }

    private function coordinatesFromMapUrl(?string $mapUrl): ?string
    {
        if (! $mapUrl) {
            return null;
        }

        $matches = [];
        $matched = preg_match('/!3d(-?\d{1,2}(?:\.\d+)?)!4d(-?\d{1,3}(?:\.\d+)?)/', $mapUrl, $matches)
            || preg_match('/@(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)/', $mapUrl, $matches);

        if (! $matched) {
            return null;
        }

        $latitude = (float) $matches[1];
        $longitude = (float) $matches[2];

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        return $matches[1].','.$matches[2];
    }

    private function sharedData(): array
    {
        $business = BusinessSetting::current();
        $menuSettings = DigitalMenuSetting::current();
        $moment = now(config('app.business_timezone', 'America/Mexico_City'));
        $weeklySchedule = collect($business->weeklySchedule($moment))
            ->map(fn (array $day): array => array_merge($day, [
                'opens_label' => $day['enabled'] ? $this->formatTime12($day['opens']) : null,
                'closes_label' => $day['enabled'] ? $this->formatTime12($day['closes']) : null,
            ]))
            ->all();

        return [
            'business' => $business,
            'menuSettings' => $menuSettings,
            'openingStatus' => $business->openingStatus($moment),
            'hoursTimeline' => $this->hoursTimeline($business, $moment),
            'weeklySchedule' => $weeklySchedule,
            'galleryImages' => collect($menuSettings->show_gallery ? $menuSettings->galleryItems() : [])
                ->filter(fn (array $item) => Storage::disk('public')->exists($item['path']))
                ->values(),
        ];
    }

    private function hoursTimeline(BusinessSetting $business, Carbon $now): array
    {
        $schedule = collect($business->weeklySchedule($now))->keyBy('key');
        $windowFor = function (Carbon $date) use ($schedule): ?array {
            $day = $schedule->get(strtolower($date->englishDayOfWeek));

            if (! ($day['enabled'] ?? false)) {
                return null;
            }

            $opens = $date->copy()->startOfDay()->setTimeFromTimeString($day['opens']);
            $closes = $date->copy()->startOfDay()->setTimeFromTimeString($day['closes']);
            if ($closes->lessThanOrEqualTo($opens)) {
                $closes->addDay();
            }

            return compact('day', 'opens', 'closes');
        };

        $activeWindow = collect([$now->copy()->subDay(), $now->copy()])
            ->map($windowFor)
            ->filter()
            ->first(fn (array $window): bool => $now->betweenIncluded($window['opens'], $window['closes']));
        $todayWindow = $windowFor($now->copy());

        if ($activeWindow) {
            $state = 'open';
            $window = $activeWindow;
            $progress = ($now->timestamp - $window['opens']->timestamp)
                / max(1, $window['closes']->timestamp - $window['opens']->timestamp);
            $statusLabel = 'Abierto ahora';
            $statusDetail = 'Quedan '.$this->durationLabel($now->diffInMinutes($window['closes'])).' de servicio.';
        } elseif ($todayWindow && $now->lessThan($todayWindow['opens'])) {
            $state = 'upcoming';
            $window = $todayWindow;
            $progress = 0;
            $statusLabel = 'Abrimos más tarde';
            $statusDetail = 'Abrimos en '.$this->durationLabel($now->diffInMinutes($window['opens'])).'.';
        } elseif ($todayWindow) {
            $state = 'closed';
            $window = $todayWindow;
            $progress = 1;
            $statusLabel = 'Cerrado por hoy';
            $statusDetail = 'La jornada de hoy ha finalizado. Te esperamos en nuestra próxima apertura.';
        } else {
            $state = 'closed-day';
            $window = null;
            $progress = 0;
            $statusLabel = 'Hoy no abrimos';
            $statusDetail = 'Consulta la semana completa para planear tu próxima visita.';
        }

        return [
            'state' => $state,
            'status_label' => $statusLabel,
            'status_detail' => $statusDetail,
            'progress' => round(max(0, min(1, $progress)), 4),
            'timezone' => config('app.business_timezone', 'America/Mexico_City'),
            'business_date' => $now->format('Y-m-d'),
            'now_iso' => $now->toIso8601String(),
            'clock_label' => $this->formatDateTime12($now),
            'date_label' => str($now->locale('es')->translatedFormat('l, j \d\e F'))->ucfirst()->toString(),
            'day_label' => $window['day']['label'] ?? str($now->locale('es')->translatedFormat('l'))->ucfirst()->toString(),
            'day_enabled' => $window !== null,
            'opens_at' => $window ? $window['opens']->format('H:i') : null,
            'closes_at' => $window ? $window['closes']->format('H:i') : null,
            'opens_iso' => $window ? $window['opens']->toIso8601String() : null,
            'closes_iso' => $window ? $window['closes']->toIso8601String() : null,
            'opens_label' => $window ? $this->formatDateTime12($window['opens']) : null,
            'closes_label' => $window ? $this->formatDateTime12($window['closes']) : null,
            'is_overnight' => $window ? ! $window['opens']->isSameDay($window['closes']) : false,
        ];
    }

    private function durationLabel(float|int $minutes): string
    {
        $minutes = max(0, (int) ceil($minutes));
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return collect([
            $hours > 0 ? $hours.' h' : null,
            $remainingMinutes > 0 ? $remainingMinutes.' min' : null,
        ])->filter()->implode(' ') ?: 'menos de 1 min';
    }

    private function formatTime12(string $time): string
    {
        return $this->formatDateTime12(Carbon::createFromFormat('H:i', $time));
    }

    private function formatDateTime12(Carbon $time): string
    {
        return $time->format('g:i').' '.($time->format('A') === 'AM' ? 'a. m.' : 'p. m.');
    }
}
