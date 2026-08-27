<?php

namespace App\Livewire\Admin;

use App\Models\Product;
use App\Models\Promotion;
use App\Traits\ConvertsToWebp;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class PromotionManager extends Component
{
    use AuthorizesRequests, ConvertsToWebp, WithFileUploads;

    public bool $showEditor = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $shortDescription = '';

    public string $presentationType = 'promotion';

    public ?int $primaryProductId = null;

    public int $wizardStep = 1;

    public string $discountPercentage = '';

    public string $price = '';

    public bool $launchOfferEnabled = false;

    public int $buyQuantity = 1;

    public int $rewardQuantity = 1;

    public int $rewardDiscountPercentage = 50;

    public string $maxApplicationsPerOrder = '';

    public string $startsOn = '';

    public string $endsOn = '';

    public array $weekdays = [];

    public array $fulfillmentModes = ['dine_in', 'takeaway', 'pickup', 'delivery'];

    public string $termsAndConditions = '';

    public bool $showOnPos = true;

    public bool $showOnDigitalMenu = true;

    public bool $showOnKiosk = true;

    public bool $isActive = true;

    public $image;

    public ?string $currentImage = null;

    public array $groups = [];

    public string $activeView = 'catalog';

    public string $calendarMonth = '';

    public function mount(): void
    {
        $this->authorize('ver promociones');
        $this->calendarMonth = now()->format('Y-m');

        $productId = (int) request()->query('product', 0);
        if ($productId > 0 && auth()->user()?->can('crear promociones')) {
            $product = Product::query()->where('is_active', true)->find($productId);
            if ($product) {
                $this->resetForm();
                $this->presentationType = 'new';
                $this->primaryProductId = $product->id;
                $this->startsOn = now()->toDateString();
                $this->showOnPos = false;
                $this->showOnKiosk = false;
                $this->updatedPrimaryProductId($product->id);
                $this->wizardStep = 2;
                $this->showEditor = true;
            }
        }
    }

    #[Computed]
    public function promotions()
    {
        return Promotion::query()
            ->with(['groups.products:id,name,image', 'primaryProduct:id,name,image,price'])
            ->latest('starts_on')
            ->latest('id')
            ->get();
    }

    #[Computed]
    public function products()
    {
        return Product::query()
            ->where('is_active', true)
            ->with('category:id,name')
            ->orderBy('name')
            ->get(['id', 'category_id', 'name', 'image']);
    }

    #[Computed]
    public function calendarDays(): array
    {
        $month = CarbonImmutable::createFromFormat('Y-m-d', $this->calendarMonth.'-01')->startOfDay();
        $start = $month->startOfMonth()->startOfWeek();
        $end = $month->endOfMonth()->endOfWeek();
        $days = [];

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $days[] = [
                'date' => $date,
                'current_month' => $date->month === $month->month,
                'today' => $date->isToday(),
                'promotions' => $this->promotions
                    ->filter(fn (Promotion $promotion) => $promotion->isScheduledFor($date))
                    ->values(),
            ];
        }

        return $days;
    }

    public function showCatalog(): void
    {
        $this->activeView = 'catalog';
    }

    public function showCalendar(): void
    {
        $this->activeView = 'calendar';
    }

    public function previousMonth(): void
    {
        $this->calendarMonth = CarbonImmutable::createFromFormat('Y-m-d', $this->calendarMonth.'-01')->subMonth()->format('Y-m');
        unset($this->calendarDays);
    }

    public function nextMonth(): void
    {
        $this->calendarMonth = CarbonImmutable::createFromFormat('Y-m-d', $this->calendarMonth.'-01')->addMonth()->format('Y-m');
        unset($this->calendarDays);
    }

    public function goToCurrentMonth(): void
    {
        $this->calendarMonth = now()->format('Y-m');
        unset($this->calendarDays);
    }

    public function openCreate(): void
    {
        $this->authorize('crear promociones');
        $this->resetForm();
        $this->startsOn = now()->toDateString();
        $this->addGroup();
        $this->showEditor = true;
    }

    public function openEdit(int $promotionId): void
    {
        $this->authorize('editar promociones');
        $promotion = Promotion::with('groups.products:id')->findOrFail($promotionId);
        $this->resetForm();
        $this->editingId = $promotion->id;
        $this->name = $promotion->name;
        $this->description = (string) $promotion->description;
        $this->shortDescription = (string) ($promotion->short_description ?: str($promotion->description ?: $promotion->name)->limit(160));
        $this->presentationType = $promotion->presentation_type;
        $this->primaryProductId = $promotion->primary_product_id;
        $this->discountPercentage = (string) ($promotion->discount_percentage ?? '');
        $pricingRule = $promotion->normalizedPricingRule();
        $this->launchOfferEnabled = $promotion->hasAutomaticPricingRule();
        $this->buyQuantity = $pricingRule['buy_quantity'];
        $this->rewardQuantity = $pricingRule['reward_quantity'];
        $this->rewardDiscountPercentage = $pricingRule['reward_discount_percentage'];
        $this->maxApplicationsPerOrder = (string) ($pricingRule['max_applications_per_order'] ?? '');
        $this->price = (string) $promotion->price;
        $this->startsOn = $promotion->starts_on->toDateString();
        $this->endsOn = $promotion->ends_on?->toDateString() ?? '';
        $this->weekdays = array_map('intval', $promotion->weekdays ?? []);
        $this->fulfillmentModes = $promotion->isProductLaunch() && ! $promotion->hasAutomaticPricingRule()
            ? Promotion::FULFILLMENT_MODES
            : array_values($promotion->fulfillment_modes ?: Promotion::FULFILLMENT_MODES);
        $this->termsAndConditions = (string) $promotion->terms_and_conditions;
        $this->showOnPos = $promotion->show_on_pos;
        $this->showOnDigitalMenu = $promotion->show_on_digital_menu;
        $this->showOnKiosk = $promotion->show_on_kiosk;
        $this->isActive = $promotion->is_active;
        $this->currentImage = $promotion->image;
        $this->groups = $promotion->groups->map(fn ($group) => [
            'id' => $group->id,
            'name' => $group->name,
            'min_selections' => $group->min_selections,
            'max_selections' => $group->max_selections,
            'product_ids' => $group->products->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ])->all();
        $this->wizardStep = 1;
        $this->showEditor = true;
    }

    public function closeEditor(): void
    {
        $this->showEditor = false;
        $this->resetForm();
    }

    public function addGroup(): void
    {
        $this->groups[] = [
            'id' => null,
            'name' => '',
            'min_selections' => 1,
            'max_selections' => 1,
            'product_ids' => [],
        ];
    }

    public function updatedPresentationType(string $type): void
    {
        if (! in_array($type, Promotion::PRESENTATION_TYPES, true)) {
            return;
        }

        $this->resetValidation();
        if ($type === 'new') {
            $this->fulfillmentModes = Promotion::FULFILLMENT_MODES;
            $this->groups = [];

            return;
        }

        $this->primaryProductId = null;
        $this->showOnPos = true;
        $this->showOnKiosk = true;
        if ($this->groups === []) {
            $this->addGroup();
        }
    }

    public function updatedPrimaryProductId($productId): void
    {
        if ($this->presentationType !== 'new' || ! $productId) {
            return;
        }

        $product = Product::query()->where('is_active', true)->find((int) $productId);
        if (! $product) {
            return;
        }

        $this->name = $product->name;
        $this->description = (string) $product->description;
        $this->shortDescription = str($product->description ?: "Descubre {$product->name}, nuevo en nuestro menú.")->limit(160, '')->toString();
        $this->price = (string) $product->price;
    }

    public function nextWizardStep(): void
    {
        if ($this->wizardStep === 1) {
            $this->validateOnly('presentationType', [
                'presentationType' => ['required', Rule::in(Promotion::PRESENTATION_TYPES)],
            ]);
        }

        if ($this->wizardStep === 2) {
            $rules = [
                'name' => ['required', 'string', 'max:120'],
                'shortDescription' => ['required', 'string', 'min:10', 'max:160'],
                'description' => ['nullable', 'string', 'max:500'],
            ];
            if ($this->presentationType === 'new') {
                $rules['primaryProductId'] = ['required', Rule::exists('products', 'id')->where('is_active', true)];
                if ($this->launchOfferEnabled) {
                    $rules += $this->pricingRuleValidationRules();
                }
            } else {
                $rules['price'] = ['required', 'numeric', 'min:0.01', 'max:999999.99'];
                $rules['discountPercentage'] = ['nullable', 'required_if:presentationType,discount', 'integer', 'between:1,99'];
                $rules += [
                    'groups' => ['required', 'array', 'min:1', 'max:12'],
                    'groups.*.name' => ['required', 'string', 'max:100'],
                    'groups.*.min_selections' => ['required', 'integer', 'min:0', 'max:99'],
                    'groups.*.max_selections' => ['required', 'integer', 'min:1', 'max:99'],
                    'groups.*.product_ids' => ['required', 'array', 'min:1'],
                    'groups.*.product_ids.*' => ['required', 'integer', 'distinct', 'exists:products,id'],
                ];
            }
            $this->validate($rules);
            foreach ($this->groups as $index => $group) {
                if ((int) $group['min_selections'] > (int) $group['max_selections']) {
                    throw ValidationException::withMessages([
                        "groups.{$index}.min_selections" => 'El mínimo no puede superar el máximo.',
                    ]);
                }
            }
        }

        if ($this->wizardStep === 3) {
            $rules = [
                'startsOn' => ['required', 'date'],
                'endsOn' => ['nullable', 'date', 'after_or_equal:startsOn'],
                'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            ];
            if ($this->presentationType !== 'new' || $this->launchOfferEnabled) {
                $rules += [
                    'fulfillmentModes' => ['required', 'array', 'min:1'],
                    'fulfillmentModes.*' => ['required', Rule::in(Promotion::FULFILLMENT_MODES), 'distinct'],
                    'termsAndConditions' => ['nullable', 'string', 'max:1000'],
                ];
            }
            $this->validate($rules);
            $this->validateChannelFulfillmentCompatibility();
            if ($this->presentationType !== 'new' && ! $this->image && ! $this->currentImage) {
                throw ValidationException::withMessages(['image' => 'Carga una imagen horizontal para el banner promocional.']);
            }
            if ($this->presentationType === 'new') {
                $product = Product::query()->where('is_active', true)->find($this->primaryProductId);
                if (! $this->image && ! $this->currentImage && ! $product?->image) {
                    throw ValidationException::withMessages(['image' => 'Carga una imagen o agrega una al producto seleccionado.']);
                }
            }
            if (! $this->showOnPos && ! $this->showOnDigitalMenu && ! $this->showOnKiosk) {
                throw ValidationException::withMessages(['channels' => 'Selecciona al menos un canal de publicación.']);
            }
        }

        $this->wizardStep = min(4, $this->wizardStep + 1);
    }

    public function previousWizardStep(): void
    {
        $this->wizardStep = max(1, $this->wizardStep - 1);
        $this->resetValidation();
    }

    public function removeGroup(int $index): void
    {
        if (count($this->groups) <= 1) {
            return;
        }

        array_splice($this->groups, $index, 1);
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'editar promociones' : 'crear promociones');

        if ($this->presentationType === 'new' && $this->primaryProductId) {
            $productPrice = Product::query()->where('is_active', true)->find($this->primaryProductId)?->price;
            if ($productPrice !== null) {
                $this->price = (string) $productPrice;
            }
        }

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'shortDescription' => ['required', 'string', 'min:10', 'max:160'],
            'presentationType' => ['required', Rule::in(Promotion::PRESENTATION_TYPES)],
            'primaryProductId' => ['nullable'],
            'discountPercentage' => ['nullable', 'required_if:presentationType,discount', 'integer', 'between:1,99'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'startsOn' => ['required', 'date'],
            'endsOn' => ['nullable', 'date', 'after_or_equal:startsOn'],
            'weekdays' => ['array'],
            'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'fulfillmentModes' => ['array'],
            'termsAndConditions' => ['nullable', 'string', 'max:1000'],
            'showOnPos' => ['boolean'],
            'showOnDigitalMenu' => ['boolean'],
            'showOnKiosk' => ['boolean'],
            'isActive' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
        if ($this->presentationType === 'new') {
            $rules['primaryProductId'] = ['required', Rule::exists('products', 'id')->where('is_active', true)];
            if ($this->launchOfferEnabled) {
                $rules += $this->pricingRuleValidationRules();
                $rules += [
                    'fulfillmentModes' => ['required', 'array', 'min:1'],
                    'fulfillmentModes.*' => ['required', Rule::in(Promotion::FULFILLMENT_MODES), 'distinct'],
                ];
            }
        } else {
            $rules += [
                'fulfillmentModes' => ['required', 'array', 'min:1'],
                'fulfillmentModes.*' => ['required', Rule::in(Promotion::FULFILLMENT_MODES), 'distinct'],
                'groups' => ['required', 'array', 'min:1', 'max:12'],
                'groups.*.name' => ['required', 'string', 'max:100'],
                'groups.*.min_selections' => ['required', 'integer', 'min:0', 'max:99'],
                'groups.*.max_selections' => ['required', 'integer', 'min:1', 'max:99'],
                'groups.*.product_ids' => ['required', 'array', 'min:1'],
                'groups.*.product_ids.*' => ['required', 'integer', 'distinct', 'exists:products,id'],
            ];
        }

        $validated = $this->validate($rules, [], [
            'name' => 'nombre',
            'price' => 'precio promocional',
            'startsOn' => 'fecha de inicio',
            'endsOn' => 'fecha de fin',
            'image' => 'imagen',
        ]);
        $this->validateChannelFulfillmentCompatibility();

        if ($this->presentationType === 'new') {
            $selectedProduct = Product::query()->where('is_active', true)->findOrFail((int) $validated['primaryProductId']);
            if (! $this->image && ! $this->currentImage && ! $selectedProduct->image) {
                throw ValidationException::withMessages(['primaryProductId' => 'El producto necesita una imagen antes de publicarse como novedad.']);
            }
        } elseif (! $this->image && ! $this->currentImage) {
            throw ValidationException::withMessages(['image' => 'Los banners promocionales necesitan una imagen horizontal.']);
        }

        if (! $this->showOnPos && ! $this->showOnDigitalMenu && ! $this->showOnKiosk) {
            throw ValidationException::withMessages(['channels' => 'Selecciona al menos un canal de publicación.']);
        }

        foreach ($validated['groups'] ?? [] as $index => $group) {
            if ((int) $group['min_selections'] > (int) $group['max_selections']) {
                throw ValidationException::withMessages([
                    "groups.{$index}.min_selections" => 'El mínimo no puede superar el máximo.',
                ]);
            }
        }

        $oldImage = $this->currentImage;
        $newImage = null;
        if ($this->image) {
            try {
                $newImage = $this->storeAsWebp($this->image, 'promotions', 80, 1600);
            } catch (\Throwable $exception) {
                report($exception);
                throw ValidationException::withMessages([
                    'image' => 'No fue posible optimizar y guardar la imagen. Intenta con otro archivo JPG, PNG o WEBP.',
                ]);
            }
        }

        try {
            DB::transaction(function () use ($validated, $newImage): void {
                $promotion = $this->editingId
                    ? Promotion::query()->lockForUpdate()->findOrFail($this->editingId)
                    : new Promotion(['created_by' => auth()->id()]);

                $promotion->fill([
                    'name' => trim($validated['name']),
                    'description' => trim((string) ($validated['description'] ?? '')) ?: null,
                    'short_description' => trim($validated['shortDescription']),
                    'presentation_type' => $validated['presentationType'],
                    'primary_product_id' => $validated['presentationType'] === 'new'
                        ? (int) $validated['primaryProductId']
                        : null,
                    'price' => $validated['price'],
                    'discount_percentage' => $validated['presentationType'] === 'discount'
                        ? (int) $validated['discountPercentage']
                        : null,
                    'pricing_rule_type' => $validated['presentationType'] === 'new' && $this->launchOfferEnabled
                        ? Promotion::PRICING_RULE_BUY_X_GET_Y_DISCOUNT
                        : null,
                    'pricing_rule_config' => $validated['presentationType'] === 'new' && $this->launchOfferEnabled
                        ? [
                            'version' => 1,
                            'buy_quantity' => (int) $validated['buyQuantity'],
                            'reward_quantity' => (int) $validated['rewardQuantity'],
                            'reward_discount_percentage' => (int) $validated['rewardDiscountPercentage'],
                            'max_applications_per_order' => filled($validated['maxApplicationsPerOrder'] ?? null)
                                ? (int) $validated['maxApplicationsPerOrder']
                                : null,
                            'apply_to_addons' => false,
                            'reward_scope' => 'same_product',
                        ]
                        : null,
                    'auto_apply' => $validated['presentationType'] === 'new' && $this->launchOfferEnabled,
                    'starts_on' => $validated['startsOn'],
                    'ends_on' => filled($validated['endsOn'] ?? null) ? $validated['endsOn'] : null,
                    'weekdays' => array_values(array_map('intval', $validated['weekdays'] ?? [])),
                    'fulfillment_modes' => $validated['presentationType'] === 'new' && ! $this->launchOfferEnabled
                        ? Promotion::FULFILLMENT_MODES
                        : array_values(array_unique($validated['fulfillmentModes'])),
                    'terms_and_conditions' => $validated['presentationType'] === 'new' && ! $this->launchOfferEnabled
                        ? null
                        : (trim((string) ($validated['termsAndConditions'] ?? '')) ?: null),
                    'show_on_pos' => $validated['presentationType'] === 'new' && ! $this->launchOfferEnabled ? false : $validated['showOnPos'],
                    'show_on_digital_menu' => $validated['showOnDigitalMenu'],
                    'show_on_kiosk' => $validated['presentationType'] === 'new' && ! $this->launchOfferEnabled ? false : $validated['showOnKiosk'],
                    'is_active' => $validated['isActive'],
                    'image' => $newImage ?: $promotion->image,
                ])->save();

                $promotion->groups()->delete();
                foreach (array_values($validated['groups'] ?? []) as $position => $groupData) {
                    $group = $promotion->groups()->create([
                        'name' => trim($groupData['name']),
                        'min_selections' => (int) $groupData['min_selections'],
                        'max_selections' => (int) $groupData['max_selections'],
                        'sort_order' => $position,
                    ]);
                    $group->products()->attach(collect($groupData['product_ids'])
                        ->mapWithKeys(fn ($productId, $productPosition) => [(int) $productId => ['sort_order' => $productPosition]])
                        ->all());
                }
            });
        } catch (\Throwable $exception) {
            if ($newImage) {
                Storage::disk('public')->delete($newImage);
            }
            throw $exception;
        }

        if ($newImage && $oldImage && $oldImage !== $newImage) {
            Storage::disk('public')->delete($oldImage);
        }

        unset($this->promotions);
        unset($this->calendarDays);
        $this->closeEditor();
        $this->dispatch('notify', type: 'success', message: 'La promoción quedó guardada y lista para su vigencia configurada.');
    }

    public function toggleActive(int $promotionId): void
    {
        $this->authorize('editar promociones');
        $promotion = Promotion::findOrFail($promotionId);
        $promotion->update(['is_active' => ! $promotion->is_active]);
        unset($this->promotions);
        unset($this->calendarDays);
    }

    public function delete(int $promotionId): void
    {
        $this->authorize('eliminar promociones');
        $promotion = Promotion::findOrFail($promotionId);
        $image = $promotion->image;
        $promotion->delete();
        if ($image) {
            Storage::disk('public')->delete($image);
        }
        unset($this->promotions);
        unset($this->calendarDays);
        $this->dispatch('notify', type: 'success', message: 'La promoción fue eliminada. Las ventas históricas conservan su detalle.');
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'description', 'shortDescription', 'presentationType',
            'primaryProductId', 'wizardStep', 'discountPercentage', 'price', 'startsOn', 'endsOn', 'weekdays',
            'launchOfferEnabled', 'buyQuantity', 'rewardQuantity', 'rewardDiscountPercentage', 'maxApplicationsPerOrder',
            'fulfillmentModes', 'termsAndConditions', 'showOnPos', 'showOnDigitalMenu', 'showOnKiosk',
            'isActive', 'image', 'currentImage', 'groups',
        ]);
        $this->fulfillmentModes = Promotion::FULFILLMENT_MODES;
        $this->showOnPos = true;
        $this->showOnDigitalMenu = true;
        $this->showOnKiosk = true;
        $this->isActive = true;
        $this->presentationType = 'promotion';
        $this->wizardStep = 1;
        $this->buyQuantity = 1;
        $this->rewardQuantity = 1;
        $this->rewardDiscountPercentage = 50;
        $this->resetValidation();
    }

    private function validateChannelFulfillmentCompatibility(): void
    {
        if ($this->presentationType === 'new' && ! $this->launchOfferEnabled) {
            return;
        }

        if ($this->showOnPos && array_intersect($this->fulfillmentModes, Promotion::POS_FULFILLMENT_MODES) === []) {
            throw ValidationException::withMessages([
                'channels' => 'El punto de venta solo admite Para llevar, Pasar a buscar o Entrega a domicilio.',
            ]);
        }

        if ($this->showOnKiosk && array_intersect($this->fulfillmentModes, Promotion::KIOSK_FULFILLMENT_MODES) === []) {
            throw ValidationException::withMessages([
                'channels' => 'El kiosco no maneja pedidos programados para pasar a buscar; habilita otra modalidad o desactiva ese canal.',
            ]);
        }
    }

    private function pricingRuleValidationRules(): array
    {
        return [
            'launchOfferEnabled' => ['boolean'],
            'buyQuantity' => ['required', 'integer', 'between:1,99'],
            'rewardQuantity' => ['required', 'integer', 'between:1,99'],
            'rewardDiscountPercentage' => ['required', 'integer', 'between:1,100'],
            'maxApplicationsPerOrder' => ['nullable', 'integer', 'between:1,99'],
        ];
    }

    public function render()
    {
        return view('livewire.admin.promotion-manager')->layout('layouts.app');
    }
}
