<?php

namespace App\Livewire\Admin;

use App\Models\BusinessSetting;
use App\Models\DigitalMenuSetting;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

#[Layout('layouts.app')]
class DigitalMenuManager extends Component
{
    use WithFileUploads;

    public const MAX_BANNERS = 8;

    public const MAX_FEATURED = 8;

    public const MAX_GALLERY_IMAGES = 24;

    public string $activeSection = 'overview';

    public string $primaryColor = '#15803d';

    public bool $showBanners = true;

    public bool $autoplayBanners = true;

    public int $bannerIntervalSeconds = 5;

    public array $bannerPaths = [];

    public array $bannerUploads = [];

    public array $bannerUploadAlts = [];

    public bool $showFeatured = true;

    public array $featuredProductIds = [];

    public bool $showCategories = true;

    public string $categoryStyle = 'cards';

    public bool $showGallery = true;

    public array $galleryPaths = [];

    public array $galleryUploads = [];

    public array $galleryUploadCaptions = [];

    public array $removedMediaPaths = [];

    public function mount(): void
    {
        $this->authorizeAccess();
        $this->loadSettings();
    }

    public function setSection(string $section): void
    {
        abort_unless(in_array($section, ['overview', 'banners', 'featured', 'categories', 'gallery'], true), 404);
        $this->activeSection = $section;
    }

    public function save(): void
    {
        $this->authorizeAccess();
        $this->resetErrorBag();

        try {
            $this->validate([
                'primaryColor' => [
                    'required',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                    function (string $attribute, mixed $value, \Closure $fail): void {
                        if (is_string($value) && ! $this->hasReadableWhiteContrast($value)) {
                            $fail('El color debe ser suficientemente oscuro para mantener un contraste accesible.');
                        }
                    },
                ],
                'showBanners' => 'boolean',
                'autoplayBanners' => 'boolean',
                'bannerIntervalSeconds' => 'integer|min:3|max:12',
                'bannerPaths' => 'array',
                'bannerPaths.*.path' => 'required|string',
                'bannerPaths.*.alt' => 'nullable|string|max:120',
                'bannerUploads' => 'array',
                'bannerUploads.*' => 'image|mimes:jpg,jpeg,png,webp|max:6144',
                'bannerUploadAlts' => 'array',
                'bannerUploadAlts.*' => 'nullable|string|max:120',
                'showFeatured' => 'boolean',
                'featuredProductIds' => 'array|max:'.self::MAX_FEATURED,
                'featuredProductIds.*' => 'integer|distinct|exists:products,id',
                'showCategories' => 'boolean',
                'categoryStyle' => 'required|in:cards,circles',
                'showGallery' => 'boolean',
                'galleryPaths' => 'array',
                'galleryPaths.*.path' => 'required|string',
                'galleryPaths.*.caption' => 'nullable|string|max:120',
                'galleryUploads' => 'array',
                'galleryUploads.*' => 'image|mimes:jpg,jpeg,png,webp|max:6144',
                'galleryUploadCaptions' => 'array',
                'galleryUploadCaptions.*' => 'nullable|string|max:120',
            ], $this->validationMessages());
        } catch (ValidationException $exception) {
            $this->activeSection = $this->sectionForErrors(array_keys($exception->errors()));
            $this->notify('error', 'No se pudo guardar', 'Revisa los campos marcados en la sección abierta.');

            throw $exception;
        }

        if (count($this->bannerPaths) + count($this->bannerUploads) > self::MAX_BANNERS) {
            $this->reportLimitError('bannerUploads', 'banners', 'Puedes publicar un máximo de '.self::MAX_BANNERS.' banners.');

            return;
        }

        if (count($this->galleryPaths) + count($this->galleryUploads) > self::MAX_GALLERY_IMAGES) {
            $this->reportLimitError('galleryUploads', 'gallery', 'La galería admite un máximo de '.self::MAX_GALLERY_IMAGES.' imágenes.');

            return;
        }

        $newMediaPaths = [];

        try {
            $bannerPaths = $this->storeMediaUploads($this->bannerPaths, $this->bannerUploads, $this->bannerUploadAlts, 'business/digital-menu/banners', 'alt', $newMediaPaths);
            $galleryPaths = $this->storeMediaUploads($this->galleryPaths, $this->galleryUploads, $this->galleryUploadCaptions, 'business/digital-menu/gallery', 'caption', $newMediaPaths);
            $featuredIds = array_values(array_map('intval', $this->featuredProductIds));

            DB::transaction(function () use ($bannerPaths, $galleryPaths, $featuredIds): void {
                DigitalMenuSetting::current()->update([
                    'primary_color' => strtolower($this->primaryColor),
                    'show_banners' => $this->showBanners,
                    'autoplay_banners' => $this->autoplayBanners,
                    'banner_interval_seconds' => $this->bannerIntervalSeconds,
                    'banner_paths' => $bannerPaths,
                    'show_featured' => $this->showFeatured,
                    'featured_product_ids' => $featuredIds,
                    'show_categories' => $this->showCategories,
                    'category_style' => $this->categoryStyle,
                    'show_gallery' => $this->showGallery,
                    'gallery_paths' => $galleryPaths,
                    'updated_by' => auth()->id(),
                ]);

                BusinessSetting::current()->update([
                    'primary_color' => strtolower($this->primaryColor),
                    'banner_path' => $bannerPaths[0]['path'] ?? null,
                    'featured_product_ids' => $featuredIds,
                    'gallery_paths' => $galleryPaths,
                    'updated_by' => auth()->id(),
                ]);
            });
        } catch (Throwable $exception) {
            $this->discardNewMedia($newMediaPaths);
            Log::error('No fue posible publicar el menú digital.', [
                'user_id' => auth()->id(),
                'exception' => $exception,
            ]);
            $this->addError('save', 'No fue posible guardar los cambios. Verifica el almacenamiento del servidor e inténtalo de nuevo.');
            $this->notify('error', 'Error al publicar', 'Los cambios no se guardaron. El equipo técnico puede revisar el registro del servidor.');

            return;
        }

        $protectedPaths = collect($bannerPaths)->concat($galleryPaths)->pluck('path')->filter()->all();
        $obsoletePaths = collect($this->removedMediaPaths)
            ->unique()
            ->reject(fn (string $path): bool => in_array($path, $protectedPaths, true))
            ->values()
            ->all();

        try {
            Storage::disk('public')->delete($obsoletePaths);
        } catch (Throwable $exception) {
            Log::warning('El menú digital se publicó, pero no se pudieron limpiar archivos anteriores.', [
                'user_id' => auth()->id(),
                'paths' => $obsoletePaths,
                'exception' => $exception,
            ]);
        }

        $this->bannerUploads = [];
        $this->bannerUploadAlts = [];
        $this->galleryUploads = [];
        $this->galleryUploadCaptions = [];
        $this->removedMediaPaths = [];
        $this->loadSettings();

        $this->notify('success', 'Menú publicado', 'Las imágenes y la configuración ya están visibles para tus clientes.');
    }

    public function toggleFeaturedProduct(int $productId): void
    {
        $this->authorizeAccess();
        abort_unless(Product::query()->whereKey($productId)->where('is_active', true)->exists(), 404);

        $ids = array_values(array_map('intval', $this->featuredProductIds));
        $position = array_search($productId, $ids, true);

        if ($position !== false) {
            array_splice($ids, $position, 1);
        } elseif (count($ids) < self::MAX_FEATURED) {
            $ids[] = $productId;
        } else {
            $this->addError('featuredProductIds', 'Solo puedes ordenar '.self::MAX_FEATURED.' favoritos.');
        }

        $this->featuredProductIds = $ids;
    }

    public function moveFeatured(int $index, int $direction): void
    {
        $this->swapItems($this->featuredProductIds, $index, $direction);
    }

    public function moveBanner(int $index, int $direction): void
    {
        $this->swapItems($this->bannerPaths, $index, $direction);
    }

    public function moveGalleryImage(int $index, int $direction): void
    {
        $this->swapItems($this->galleryPaths, $index, $direction);
    }

    public function removeBanner(int $index): void
    {
        $this->removeMediaItem($this->bannerPaths, $index);
    }

    public function removeGalleryImage(int $index): void
    {
        $this->removeMediaItem($this->galleryPaths, $index);
    }

    public function removePendingBanner(int $index): void
    {
        $this->removePendingMedia($this->bannerUploads, $this->bannerUploadAlts, $index);
    }

    public function removePendingGalleryImage(int $index): void
    {
        $this->removePendingMedia($this->galleryUploads, $this->galleryUploadCaptions, $index);
    }

    #[Computed]
    public function availableProducts(): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedProducts(): Collection
    {
        $products = $this->availableProducts->keyBy('id');

        return collect($this->featuredProductIds)
            ->map(fn (int|string $id) => $products->get((int) $id))
            ->filter()
            ->values();
    }

    #[Computed]
    public function maxBanners(): int
    {
        return self::MAX_BANNERS;
    }

    #[Computed]
    public function maxFeatured(): int
    {
        return self::MAX_FEATURED;
    }

    #[Computed]
    public function maxGalleryImages(): int
    {
        return self::MAX_GALLERY_IMAGES;
    }

    public function render()
    {
        return view('livewire.admin.digital-menu-manager');
    }

    private function loadSettings(): void
    {
        $setting = DigitalMenuSetting::current();
        $this->primaryColor = $setting->primary_color ?: '#15803d';
        $this->showBanners = $setting->show_banners;
        $this->autoplayBanners = $setting->autoplay_banners;
        $this->bannerIntervalSeconds = (int) $setting->banner_interval_seconds;
        $this->bannerPaths = $setting->bannerItems();
        $this->showFeatured = $setting->show_featured;
        $this->featuredProductIds = array_values(array_map('intval', $setting->featured_product_ids ?? []));
        $this->showCategories = $setting->show_categories;
        $this->categoryStyle = $setting->category_style ?: 'cards';
        $this->showGallery = $setting->show_gallery;
        $this->galleryPaths = $setting->galleryItems();
    }

    private function storeMediaUploads(array $current, array $uploads, array $texts, string $directory, string $textKey, array &$newMediaPaths): array
    {
        $items = array_values($current);

        foreach ($uploads as $index => $upload) {
            $path = $upload->store($directory, 'public');

            if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
                throw new RuntimeException("No se pudo confirmar la imagen almacenada en {$directory}.");
            }

            $newMediaPaths[] = $path;
            $items[] = [
                'path' => $path,
                $textKey => trim((string) ($texts[$index] ?? '')),
            ];
        }

        return $items;
    }

    private function swapItems(array &$items, int $index, int $direction): void
    {
        $this->authorizeAccess();
        abort_unless(in_array($direction, [-1, 1], true), 404);
        $target = $index + $direction;
        abort_unless(isset($items[$index], $items[$target]), 404);
        [$items[$index], $items[$target]] = [$items[$target], $items[$index]];
        $items = array_values($items);
    }

    private function removeMediaItem(array &$items, int $index): void
    {
        $this->authorizeAccess();
        abort_unless(isset($items[$index]), 404);
        $path = $items[$index]['path'] ?? null;
        if (is_string($path)) {
            $this->removedMediaPaths[] = $path;
        }
        array_splice($items, $index, 1);
        $items = array_values($items);
    }

    private function removePendingMedia(array &$uploads, array &$texts, int $index): void
    {
        $this->authorizeAccess();
        abort_unless(isset($uploads[$index]), 404);
        array_splice($uploads, $index, 1);
        array_splice($texts, $index, 1);
        $uploads = array_values($uploads);
        $texts = array_values($texts);
    }

    private function hasReadableWhiteContrast(string $hex): bool
    {
        $channels = array_map(
            fn (string $channel): float => hexdec($channel) / 255,
            str_split(ltrim($hex, '#'), 2),
        );
        $linear = array_map(
            fn (float $channel): float => $channel <= 0.04045
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4,
            $channels,
        );
        $luminance = (0.2126 * $linear[0]) + (0.7152 * $linear[1]) + (0.0722 * $linear[2]);

        return 1.05 / ($luminance + 0.05) >= 4.5;
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('gestionar menu digital'), 403);
    }

    private function validationMessages(): array
    {
        return [
            'bannerUploads.*.image' => 'Cada banner debe ser una imagen válida.',
            'bannerUploads.*.mimes' => 'Los banners deben ser JPG, PNG o WebP.',
            'bannerUploads.*.max' => 'Cada banner puede pesar máximo 6 MB.',
            'galleryUploads.*.image' => 'Cada archivo de galería debe ser una imagen válida.',
            'galleryUploads.*.mimes' => 'Las imágenes de galería deben ser JPG, PNG o WebP.',
            'galleryUploads.*.max' => 'Cada imagen de galería puede pesar máximo 6 MB.',
        ];
    }

    private function sectionForErrors(array $fields): string
    {
        $field = (string) ($fields[0] ?? '');

        return match (true) {
            str_starts_with($field, 'banner') => 'banners',
            str_starts_with($field, 'featured') => 'featured',
            str_starts_with($field, 'category'), str_starts_with($field, 'showCategories') => 'categories',
            str_starts_with($field, 'gallery'), str_starts_with($field, 'showGallery') => 'gallery',
            default => 'overview',
        };
    }

    private function reportLimitError(string $field, string $section, string $message): void
    {
        $this->activeSection = $section;
        $this->addError($field, $message);
        $this->notify('error', 'Límite de imágenes', $message);
    }

    private function notify(string $type, string $title, string $message): void
    {
        $this->dispatch('notify', type: $type, title: $title, message: $message);
    }

    private function discardNewMedia(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        try {
            Storage::disk('public')->delete($paths);
        } catch (Throwable $cleanupException) {
            Log::warning('No fue posible limpiar archivos de un guardado fallido del menú digital.', [
                'paths' => $paths,
                'exception' => $cleanupException,
            ]);
        }
    }
}
