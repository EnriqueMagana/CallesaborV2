<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigitalMenuSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'show_banners' => 'boolean',
        'autoplay_banners' => 'boolean',
        'banner_paths' => 'array',
        'show_featured' => 'boolean',
        'featured_product_ids' => 'array',
        'show_categories' => 'boolean',
        'show_gallery' => 'boolean',
        'gallery_paths' => 'array',
    ];

    public static function current(): self
    {
        $business = BusinessSetting::current();

        return static::query()->firstOrCreate([], [
            'primary_color' => $business->primary_color ?: '#15803d',
            'banner_paths' => $business->banner_path
                ? [['path' => $business->banner_path, 'alt' => '']]
                : [],
            'featured_product_ids' => $business->featured_product_ids ?? [],
            'gallery_paths' => $business->galleryItems(),
        ]);
    }

    public function bannerItems(): array
    {
        return $this->normalizeMediaItems($this->banner_paths, 'alt');
    }

    public function galleryItems(): array
    {
        return $this->normalizeMediaItems($this->gallery_paths, 'caption');
    }

    private function normalizeMediaItems(?array $items, string $textKey): array
    {
        return collect($items ?? [])
            ->map(function (mixed $item) use ($textKey): ?array {
                if (is_string($item)) {
                    return ['path' => $item, $textKey => ''];
                }

                if (! is_array($item) || ! is_string($item['path'] ?? null)) {
                    return null;
                }

                return [
                    'path' => $item['path'],
                    $textKey => trim((string) ($item[$textKey] ?? '')),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
