<?php

namespace App\Livewire\Admin;

use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\TicketTemplate;
use App\Services\ThermalTicketRenderer;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class BusinessSettingsManager extends Component
{
    use WithFileUploads;

    public const MAX_GALLERY_IMAGES = 24;

    public const KITCHEN_ITEM_FONTS = [
        'courier' => 'Courier New',
        'arial' => 'Arial',
        'verdana' => 'Verdana',
        'system' => 'Sistema',
    ];

    public string $activeTab = 'business';

    public string $businessSection = 'identity';

    public bool $canManageBusiness = false;

    public string $businessName = '';

    public string $platformName = '';

    public string $legalName = '';

    public string $rfc = '';

    public string $phone = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $website = '';

    public string $instagramUrl = '';

    public string $facebookUrl = '';

    public string $tiktokUrl = '';

    public string $address = '';

    public string $city = '';

    public string $state = '';

    public string $postalCode = '';

    public string $mapsUrl = '';

    public ?string $logoPath = null;

    public ?string $ticketLogoPath = null;

    public ?string $bannerPath = null;

    public $logoUpload = null;

    public $ticketLogoUpload = null;

    public $bannerUpload = null;

    public array $businessHours = [];

    public string $primaryColor = '#15803d';

    public string $homeBadge = '';

    public string $homeHeadline = '';

    public string $homeDescription = '';

    public string $homeIntroKicker = '';

    public string $homeIntroTitle = '';

    public string $homeIntroDescription = '';

    public array $galleryPaths = [];

    public array $galleryUploads = [];

    public array $galleryUploadCaptions = [];

    public array $featuredProductIds = [];

    public string $selectedType = 'customer';

    public int $paperWidth = 80;

    public int $fontSize = 12;

    public int $marginMm = 4;

    public int $logoWidthMm = 42;

    public string $itemFontFamily = 'courier';

    public int $itemFontSize = 18;

    public bool $showLogo = false;

    public bool $showQr = false;

    public string $qrLabel = '';

    public string $footerText = '';

    public bool $showRfc = true;

    public bool $showPhone = true;

    public bool $showAddress = true;

    public array $blocks = [];

    public function mount(): void
    {
        $this->authorizeAccess();
        $this->canManageBusiness = (bool) auth()->user()?->can('gestionar configuracion negocio');
        $requestedTab = request()->routeIs('app.configuracion-negocio.menu') ? 'menu' : request()->query('tab');

        if ($this->canManageBusiness) {
            TicketTemplate::ensureDefaults();
            $this->loadBusiness();
            $this->loadTemplate();
            $this->activeTab = in_array($requestedTab, ['business', 'tickets', 'menu'], true) ? $requestedTab : 'business';
        } else {
            $this->activeTab = 'menu';
        }
    }

    public function setTab(string $tab): void
    {
        abort_unless(in_array($tab, ['business', 'tickets', 'menu'], true), 404);
        if (in_array($tab, ['business', 'tickets'], true)) {
            $this->authorizeManage();
        }
        if ($tab === 'menu') {
            abort_unless(auth()->user()?->can('ver menu sidebar'), 403);
        }
        $this->activeTab = $tab;
    }

    public function setBusinessSection(string $section): void
    {
        $this->authorizeManage();
        abort_unless(in_array($section, ['identity', 'contact', 'hours', 'social', 'visual', 'appearance', 'homepage', 'gallery', 'featured'], true), 404);
        $this->businessSection = $section;
    }

    public function selectType(string $type): void
    {
        abort_unless(array_key_exists($type, TicketTemplate::TYPES), 404);
        $this->selectedType = $type;
        $this->loadTemplate();
        unset($this->previewHtml);
    }

    public function saveBusiness(): void
    {
        $this->authorizeManage();
        $this->validate([
            'businessName' => 'required|string|max:120',
            'platformName' => 'required|string|max:120',
            'legalName' => 'nullable|string|max:160',
            'rfc' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:160',
            'website' => 'nullable|url|max:200',
            'instagramUrl' => 'nullable|url|max:255',
            'facebookUrl' => 'nullable|url|max:255',
            'tiktokUrl' => 'nullable|url|max:255',
            'address' => 'nullable|string|max:200',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postalCode' => 'nullable|string|max:10',
            'mapsUrl' => 'nullable|url:http,https|max:500',
            'logoUpload' => 'nullable|image|max:4096',
            'ticketLogoUpload' => 'nullable|image|max:2048',
            'bannerUpload' => 'nullable|image|max:6144',
            'primaryColor' => [
                'required',
                'regex:/^#[0-9A-Fa-f]{6}$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && ! $this->hasReadableWhiteContrast($value)) {
                        $fail('El color debe ser suficientemente oscuro para mantener un contraste accesible.');
                    }
                },
            ],
            'galleryUploads' => 'array',
            'homeBadge' => 'nullable|string|max:120',
            'homeHeadline' => 'nullable|string|max:180',
            'homeDescription' => 'nullable|string|max:600',
            'homeIntroKicker' => 'nullable|string|max:80',
            'homeIntroTitle' => 'nullable|string|max:180',
            'homeIntroDescription' => 'nullable|string|max:600',
            'galleryUploads.*' => 'image|max:6144',
            'galleryPaths.*.caption' => 'nullable|string|max:120',
            'galleryUploadCaptions' => 'array',
            'galleryUploadCaptions.*' => 'nullable|string|max:120',
            'featuredProductIds' => 'array|max:8',
            'featuredProductIds.*' => 'integer|distinct|exists:products,id',
            'businessHours' => 'required|array|size:7',
            'businessHours.*.key' => 'required|string|max:20',
            'businessHours.*.label' => 'required|string|max:20',
            'businessHours.*.enabled' => 'required|boolean',
            'businessHours.*.opens' => 'required|date_format:H:i',
            'businessHours.*.closes' => 'required|date_format:H:i',
        ]);

        if (count($this->galleryPaths) + count($this->galleryUploads) > self::MAX_GALLERY_IMAGES) {
            $this->addError('galleryUploads', 'La galería admite un máximo de '.self::MAX_GALLERY_IMAGES.' imágenes.');

            return;
        }

        $setting = BusinessSetting::current();
        $galleryPaths = $this->storeGalleryUploads($this->galleryPaths);

        $paths = [
            'logo_path' => $this->storeUpload($this->logoUpload, $setting->logo_path, 'logo'),
            'ticket_logo_path' => $this->storeUpload($this->ticketLogoUpload, $setting->ticket_logo_path, 'ticket-logo'),
            'banner_path' => $this->storeUpload($this->bannerUpload, $setting->banner_path, 'banner'),
        ];

        $setting->update(array_merge($paths, [
            'business_name' => trim($this->businessName),
            'platform_name' => trim($this->platformName),
            'legal_name' => trim($this->legalName) ?: null,
            'rfc' => strtoupper(trim($this->rfc)) ?: null,
            'phone' => trim($this->phone) ?: null,
            'whatsapp' => trim($this->whatsapp) ?: null,
            'email' => trim($this->email) ?: null,
            'website' => trim($this->website) ?: null,
            'instagram_url' => trim($this->instagramUrl) ?: null,
            'facebook_url' => trim($this->facebookUrl) ?: null,
            'tiktok_url' => trim($this->tiktokUrl) ?: null,
            'address' => trim($this->address) ?: null,
            'city' => trim($this->city) ?: null,
            'state' => trim($this->state) ?: null,
            'postal_code' => trim($this->postalCode) ?: null,
            'maps_url' => trim($this->mapsUrl) ?: null,
            'business_hours' => array_values($this->businessHours),
            'primary_color' => strtolower($this->primaryColor),
            'home_badge' => trim($this->homeBadge) ?: null,
            'home_headline' => trim($this->homeHeadline) ?: null,
            'home_description' => trim($this->homeDescription) ?: null,
            'home_intro_kicker' => trim($this->homeIntroKicker) ?: null,
            'home_intro_title' => trim($this->homeIntroTitle) ?: null,
            'home_intro_description' => trim($this->homeIntroDescription) ?: null,
            'gallery_paths' => array_values($galleryPaths),
            'featured_product_ids' => array_values(array_map('intval', $this->featuredProductIds)),
            'updated_by' => auth()->id(),
        ]));

        $this->logoUpload = $this->ticketLogoUpload = $this->bannerUpload = null;
        $this->galleryUploads = [];
        $this->galleryUploadCaptions = [];
        $this->loadBusiness();
        unset($this->previewHtml);
        session()->flash('success', 'Configuración del negocio guardada.');
    }

    public function saveGallery(): void
    {
        $this->authorizeManage();
        $this->validate([
            'galleryPaths' => 'array',
            'galleryPaths.*.path' => 'required|string',
            'galleryPaths.*.caption' => 'nullable|string|max:120',
            'galleryUploads' => 'array',
            'galleryUploads.*' => 'image|max:6144',
            'galleryUploadCaptions' => 'array',
            'galleryUploadCaptions.*' => 'nullable|string|max:120',
        ]);

        $setting = BusinessSetting::current();
        $currentPaths = $this->normalizeGalleryItems($this->galleryPaths);

        if (count($currentPaths) + count($this->galleryUploads) > self::MAX_GALLERY_IMAGES) {
            $this->addError('galleryUploads', 'La galería admite un máximo de '.self::MAX_GALLERY_IMAGES.' imágenes.');

            return;
        }

        $paths = $this->storeGalleryUploads($currentPaths);
        $setting->update(['gallery_paths' => $paths, 'updated_by' => auth()->id()]);
        $this->galleryPaths = $paths;
        $this->galleryUploads = [];
        $this->galleryUploadCaptions = [];
        session()->flash('success', 'Galería actualizada correctamente.');
    }

    public function removeGalleryImage(int $index): void
    {
        $this->authorizeManage();
        $setting = BusinessSetting::current();
        $paths = $setting->galleryItems();
        $path = $paths[$index]['path'] ?? null;

        abort_unless($path, 404);
        Storage::disk('public')->delete($path);
        array_splice($paths, $index, 1);
        $setting->update(['gallery_paths' => $paths, 'updated_by' => auth()->id()]);
        $this->galleryPaths = $paths;
        session()->flash('success', 'Imagen eliminada de la galería.');
    }

    public function removePendingGalleryImage(int $index): void
    {
        $this->authorizeManage();

        abort_unless(isset($this->galleryUploads[$index]), 404);
        array_splice($this->galleryUploads, $index, 1);
        array_splice($this->galleryUploadCaptions, $index, 1);
        $this->galleryUploads = array_values($this->galleryUploads);
        $this->galleryUploadCaptions = array_values($this->galleryUploadCaptions);
    }

    public function setHoursPreset(string $preset): void
    {
        $this->authorizeManage();
        abort_unless(in_array($preset, ['weekdays', 'everyday', 'closed'], true), 404);

        foreach ($this->businessHours as $index => $day) {
            $this->businessHours[$index]['enabled'] = match ($preset) {
                'weekdays' => ! in_array($day['key'], ['saturday', 'sunday'], true),
                'everyday' => true,
                'closed' => false,
            };
        }
    }

    #[Computed]
    public function availablePublicProducts()
    {
        return Product::query()
            ->where('is_active', true)
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function saveTemplate(): void
    {
        $this->authorizeManage();
        $this->validate([
            'paperWidth' => 'required|in:58,80',
            'fontSize' => 'required|integer|min:9|max:16',
            'marginMm' => 'required|integer|min:2|max:6',
            'logoWidthMm' => 'required|integer|in:12,18,24,30,36,42,48,54',
            'itemFontFamily' => 'required|in:courier,arial,verdana,system',
            'itemFontSize' => 'required|integer|min:12|max:28',
            'qrLabel' => 'nullable|string|max:120',
            'footerText' => 'nullable|string|max:240',
            'blocks' => 'required|array|min:1',
            'blocks.*.key' => 'required|string',
            'blocks.*.label' => 'required|string|max:80',
            'blocks.*.enabled' => 'required|boolean',
        ]);

        TicketTemplate::current($this->selectedType)->update([
            'paper_width_mm' => $this->paperWidth,
            'font_size' => $this->fontSize,
            'margin_mm' => $this->marginMm,
            'show_logo' => $this->showLogo,
            'show_qr' => $this->showQr,
            'qr_label' => trim($this->qrLabel) ?: null,
            'footer_text' => trim($this->footerText) ?: null,
            'blocks' => array_values($this->blocks),
            'options' => [
                'show_rfc' => $this->showRfc,
                'show_phone' => $this->showPhone,
                'show_address' => $this->showAddress,
                'logo_width_mm' => $this->logoWidthMm,
                'item_font_family' => $this->itemFontFamily,
                'item_font_size' => $this->itemFontSize,
            ],
            'updated_by' => auth()->id(),
        ]);

        unset($this->previewHtml);
        session()->flash('success', 'Plantilla de ticket guardada.');
    }

    public function toggleBlock(int $index): void
    {
        if (isset($this->blocks[$index])) {
            $this->blocks[$index]['enabled'] = ! ($this->blocks[$index]['enabled'] ?? false);
            if (($this->blocks[$index]['key'] ?? null) === 'qr') {
                $this->showQr = (bool) $this->blocks[$index]['enabled'];
            }
            unset($this->previewHtml);
        }
    }

    public function updatedShowQr(bool $enabled): void
    {
        foreach ($this->blocks as $index => $block) {
            if (($block['key'] ?? null) === 'qr') {
                $this->blocks[$index]['enabled'] = $enabled;
                break;
            }
        }

        unset($this->previewHtml);
    }

    public function moveBlock(int $index, int $direction): void
    {
        $target = $index + $direction;
        if (! isset($this->blocks[$index], $this->blocks[$target])) {
            return;
        }
        [$this->blocks[$index], $this->blocks[$target]] = [$this->blocks[$target], $this->blocks[$index]];
        $this->blocks = array_values($this->blocks);
        unset($this->previewHtml);
    }

    public function resetTemplate(): void
    {
        $defaults = TicketTemplate::defaultsFor($this->selectedType);
        TicketTemplate::current($this->selectedType)->update(array_merge($defaults, ['updated_by' => auth()->id()]));
        $this->loadTemplate();
        unset($this->previewHtml);
        session()->flash('success', 'Plantilla restaurada.');
    }

    #[Computed]
    public function previewHtml(): string
    {
        $template = new TicketTemplate([
            'key' => $this->selectedType,
            'name' => TicketTemplate::TYPES[$this->selectedType]['name'],
            'paper_width_mm' => $this->paperWidth,
            'font_size' => $this->fontSize,
            'margin_mm' => $this->marginMm,
            'show_logo' => $this->showLogo,
            'show_qr' => $this->showQr,
            'qr_label' => $this->qrLabel,
            'footer_text' => $this->footerText,
            'blocks' => $this->blocks,
            'options' => [
                'show_rfc' => $this->showRfc,
                'show_phone' => $this->showPhone,
                'show_address' => $this->showAddress,
                'logo_width_mm' => $this->logoWidthMm,
                'item_font_family' => $this->itemFontFamily,
                'item_font_size' => $this->itemFontSize,
            ],
        ]);
        $business = clone BusinessSetting::current();
        $business->fill([
            'business_name' => $this->businessName,
            'platform_name' => $this->platformName,
            'legal_name' => $this->legalName,
            'rfc' => $this->rfc,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'website' => $this->website,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postalCode,
        ]);

        return app(ThermalTicketRenderer::class)->renderPreview($this->selectedType, $template, $business);
    }

    private function loadBusiness(): void
    {
        $setting = BusinessSetting::current();
        $this->businessName = $setting->business_name;
        $this->platformName = $setting->platform_name;
        $this->legalName = $setting->legal_name ?? '';
        $this->rfc = $setting->rfc ?? '';
        $this->phone = $setting->phone ?? '';
        $this->whatsapp = $setting->whatsapp ?? '';
        $this->email = $setting->email ?? '';
        $this->website = $setting->website ?? '';
        $this->instagramUrl = $setting->instagram_url ?? '';
        $this->facebookUrl = $setting->facebook_url ?? '';
        $this->tiktokUrl = $setting->tiktok_url ?? '';
        $this->address = $setting->address ?? '';
        $this->city = $setting->city ?? '';
        $this->state = $setting->state ?? '';
        $this->postalCode = $setting->postal_code ?? '';
        $this->mapsUrl = $setting->maps_url ?? '';
        $this->logoPath = $setting->logo_path;
        $this->ticketLogoPath = $setting->ticket_logo_path;
        $this->bannerPath = $setting->banner_path;
        $this->businessHours = $setting->business_hours ?: BusinessSetting::DEFAULT_HOURS;
        $this->primaryColor = $setting->primary_color ?: '#15803d';
        $this->homeBadge = $setting->home_badge ?? '';
        $this->homeHeadline = $setting->home_headline ?? '';
        $this->homeDescription = $setting->home_description ?? '';
        $this->homeIntroKicker = $setting->home_intro_kicker ?? '';
        $this->homeIntroTitle = $setting->home_intro_title ?? '';
        $this->homeIntroDescription = $setting->home_intro_description ?? '';
        $this->galleryPaths = $setting->galleryItems();
        $this->featuredProductIds = array_map('strval', $setting->featured_product_ids ?? []);
    }

    private function loadTemplate(): void
    {
        $template = TicketTemplate::current($this->selectedType);
        $this->paperWidth = (int) $template->paper_width_mm;
        $this->fontSize = (int) $template->font_size;
        $this->marginMm = (int) $template->margin_mm;
        $this->showLogo = $template->show_logo;
        $this->showQr = $template->show_qr;
        $this->qrLabel = $template->qr_label ?? '';
        $this->footerText = $template->footer_text ?? '';
        $this->showRfc = (bool) ($template->options['show_rfc'] ?? true);
        $this->showPhone = (bool) ($template->options['show_phone'] ?? true);
        $this->showAddress = (bool) ($template->options['show_address'] ?? true);
        $this->logoWidthMm = (int) ($template->options['logo_width_mm'] ?? 42);
        $this->itemFontFamily = (string) ($template->options['item_font_family'] ?? 'courier');
        $this->itemFontSize = (int) ($template->options['item_font_size'] ?? ($this->selectedType === 'kitchen_area' ? 18 : 12));
        $this->blocks = $template->blocks;
    }

    private function storeUpload($upload, ?string $currentPath, string $name): ?string
    {
        if (! $upload) {
            return $currentPath;
        }
        if ($currentPath) {
            Storage::disk('public')->delete($currentPath);
        }

        return $upload->storeAs('business', $name.'-'.now()->format('YmdHis').'.'.$upload->getClientOriginalExtension(), 'public');
    }

    private function storeGalleryUploads(array $currentPaths): array
    {
        $items = $this->normalizeGalleryItems($currentPaths);

        foreach ($this->galleryUploads as $index => $upload) {
            $items[] = [
                'path' => $upload->store('business/gallery', 'public'),
                'caption' => trim((string) ($this->galleryUploadCaptions[$index] ?? '')),
            ];
        }

        return array_values($items);
    }

    private function normalizeGalleryItems(array $items): array
    {
        return collect($items)
            ->map(function (mixed $item): ?array {
                if (is_string($item)) {
                    return ['path' => $item, 'caption' => ''];
                }

                if (! is_array($item) || ! is_string($item['path'] ?? null)) {
                    return null;
                }

                return [
                    'path' => $item['path'],
                    'caption' => trim((string) ($item['caption'] ?? '')),
                ];
            })
            ->filter()
            ->values()
            ->all();
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

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->can('gestionar configuracion negocio'), 403);
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->can('gestionar configuracion negocio') || $user->can('ver menu sidebar')), 403);
    }

    public function render()
    {
        return view('livewire.admin.business-settings-manager', ['ticketTypes' => TicketTemplate::TYPES]);
    }
}
