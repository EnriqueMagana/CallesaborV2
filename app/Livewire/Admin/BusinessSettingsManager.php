<?php

namespace App\Livewire\Admin;

use App\Models\BusinessSetting;
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
    public string $address = '';
    public string $city = '';
    public string $state = '';
    public string $postalCode = '';
    public ?string $logoPath = null;
    public ?string $ticketLogoPath = null;
    public ?string $bannerPath = null;
    public $logoUpload = null;
    public $ticketLogoUpload = null;
    public $bannerUpload = null;
    public array $businessHours = [];

    public string $selectedType = 'customer';
    public int $paperWidth = 80;
    public int $fontSize = 12;
    public int $marginMm = 4;
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
        if (in_array($tab, ['business', 'tickets'], true)) $this->authorizeManage();
        if ($tab === 'menu') abort_unless(auth()->user()?->can('ver menu sidebar'), 403);
        $this->activeTab = $tab;
    }

    public function setBusinessSection(string $section): void
    {
        $this->authorizeManage();
        abort_unless(in_array($section, ['identity', 'contact', 'hours', 'visual'], true), 404);
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
            'address' => 'nullable|string|max:200',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postalCode' => 'nullable|string|max:10',
            'logoUpload' => 'nullable|image|max:4096',
            'ticketLogoUpload' => 'nullable|image|max:2048',
            'bannerUpload' => 'nullable|image|max:6144',
            'businessHours' => 'required|array|size:7',
            'businessHours.*.key' => 'required|string|max:20',
            'businessHours.*.label' => 'required|string|max:20',
            'businessHours.*.enabled' => 'required|boolean',
            'businessHours.*.opens' => 'required|date_format:H:i',
            'businessHours.*.closes' => 'required|date_format:H:i',
        ]);

        $setting = BusinessSetting::current();
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
            'address' => trim($this->address) ?: null,
            'city' => trim($this->city) ?: null,
            'state' => trim($this->state) ?: null,
            'postal_code' => trim($this->postalCode) ?: null,
            'business_hours' => array_values($this->businessHours),
            'updated_by' => auth()->id(),
        ]));

        $this->logoUpload = $this->ticketLogoUpload = $this->bannerUpload = null;
        $this->loadBusiness();
        unset($this->previewHtml);
        session()->flash('success', 'Configuración del negocio guardada.');
    }

    public function saveTemplate(): void
    {
        $this->authorizeManage();
        $this->validate([
            'paperWidth' => 'required|in:58,80',
            'fontSize' => 'required|integer|min:9|max:16',
            'marginMm' => 'required|integer|min:2|max:6',
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
            'options' => ['show_rfc' => $this->showRfc, 'show_phone' => $this->showPhone, 'show_address' => $this->showAddress],
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
        if (! isset($this->blocks[$index], $this->blocks[$target])) return;
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
            'options' => ['show_rfc' => $this->showRfc, 'show_phone' => $this->showPhone, 'show_address' => $this->showAddress],
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
        $this->address = $setting->address ?? '';
        $this->city = $setting->city ?? '';
        $this->state = $setting->state ?? '';
        $this->postalCode = $setting->postal_code ?? '';
        $this->logoPath = $setting->logo_path;
        $this->ticketLogoPath = $setting->ticket_logo_path;
        $this->bannerPath = $setting->banner_path;
        $this->businessHours = $setting->business_hours ?: BusinessSetting::DEFAULT_HOURS;
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
        $this->blocks = $template->blocks;
    }

    private function storeUpload($upload, ?string $currentPath, string $name): ?string
    {
        if (! $upload) return $currentPath;
        if ($currentPath) Storage::disk('public')->delete($currentPath);
        return $upload->storeAs('business', $name.'-'.now()->format('YmdHis').'.'.$upload->getClientOriginalExtension(), 'public');
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
