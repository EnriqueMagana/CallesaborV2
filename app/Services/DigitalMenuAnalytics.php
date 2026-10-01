<?php

namespace App\Services;

use App\Models\DigitalMenuEvent;
use App\Models\Product;
use App\Support\BusinessTime;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class DigitalMenuAnalytics
{
    public function record(string $eventType, string $visitorToken, ?string $sessionToken = null, ?Product $product = null): void
    {
        DigitalMenuEvent::query()->create([
            'event_type' => $eventType,
            'product_id' => $product?->getKey(),
            'visitor_hash' => $this->anonymousHash($visitorToken),
            'session_hash' => $sessionToken ? $this->anonymousHash($sessionToken) : null,
            'occurred_on' => BusinessTime::now()->toDateString(),
        ]);
    }

    /** @return Collection<int, int> */
    public function topProductIds(int $limit = 5): Collection
    {
        return DigitalMenuEvent::query()
            ->where('event_type', DigitalMenuEvent::TYPE_PRODUCT_CLICK)
            ->whereNotNull('product_id')
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->select('product_id')
            ->selectRaw('COUNT(*) AS clicks_count')
            ->selectRaw('MAX(created_at) AS last_clicked_at')
            ->groupBy('product_id')
            ->orderByDesc('clicks_count')
            ->orderByDesc('last_clicked_at')
            ->limit($limit)
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id);
    }

    /** @return array<string, mixed> */
    public function dashboard(int $days): array
    {
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $to = BusinessTime::now()->startOfDay();
        $from = $to->copy()->subDays($days - 1);
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();
        $storageRange = [$fromDate.' 00:00:00', $toDate.' 23:59:59'];

        $base = DigitalMenuEvent::query()->whereBetween('occurred_on', $storageRange);
        $views = (clone $base)->where('event_type', DigitalMenuEvent::TYPE_MENU_VIEW)->count();
        $clicks = (clone $base)->where('event_type', DigitalMenuEvent::TYPE_PRODUCT_CLICK)->count();
        $visitors = (clone $base)->where('event_type', DigitalMenuEvent::TYPE_MENU_VIEW)->distinct()->count('visitor_hash');

        $daily = (clone $base)
            ->select(['occurred_on', 'event_type'])
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(DISTINCT visitor_hash) AS unique_visitors')
            ->groupBy('occurred_on', 'event_type')
            ->get()
            ->groupBy(fn (DigitalMenuEvent $event): string => $event->occurred_on->toDateString());

        $trend = collect(CarbonPeriod::create($from, $to))->map(function ($date) use ($daily): array {
            $rows = $daily->get($date->toDateString(), collect())->keyBy('event_type');
            $view = $rows->get(DigitalMenuEvent::TYPE_MENU_VIEW);
            $click = $rows->get(DigitalMenuEvent::TYPE_PRODUCT_CLICK);

            return [
                'date' => $date->format('d/m'),
                'views' => (int) ($view?->total ?? 0),
                'visitors' => (int) ($view?->unique_visitors ?? 0),
                'clicks' => (int) ($click?->total ?? 0),
            ];
        });

        $products = Product::query()
            ->join('digital_menu_events', 'digital_menu_events.product_id', '=', 'products.id')
            ->where('products.is_active', true)
            ->where('digital_menu_events.event_type', DigitalMenuEvent::TYPE_PRODUCT_CLICK)
            ->whereBetween('digital_menu_events.occurred_on', $storageRange)
            ->select(['products.id', 'products.name', 'products.image'])
            ->selectRaw('COUNT(digital_menu_events.id) AS clicks_count')
            ->selectRaw('COUNT(DISTINCT digital_menu_events.visitor_hash) AS unique_visitors')
            ->groupBy('products.id', 'products.name', 'products.image')
            ->orderByDesc('clicks_count')
            ->orderBy('products.name')
            ->limit(10)
            ->get();

        return [
            'period' => ['days' => $days, 'from' => $fromDate, 'to' => $toDate],
            'totals' => [
                'views' => $views,
                'visitors' => $visitors,
                'clicks' => $clicks,
                'click_rate' => $views > 0 ? round(($clicks / $views) * 100, 1) : 0,
            ],
            'trend' => [
                'labels' => $trend->pluck('date')->all(),
                'views' => $trend->pluck('views')->all(),
                'visitors' => $trend->pluck('visitors')->all(),
                'clicks' => $trend->pluck('clicks')->all(),
            ],
            'products' => [
                'labels' => $products->pluck('name')->all(),
                'clicks' => $products->pluck('clicks_count')->map(fn ($value): int => (int) $value)->all(),
                'items' => $products,
            ],
        ];
    }

    private function anonymousHash(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }
}
