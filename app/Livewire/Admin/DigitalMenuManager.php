<?php

namespace App\Livewire\Admin;

use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\DigitalMenuSetting;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

    public array $removedBannerPaths = [];

    public array $removedGalleryPaths = [];

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

    public function saveSection(): void
    {
        $this->authorizeAccess();
        $this->resetErrorBag();

        match ($this->activeSection) {
            'overview' => $this->saveOverview(),
            'banners' => $this->saveBanners(),
            'featured' => $this->saveFeatured(),
            'categories' => $this->saveCategories(),
            'gallery' => $this->saveGallery(),
            default => abort(404),
        };
    }

    private function saveOverview(): void
    {
        $this->validateSection([
            'primaryColor' => [
                'required',
                'regex:/^#[0-9A-Fa-f]{6}$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && ! $this->hasReadableWhiteContrast($value)) {
                        $fail('Elige un color más oscuro para que el texto blanco pueda leerse correctamente.');
                    }
                },
            ],
        ]);

        if (! $this->persistSection('Configuración general', function (): void {
            DigitalMenuSetting::current()->update([
                'primary_color' => strtolower($this->primaryColor),
                'updated_by' => auth()->id(),
            ]);
            BusinessSetting::current()->update([
                'primary_color' => strtolower($this->primaryColor),
                'updated_by' => auth()->id(),
            ]);
        })) {
            return;
        }

        $this->loadSettings('overview');
        $this->notify('success', 'Configuración guardada', 'El color principal del menú quedó actualizado.');
    }

    private function saveBanners(): void
    {
        $this->validateSection([
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
        ]);

        $count = count($this->bannerPaths) + count($this->bannerUploads);
        if ($count > self::MAX_BANNERS) {
            $this->rejectSection('bannerUploads', 'Puedes publicar un máximo de '.self::MAX_BANNERS.' banners. Quita una imagen y vuelve a guardar.');

            return;
        }
        if ($this->showBanners && $count === 0) {
            $this->rejectSection('showBanners', 'Para activar los banners, agrega al menos una imagen. También puedes desactivar el carrusel.');

            return;
        }
        if ($this->showBanners && $this->containsMissingMedia($this->bannerPaths)) {
            $this->rejectSection('bannerPaths', 'Una imagen guardada ya no está disponible en el servidor. Quítala o vuelve a subirla antes de publicar el carrusel.');

            return;
        }

        $newMediaPaths = [];
        try {
            $bannerPaths = $this->storeMediaUploads($this->bannerPaths, $this->bannerUploads, $this->bannerUploadAlts, 'business/digital-menu/banners', 'alt', $newMediaPaths);
            DB::transaction(function () use ($bannerPaths): void {
                DigitalMenuSetting::current()->update([
                    'show_banners' => $this->showBanners,
                    'autoplay_banners' => $this->autoplayBanners,
                    'banner_interval_seconds' => $this->bannerIntervalSeconds,
                    'banner_paths' => $bannerPaths,
                    'updated_by' => auth()->id(),
                ]);
                BusinessSetting::current()->update([
                    'banner_path' => $bannerPaths[0]['path'] ?? null,
                    'updated_by' => auth()->id(),
                ]);
            });
        } catch (Throwable $exception) {
            $this->handlePersistenceFailure('Banners', $exception, $newMediaPaths);

            return;
        }

        $this->deleteObsoleteMedia($this->removedBannerPaths, $bannerPaths, 'banners');
        $this->bannerUploads = [];
        $this->bannerUploadAlts = [];
        $this->removedBannerPaths = [];
        $this->loadSettings('banners');
        $this->notify('success', 'Banners guardados', $this->showBanners ? 'El carrusel ya está publicado con los cambios.' : 'El carrusel quedó oculto; sus imágenes se conservaron.');
    }

    private function saveFeatured(): void
    {
        $this->validateSection([
            'showFeatured' => 'boolean',
            'featuredProductIds' => 'array|max:'.self::MAX_FEATURED,
            'featuredProductIds.*' => [
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ]);

        if ($this->showFeatured && $this->featuredProductIds === []) {
            $this->rejectSection('showFeatured', 'Para mostrar favoritos, selecciona al menos un producto. También puedes dejar esta sección desactivada.');

            return;
        }

        $featuredIds = array_values(array_map('intval', $this->featuredProductIds));
        if (! $this->persistSection('Favoritos', function () use ($featuredIds): void {
            DigitalMenuSetting::current()->update([
                'show_featured' => $this->showFeatured,
                'featured_product_ids' => $featuredIds,
                'updated_by' => auth()->id(),
            ]);
            BusinessSetting::current()->update([
                'featured_product_ids' => $featuredIds,
                'updated_by' => auth()->id(),
            ]);
        })) {
            return;
        }

        $this->loadSettings('featured');
        $this->notify('success', 'Favoritos guardados', $this->showFeatured ? 'El orden de productos ya está publicado.' : 'La sección de favoritos quedó oculta.');
    }

    private function saveCategories(): void
    {
        $this->validateSection([
            'showCategories' => 'boolean',
            'categoryStyle' => 'required|in:cards,circles',
        ]);

        $hasContent = Category::query()
            ->where('is_active', true)
            ->whereHas('products', fn ($query) => $query->where('is_active', true))
            ->exists();
        if ($this->showCategories && ! $hasContent) {
            $this->rejectSection('showCategories', 'Para mostrar categorías, primero crea una categoría activa con al menos un producto activo.');

            return;
        }

        if (! $this->persistSection('Categorías', function (): void {
            DigitalMenuSetting::current()->update([
                'show_categories' => $this->showCategories,
                'category_style' => $this->categoryStyle,
                'updated_by' => auth()->id(),
            ]);
        })) {
            return;
        }

        $this->loadSettings('categories');
        $this->notify('success', 'Categorías guardadas', $this->showCategories ? 'La navegación por categorías ya está publicada.' : 'La navegación por categorías quedó oculta.');
    }

    private function saveGallery(): void
    {
        $this->validateSection([
            'showGallery' => 'boolean',
            'galleryPaths' => 'array',
            'galleryPaths.*.path' => 'required|string',
            'galleryPaths.*.caption' => 'nullable|string|max:120',
            'galleryUploads' => 'array',
            'galleryUploads.*' => 'image|mimes:jpg,jpeg,png,webp|max:6144',
            'galleryUploadCaptions' => 'array',
            'galleryUploadCaptions.*' => 'nullable|string|max:120',
        ]);

        $count = count($this->galleryPaths) + count($this->galleryUploads);
        if ($count > self::MAX_GALLERY_IMAGES) {
            $this->rejectSection('galleryUploads', 'La galería admite un máximo de '.self::MAX_GALLERY_IMAGES.' imágenes. Quita una fotografía y vuelve a guardar.');

            return;
        }
        if ($this->showGallery && $count === 0) {
            $this->rejectSection('showGallery', 'Para activar la galería, agrega al menos una fotografía. También puedes dejarla desactivada.');

            return;
        }
        if ($this->showGallery && $this->containsMissingMedia($this->galleryPaths)) {
            $this->rejectSection('galleryPaths', 'Una fotografía guardada ya no está disponible en el servidor. Quítala o vuelve a subirla antes de publicar la galería.');

            return;
        }

        $newMediaPaths = [];
        try {
            $galleryPaths = $this->storeMediaUploads($this->galleryPaths, $this->galleryUploads, $this->galleryUploadCaptions, 'business/digital-menu/gallery', 'caption', $newMediaPaths);
            DB::transaction(function () use ($galleryPaths): void {
                DigitalMenuSetting::current()->update([
                    'show_gallery' => $this->showGallery,
                    'gallery_paths' => $galleryPaths,
                    'updated_by' => auth()->id(),
                ]);
                BusinessSetting::current()->update([
                    'gallery_paths' => $galleryPaths,
                    'updated_by' => auth()->id(),
                ]);
            });
        } catch (Throwable $exception) {
            $this->handlePersistenceFailure('Galería', $exception, $newMediaPaths);

            return;
        }

        $this->deleteObsoleteMedia($this->removedGalleryPaths, $galleryPaths, 'galería');
        $this->galleryUploads = [];
        $this->galleryUploadCaptions = [];
        $this->removedGalleryPaths = [];
        $this->loadSettings('gallery');
        $this->notify('success', 'Galería guardada', $this->showGallery ? 'Las fotografías ya están publicadas.' : 'La galería quedó oculta; sus fotografías se conservaron.');
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
        $this->removeMediaItem($this->bannerPaths, $this->removedBannerPaths, $index);
    }

    public function removeGalleryImage(int $index): void
    {
        $this->removeMediaItem($this->galleryPaths, $this->removedGalleryPaths, $index);
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

    private function loadSettings(?string $section = null): void
    {
        $setting = DigitalMenuSetting::current();
        if ($section === null || $section === 'overview') {
            $this->primaryColor = $setting->primary_color ?: '#15803d';
        }
        if ($section === null || $section === 'banners') {
            $this->showBanners = $setting->show_banners;
            $this->autoplayBanners = $setting->autoplay_banners;
            $this->bannerIntervalSeconds = (int) $setting->banner_interval_seconds;
            $this->bannerPaths = $setting->bannerItems();
        }
        if ($section === null || $section === 'featured') {
            $this->showFeatured = $setting->show_featured;
            $this->featuredProductIds = array_values(array_map('intval', $setting->featured_product_ids ?? []));
        }
        if ($section === null || $section === 'categories') {
            $this->showCategories = $setting->show_categories;
            $this->categoryStyle = $setting->category_style ?: 'cards';
        }
        if ($section === null || $section === 'gallery') {
            $this->showGallery = $setting->show_gallery;
            $this->galleryPaths = $setting->galleryItems();
        }
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

    private function containsMissingMedia(array $items): bool
    {
        return collect($items)->contains(function (array $item): bool {
            $path = $item['path'] ?? null;

            return ! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path);
        });
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

    private function removeMediaItem(array &$items, array &$removedPaths, int $index): void
    {
        $this->authorizeAccess();
        abort_unless(isset($items[$index]), 404);
        $path = $items[$index]['path'] ?? null;
        if (is_string($path)) {
            $removedPaths[] = $path;
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
            'primaryColor.required' => 'Elige un color principal para el menú.',
            'primaryColor.regex' => 'El color seleccionado no es válido. Vuelve a elegirlo desde el selector.',
            'showBanners.boolean' => 'No pudimos reconocer si el carrusel debe mostrarse. Actualiza la página e inténtalo de nuevo.',
            'autoplayBanners.boolean' => 'No pudimos reconocer la reproducción automática. Actualiza la página e inténtalo de nuevo.',
            'bannerIntervalSeconds.integer' => 'Selecciona una duración válida para cada banner.',
            'bannerIntervalSeconds.min' => 'Cada banner debe mostrarse durante al menos 3 segundos.',
            'bannerIntervalSeconds.max' => 'Cada banner puede mostrarse durante un máximo de 12 segundos.',
            'bannerPaths.array' => 'No pudimos leer la lista de banners. Actualiza la página e inténtalo de nuevo.',
            'bannerPaths.*.path.required' => 'Uno de los banners guardados ya no tiene una imagen asociada. Quítalo o vuelve a subirlo.',
            'bannerPaths.*.path.string' => 'Uno de los banners guardados no tiene una ruta válida. Quítalo o vuelve a subirlo.',
            'bannerPaths.*.alt.max' => 'La descripción de cada banner puede tener máximo 120 caracteres.',
            'bannerUploads.array' => 'No pudimos leer las imágenes seleccionadas. Vuelve a elegirlas.',
            'bannerUploads.*.image' => 'Cada banner debe ser una imagen válida.',
            'bannerUploads.*.mimes' => 'Los banners deben ser JPG, PNG o WebP.',
            'bannerUploads.*.max' => 'Cada banner puede pesar máximo 6 MB.',
            'bannerUploadAlts.array' => 'No pudimos leer las descripciones de los banners. Actualiza la página e inténtalo de nuevo.',
            'bannerUploadAlts.*.max' => 'La descripción de cada banner puede tener máximo 120 caracteres.',
            'showFeatured.boolean' => 'No pudimos reconocer si los favoritos deben mostrarse. Actualiza la página e inténtalo de nuevo.',
            'featuredProductIds.array' => 'No pudimos leer los productos favoritos. Actualiza la página e inténtalo de nuevo.',
            'featuredProductIds.max' => 'Puedes publicar un máximo de '.self::MAX_FEATURED.' productos favoritos.',
            'featuredProductIds.*.integer' => 'Uno de los productos seleccionados no es válido. Quítalo y vuelve a seleccionarlo.',
            'featuredProductIds.*.distinct' => 'Un producto está repetido en favoritos. Quítalo y vuelve a guardar.',
            'featuredProductIds.*.exists' => 'Uno de los productos seleccionados ya no está disponible. Quítalo de favoritos y vuelve a guardar.',
            'showCategories.boolean' => 'No pudimos reconocer si las categorías deben mostrarse. Actualiza la página e inténtalo de nuevo.',
            'categoryStyle.required' => 'Elige cómo quieres mostrar las categorías.',
            'categoryStyle.in' => 'El estilo seleccionado ya no está disponible. Elige Tarjetas o Círculos.',
            'showGallery.boolean' => 'No pudimos reconocer si la galería debe mostrarse. Actualiza la página e inténtalo de nuevo.',
            'galleryPaths.array' => 'No pudimos leer la lista de fotografías. Actualiza la página e inténtalo de nuevo.',
            'galleryPaths.*.path.required' => 'Una fotografía guardada ya no tiene una imagen asociada. Quítala o vuelve a subirla.',
            'galleryPaths.*.path.string' => 'Una fotografía guardada no tiene una ruta válida. Quítala o vuelve a subirla.',
            'galleryPaths.*.caption.max' => 'El pie de cada fotografía puede tener máximo 120 caracteres.',
            'galleryUploads.array' => 'No pudimos leer las fotografías seleccionadas. Vuelve a elegirlas.',
            'galleryUploads.*.image' => 'Cada archivo de galería debe ser una imagen válida.',
            'galleryUploads.*.mimes' => 'Las imágenes de galería deben ser JPG, PNG o WebP.',
            'galleryUploads.*.max' => 'Cada imagen de galería puede pesar máximo 6 MB.',
            'galleryUploadCaptions.array' => 'No pudimos leer los pies de foto. Actualiza la página e inténtalo de nuevo.',
            'galleryUploadCaptions.*.max' => 'El pie de cada fotografía puede tener máximo 120 caracteres.',
        ];
    }

    private function validateSection(array $rules): void
    {
        try {
            $this->validate($rules, $this->validationMessages());
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?: 'Revisa los datos de esta sección e inténtalo de nuevo.';
            $this->notify('error', 'No guardamos '.$this->sectionLabel(), $message);

            throw $exception;
        }
    }

    private function rejectSection(string $field, string $message): void
    {
        $this->addError($field, $message);
        $this->notify('error', 'No guardamos '.$this->sectionLabel(), $message);
    }

    private function sectionLabel(): string
    {
        return match ($this->activeSection) {
            'overview' => 'la configuración general',
            'banners' => 'los banners',
            'featured' => 'los favoritos',
            'categories' => 'las categorías',
            'gallery' => 'la galería',
            default => 'los cambios',
        };
    }

    private function persistSection(string $section, \Closure $operation): bool
    {
        try {
            DB::transaction($operation);

            return true;
        } catch (Throwable $exception) {
            $this->handlePersistenceFailure($section, $exception);

            return false;
        }
    }

    private function handlePersistenceFailure(string $section, Throwable $exception, array $newMediaPaths = []): void
    {
        $this->discardNewMedia($newMediaPaths);
        Log::error("No fue posible guardar la sección {$section} del menú digital.", [
            'user_id' => auth()->id(),
            'section' => $this->activeSection,
            'exception' => $exception,
        ]);
        $message = 'No pudimos guardar esta sección. Tus cambios anteriores siguen intactos; espera un momento y vuelve a intentarlo.';
        $this->addError('save', $message);
        $this->notify('error', "No guardamos {$section}", $message);
    }

    private function deleteObsoleteMedia(array $removedPaths, array $currentItems, string $section): void
    {
        $protectedPaths = collect($currentItems)->pluck('path')->filter()->all();
        $obsoletePaths = collect($removedPaths)
            ->unique()
            ->reject(fn (string $path): bool => in_array($path, $protectedPaths, true))
            ->values()
            ->all();

        try {
            Storage::disk('public')->delete($obsoletePaths);
        } catch (Throwable $exception) {
            Log::warning("La sección {$section} se guardó, pero no se pudieron limpiar archivos anteriores.", [
                'user_id' => auth()->id(),
                'paths' => $obsoletePaths,
                'exception' => $exception,
            ]);
        }
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
