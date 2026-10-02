<?php

namespace App\Services;

use App\Models\DigitalMenuEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TrafficDiagnosticsService
{
    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $pollingFiles = $this->pollingFiles();
        $digitalMenu = $this->digitalMenuTraffic();
        $pulse = $this->pulseTraffic();
        $log = storage_path('logs/laravel.log');

        return [
            'status' => $pollingFiles === [] && ($pulse['slow_requests_24h'] ?? 0) === 0
                ? 'healthy'
                : 'attention',
            'polling' => [
                'detected' => count($pollingFiles),
                'files' => $pollingFiles,
                'message' => $pollingFiles === []
                    ? 'No se detectó polling de red en las vistas ni en el JavaScript propio.'
                    : 'Se detectaron actualizaciones periódicas que requieren revisión.',
            ],
            'digital_menu' => $digitalMenu,
            'pulse' => $pulse,
            'log' => [
                'size_mb' => is_file($log) ? round(((int) filesize($log)) / 1024 / 1024, 1) : 0,
                'updated_at' => is_file($log) ? date('d/m/Y H:i:s', (int) filemtime($log)) : null,
            ],
            'controls' => [
                'automatic_refresh' => false,
                'analytics_cache_seconds' => DigitalMenuAnalytics::CACHE_SECONDS,
                'analytics_retention_days' => 180,
                'view_limit_per_minute' => 12,
                'click_limit_per_minute' => 60,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function digitalMenuTraffic(): array
    {
        if (! Schema::hasTable('digital_menu_events')) {
            return ['tables_ready' => false];
        }

        $sinceDay = now()->subDay();
        $sinceFiveMinutes = now()->subMinutes(5);
        $totals = DigitalMenuEvent::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS last_24h', [$sinceDay])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS last_5m', [$sinceFiveMinutes])
            ->selectRaw('SUM(CASE WHEN event_type = ? AND created_at >= ? THEN 1 ELSE 0 END) AS views_24h', [DigitalMenuEvent::TYPE_MENU_VIEW, $sinceDay])
            ->selectRaw('SUM(CASE WHEN event_type = ? AND created_at >= ? THEN 1 ELSE 0 END) AS clicks_24h', [DigitalMenuEvent::TYPE_PRODUCT_CLICK, $sinceDay])
            ->first();

        return [
            'tables_ready' => true,
            'total' => (int) ($totals?->total ?? 0),
            'last_24h' => (int) ($totals?->last_24h ?? 0),
            'last_5m' => (int) ($totals?->last_5m ?? 0),
            'views_24h' => (int) ($totals?->views_24h ?? 0),
            'clicks_24h' => (int) ($totals?->clicks_24h ?? 0),
            'latest_at' => DigitalMenuEvent::query()->max('created_at'),
        ];
    }

    /** @return array<string, mixed> */
    private function pulseTraffic(): array
    {
        if (! Schema::hasTable('pulse_entries')) {
            return [
                'tables_ready' => false,
                'slow_requests_24h' => null,
                'slow_queries_24h' => null,
                'peak_request_ms' => null,
                'peak_query_ms' => null,
                'top_slow_requests' => [],
            ];
        }

        $since = now()->subDay()->getTimestamp();
        $base = DB::table('pulse_entries')->where('timestamp', '>=', $since);
        $slowRequests = (clone $base)->where('type', 'slow_request');
        $slowQueries = (clone $base)->where('type', 'slow_query');

        $top = (clone $slowRequests)
            ->select(['key'])
            ->selectRaw('COUNT(*) AS incidents')
            ->selectRaw('MAX(value) AS peak_ms')
            ->groupBy('key')
            ->orderByDesc('peak_ms')
            ->limit(5)
            ->get()
            ->map(function ($row): array {
                try {
                    [$method, $path] = json_decode($row->key, true, flags: JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    $method = '—';
                    $path = $row->key;
                }

                return [
                    'method' => $method,
                    'path' => $path,
                    'incidents' => (int) $row->incidents,
                    'peak_ms' => (int) $row->peak_ms,
                ];
            })
            ->all();

        return [
            'tables_ready' => true,
            'slow_requests_24h' => (clone $slowRequests)->count(),
            'slow_queries_24h' => (clone $slowQueries)->count(),
            'peak_request_ms' => (int) ((clone $slowRequests)->max('value') ?? 0),
            'peak_query_ms' => (int) ((clone $slowQueries)->max('value') ?? 0),
            'top_slow_requests' => $top,
        ];
    }

    /** @return list<string> */
    private function pollingFiles(): array
    {
        $matches = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (str_contains($file->getContents(), 'wire:poll')) {
                $matches[] = 'resources/views/'.$file->getRelativePathname();
            }
        }

        foreach (File::allFiles(public_path('assets/js')) as $file) {
            if (str_ends_with($file->getFilename(), '.min.js')) {
                continue;
            }

            if (preg_match('/setInterval\s*\([\s\S]{0,1200}?(?:fetch\s*\(|axios\.|Livewire\.(?:dispatch|find)|\$wire\.)/i', $file->getContents())) {
                $matches[] = 'public/assets/js/'.$file->getRelativePathname();
            }
        }

        return array_values(array_unique($matches));
    }
}
