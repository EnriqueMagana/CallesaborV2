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

    public array $eligibleProductIds = [];

    public int $wizardStep = 1;

    public string $previewDevice = 'mobile';

    public string $discountPercentage = '';

    public string $price = '';

    public string $pricingMechanic = 'fixed_price';

    public bool $launchOfferEnabled = false;

    public int $buyQuantity = 1;

    public int $rewardQuantity = 1;

    public int $rewardDiscountPercentage = 50;

    public string $maxApplicationsPerOrder = '';

    public string $startsOn = '';

    public string $endsOn = '';

    public array $weekdays = [];

    public string $scheduleType = 'date_range';

    public int $monthlyDay = 1;

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
                $this->wizardStep = 3;
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
            ->get(['id', 'category_id', 'name', 'image', 'price']);
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
        $this->pricingMechanic = match ($promotion->pricing_rule_type) {
            Promotion::PRICING_RULE_PERCENTAGE_DISCOUNT => 'percentage_discount',
            Promotion::PRICING_RULE_FIXED_PRODUCT_PRICE => 'fixed_product_price',
            Promotion::PRICING_RULE_BUY_X_GET_Y_DISCOUNT => match (true) {
                $pricingRule['buy_quantity'] === 1 && $pricingRule['reward_quantity'] === 1 && $pricingRule['reward_discount_percentage'] === 100 => 'two_for_one',
                $pricingRule['buy_quantity'] === 1 && $pricingRule['reward_quantity'] === 1 && $pricingRule['reward_discount_percentage'] === 50 => 'second_half',
                default => 'custom_quantity',
            },
            default => $promotion->isProductLaunch() ? 'catalog_price' : 'fixed_price',
        };
        $this->price = (string) $promotion->price;
        $this->startsOn = $promotion->starts_on->toDateString();
        $this->endsOn = $promotion->ends_on?->toDateString() ?? '';
        $this->weekdays = array_map('intval', $promotion->weekdays ?? []);
        $this->scheduleType = $promotion->recurrence_type ?: ($this->weekdays !== [] ? 'weekdays' : 'date_range');
        $this->monthlyDay = (int) ($promotion->monthly_day ?: 1);
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
        $this->eligibleProductIds = $promotion->groups
            ->flatMap(fn ($group) => $group->products->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        if ($this->usesEligibleProductGroup() && $this->eligibleProductIds === [] && $this->primaryProductId) {
            $this->eligibleProductIds = [$this->primaryProductId];
        }
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
            $this->pricingMechanic = 'catalog_price';
            $this->launchOfferEnabled = false;
            $this->fulfillmentModes = Promotion::FULFILLMENT_MODES;
            $this->groups = [];
            $this->eligibleProductIds = [];

            return;
        }

        $this->primaryProductId = null;
        $this->eligibleProductIds = [];
        $this->pricingMechanic = $type === 'discount' ? 'percentage_discount' : 'fixed_price';
        $this->updatedPricingMechanic($this->pricingMechanic);
        $this->showOnPos = true;
        $this->showOnKiosk = true;
    }

    public function updatedPricingMechanic(string $mechanic): void
    {
        if (! in_array($mechanic, ['catalog_price', 'fixed_price', 'fixed_product_price', 'percentage_discount', 'two_for_one', 'second_half', 'custom_quantity'], true)) {
            return;
        }

        $this->launchOfferEnabled = $this->isAutomaticMechanic();
        if ($mechanic === 'two_for_one') {
            [$this->buyQuantity, $this->rewardQuantity, $this->rewardDiscountPercentage] = [1, 1, 100];
        } elseif ($mechanic === 'second_half') {
            [$this->buyQuantity, $this->rewardQuantity, $this->rewardDiscountPercentage] = [1, 1, 50];
        }

        if ($mechanic === 'fixed_price' && $this->groups === []) {
            $this->addGroup();
        }
        if ($this->usesEligibleProductGroup()) {
            if ($this->eligibleProductIds === [] && $this->primaryProductId) {
                $this->eligibleProductIds = [$this->primaryProductId];
            }
            $this->groups = [];
        } elseif ($mechanic !== 'fixed_price') {
            $this->groups = [];
            $this->eligibleProductIds = [];
        }
        $this->resetValidation();
    }

    public function updatedEligibleProductIds(): void
    {
        $this->eligibleProductIds = collect($this->eligibleProductIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $this->primaryProductId = $this->eligibleProductIds[0] ?? null;
        if ($this->primaryProductId) {
            $this->updatedPrimaryProductId($this->primaryProductId);
        }
    }

    public function updatedLaunchOfferEnabled(bool $enabled): void
    {
        if ($this->presentationType !== 'new') {
            return;
        }

        $this->pricingMechanic = $enabled ? 'second_half' : 'catalog_price';
        $this->updatedPricingMechanic($this->pricingMechanic);
    }

    public function updatedDiscountPercentage(): void
    {
        if ($this->primaryProductId) {
            $this->updatedPrimaryProductId($this->primaryProductId);
        }
    }

    public function updatedScheduleType(string $type): void
    {
        if (! in_array($type, Promotion::RECURRENCE_TYPES, true)) {
            return;
        }
        if ($type !== 'weekdays') {
            $this->weekdays = [];
        }
        $this->resetValidation();
    }

    public function updatedPreviewDevice(string $device): void
    {
        if (! in_array($device, ['mobile', 'tablet', 'desktop'], true)) {
            $this->previewDevice = 'mobile';
        }
    }

    public function updatedPrimaryProductId($productId): void
    {
        if ((! $this->isAutomaticMechanic() && $this->presentationType !== 'new') || ! $productId) {
            return;
        }

        $product = Product::query()->where('is_active', true)->find((int) $productId);
        if (! $product) {
            return;
        }

        if ($this->presentationType === 'new') {
            $this->name = $product->name;
            $this->description = (string) $product->description;
            $this->shortDescription = str($product->description ?: "Descubre {$product->name}, nuevo en nuestro menú.")->limit(160, '')->toString();
        }
        if ($this->pricingMechanic === 'percentage_discount' && is_numeric($this->discountPercentage)) {
            $this->price = number_format((float) $product->price * (1 - ((int) $this->discountPercentage / 100)), 2, '.', '');
        } elseif ($this->pricingMechanic !== 'fixed_product_price') {
            $this->price = (string) $product->price;
        }
    }

    public function nextWizardStep(): void
    {
        if ($this->wizardStep === 1) {
            $this->validateOnly('presentationType', [
                'presentationType' => ['required', Rule::in(Promotion::PRESENTATION_TYPES)],
            ]);

            $this->wizardStep = $this->presentationType === 'new' ? 3 : 2;
            $this->resetValidation();

            return;
        }

        if ($this->wizardStep === 2) {
            $rules = [
                'pricingMechanic' => ['required', Rule::in($this->allowedPricingMechanics())],
            ];
            if ($this->pricingMechanic === 'fixed_price') {
                $rules['price'] = ['required', 'numeric', 'min:0.01', 'max:999999.99'];
                $rules += [
                    'groups' => ['required', 'array', 'min:1', 'max:12'],
                    'groups.*.name' => ['required', 'string', 'max:100'],
                    'groups.*.min_selections' => ['required', 'integer', 'min:0', 'max:99'],
                    'groups.*.max_selections' => ['required', 'integer', 'min:1', 'max:99'],
                    'groups.*.product_ids' => ['required', 'array', 'min:1'],
                    'groups.*.product_ids.*' => ['required', 'integer', 'distinct', 'exists:products,id'],
                ];
            } elseif ($this->usesEligibleProductGroup()) {
                $rules += [
                    'eligibleProductIds' => ['required', 'array', 'min:1', 'max:100'],
                    'eligibleProductIds.*' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('is_active', true)],
                ];
                $rules += $this->pricingRuleValidationRules();
            } else {
                $rules['primaryProductId'] = ['required', Rule::exists('products', 'id')->where('is_active', true)];
                if ($this->pricingMechanic === 'percentage_discount') {
                    $rules['discountPercentage'] = ['required', 'integer', 'between:1,99'];
                } elseif ($this->pricingMechanic === 'fixed_product_price') {
                    $rules['price'] = ['required', 'numeric', 'min:0.01', 'max:999999.99'];
                } else {
                    $rules += $this->pricingRuleValidationRules();
                }
            }
            $this->validate($rules);
            $this->validateFixedProductPrice();
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
                'name' => ['required', 'string', 'max:120'],
                'shortDescription' => ['required', 'string', 'min:10', 'max:160'],
                'description' => ['nullable', 'string', 'max:500'],
                'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            ];
            if ($this->presentationType === 'new') {
                $rules['primaryProductId'] = ['required', Rule::exists('products', 'id')->where('is_active', true)];
            }
            $this->validate($rules);
            if ($this->presentationType === 'promotion' && ! $this->image && ! $this->currentImage) {
                throw ValidationException::withMessages(['image' => 'Carga una imagen horizontal para el banner promocional.']);
            }
            if (in_array($this->presentationType, ['new', 'discount'], true)) {
                $product = Product::query()->where('is_active', true)->find($this->primaryProductId);
                if (! $this->image && ! $this->currentImage && ! $product?->image) {
                    throw ValidationException::withMessages(['image' => 'Carga una imagen o agrega una al producto seleccionado para poder mostrar su tarjeta.']);
                }
            }
        }

        if ($this->wizardStep === 4) {
            $rules = [
                'startsOn' => ['required', 'date'],
                'endsOn' => ['nullable', 'date', 'after_or_equal:startsOn'],
                'scheduleType' => ['required', Rule::in(Promotion::RECURRENCE_TYPES)],
                'weekdays' => ['array'],
                'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
                'monthlyDay' => ['nullable', 'integer', 'between:1,31'],
            ];
            if ($this->scheduleType === 'weekdays') {
                $rules['weekdays'] = ['required', 'array', 'min:1'];
            }
            if ($this->scheduleType === 'monthly') {
                $rules['monthlyDay'] = ['required', 'integer', 'between:1,31'];
            }
            if ($this->presentationType !== 'new' || $this->launchOfferEnabled) {
                $rules += [
                    'fulfillmentModes' => ['required', 'array', 'min:1'],
                    'fulfillmentModes.*' => ['required', Rule::in(Promotion::FULFILLMENT_MODES), 'distinct'],
                    'termsAndConditions' => ['nullable', 'string', 'max:1000'],
                ];
            }
            $this->validate($rules);
            $this->validateChannelFulfillmentCompatibility();
            if (! $this->showOnPos && ! $this->showOnDigitalMenu && ! $this->showOnKiosk) {
                throw ValidationException::withMessages(['channels' => 'Selecciona al menos un canal de publicación.']);
            }
        }

        $this->wizardStep = min(5, $this->wizardStep + 1);
    }

    public function previousWizardStep(): void
    {
        $this->wizardStep = $this->presentationType === 'new' && $this->wizardStep === 3
            ? 1
            : max(1, $this->wizardStep - 1);
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

        if ($this->usesEligibleProductGroup()) {
            $this->updatedEligibleProductIds();
        }

        if (($this->presentationType === 'new' || $this->isAutomaticMechanic()) && $this->primaryProductId) {
            $productPrice = Product::query()->where('is_active', true)->find($this->primaryProductId)?->price;
            if ($productPrice !== null) {
                if ($this->pricingMechanic === 'percentage_discount') {
                    $this->price = number_format((float) $productPrice * (1 - ((int) $this->discountPercentage / 100)), 2, '.', '');
                } elseif ($this->pricingMechanic !== 'fixed_product_price') {
                    $this->price = (string) $productPrice;
                }
            }
        }

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'shortDescription' => ['required', 'string', 'min:10', 'max:160'],
            'presentationType' => ['required', Rule::in(Promotion::PRESENTATION_TYPES)],
            'pricingMechanic' => ['required', Rule::in($this->allowedPricingMechanics())],
            'primaryProductId' => ['nullable'],
            'discountPercentage' => ['nullable', 'integer', 'between:1,99'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'startsOn' => ['required', 'date'],
            'endsOn' => ['nullable', 'date', 'after_or_equal:startsOn'],
            'scheduleType' => ['required', Rule::in(Promotion::RECURRENCE_TYPES)],
            'weekdays' => ['array'],
            'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'monthlyDay' => ['nullable', 'integer', 'between:1,31'],
            'fulfillmentModes' => ['array'],
            'termsAndConditions' => ['nullable', 'string', 'max:1000'],
            'showOnPos' => ['boolean'],
            'showOnDigitalMenu' => ['boolean'],
            'showOnKiosk' => ['boolean'],
            'isActive' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
        if ($this->pricingMechanic === 'catalog_price') {
            $rules['primaryProductId'] = ['required', Rule::exists('products', 'id')->where('is_active', true)];
        } elseif ($this->pricingMechanic === 'fixed_price') {
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
        } elseif ($this->usesEligibleProductGroup()) {
            $rules += [
                'primaryProductId' => ['required', Rule::exists('products', 'id')->where('is_active', true)],
                'eligibleProductIds' => ['required', 'array', 'min:1', 'max:100'],
                'eligibleProductIds.*' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('is_active', true)],
                'fulfillmentModes' => ['required', 'array', 'min:1'],
                'fulfillmentModes.*' => ['required', Rule::in(Promotion::FULFILLMENT_MODES), 'distinct'],
            ];
            $rules += $this->pricingRuleValidationRules();
        } else {
            $rules['primaryProductId'] = ['required', Rule::exists('products', 'id')->where('is_active', true)];
            $rules += [
                'fulfillmentModes' => ['required', 'array', 'min:1'],
                'fulfillmentModes.*' => ['required', Rule::in(Promotion::FULFILLMENT_MODES), 'distinct'],
            ];
            if ($this->pricingMechanic === 'percentage_discount') {
                $rules['discountPercentage'] = ['required', 'integer', 'between:1,99'];
            } elseif ($this->pricingMechanic === 'fixed_product_price') {
                $rules['price'] = ['required', 'numeric', 'min:0.01', 'max:999999.99'];
            } else {
                $rules += $this->pricingRuleValidationRules();
            }
        }
        if ($this->scheduleType === 'weekdays') {
            $rules['weekdays'] = ['required', 'array', 'min:1'];
        }
        if ($this->scheduleType === 'monthly') {
            $rules['monthlyDay'] = ['required', 'integer', 'between:1,31'];
        }

        $validated = $this->validate($rules, [], [
            'name' => 'nombre',
            'price' => 'precio promocional',
            'startsOn' => 'fecha de inicio',
            'endsOn' => 'fecha de fin',
            'image' => 'imagen',
        ]);
        $this->validateFixedProductPrice();
        $this->validateChannelFulfillmentCompatibility();

        if (in_array($this->presentationType, ['new', 'discount'], true)) {
            $selectedProduct = Product::query()->where('is_active', true)->findOrFail((int) $validated['primaryProductId']);
            if (! $this->image && ! $this->currentImage && ! $selectedProduct->image) {
                throw ValidationException::withMessages(['primaryProductId' => 'El producto necesita una imagen para publicar su tarjeta.']);
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
                    'primary_product_id' => $validated['presentationType'] === 'new' || $this->isAutomaticMechanic()
                        ? (int) $validated['primaryProductId']
                        : null,
                    'price' => $validated['price'],
                    'discount_percentage' => ($this->pricingMechanic === 'percentage_discount' || ($validated['presentationType'] === 'discount' && filled($validated['discountPercentage'] ?? null)))
                        ? (int) $validated['discountPercentage']
                        : null,
                    'pricing_rule_type' => match ($this->pricingMechanic) {
                        'percentage_discount' => Promotion::PRICING_RULE_PERCENTAGE_DISCOUNT,
                        'fixed_product_price' => Promotion::PRICING_RULE_FIXED_PRODUCT_PRICE,
                        'two_for_one', 'second_half', 'custom_quantity' => Promotion::PRICING_RULE_BUY_X_GET_Y_DISCOUNT,
                        default => null,
                    },
                    'pricing_rule_config' => $this->isAutomaticMechanic()
                        ? ($this->pricingMechanic === 'percentage_discount' ? [
                            'version' => 1,
                            'discount_percentage' => (int) $validated['discountPercentage'],
                            'apply_to_addons' => false,
                        ] : ($this->pricingMechanic === 'fixed_product_price' ? [
                            'version' => 1,
                            'fixed_price' => (float) $validated['price'],
                            'apply_to_addons' => false,
                        ] : [
                            'version' => 1,
                            'buy_quantity' => (int) $validated['buyQuantity'],
                            'reward_quantity' => (int) $validated['rewardQuantity'],
                            'reward_discount_percentage' => (int) $validated['rewardDiscountPercentage'],
                            'max_applications_per_order' => filled($validated['maxApplicationsPerOrder'] ?? null)
                                ? (int) $validated['maxApplicationsPerOrder']
                                : null,
                            'apply_to_addons' => false,
                            'reward_scope' => $this->usesEligibleProductGroup() ? 'eligible_group' : 'same_product',
                        ]))
                        : null,
                    'auto_apply' => $this->isAutomaticMechanic(),
                    'starts_on' => $validated['startsOn'],
                    'ends_on' => filled($validated['endsOn'] ?? null) ? $validated['endsOn'] : null,
                    'recurrence_type' => $validated['scheduleType'],
                    'weekdays' => $validated['scheduleType'] === 'weekdays'
                        ? array_values(array_map('intval', $validated['weekdays'] ?? []))
                        : [],
                    'monthly_day' => $validated['scheduleType'] === 'monthly' ? (int) $validated['monthlyDay'] : null,
                    'fulfillment_modes' => $this->pricingMechanic === 'catalog_price'
                        ? Promotion::FULFILLMENT_MODES
                        : array_values(array_unique($validated['fulfillmentModes'])),
                    'terms_and_conditions' => $this->pricingMechanic === 'catalog_price'
                        ? null
                        : (trim((string) ($validated['termsAndConditions'] ?? '')) ?: null),
                    'show_on_pos' => $this->pricingMechanic === 'catalog_price' ? false : $validated['showOnPos'],
                    'show_on_digital_menu' => $validated['showOnDigitalMenu'],
                    'show_on_kiosk' => $this->pricingMechanic === 'catalog_price' ? false : $validated['showOnKiosk'],
                    'is_active' => $validated['isActive'],
                    'image' => $newImage ?: $promotion->image,
                ])->save();

                $promotion->groups()->delete();
                $groupPayloads = $validated['groups'] ?? [];
                if ($this->usesEligibleProductGroup()) {
                    $groupPayloads = [[
                        'name' => 'Productos elegibles',
                        'min_selections' => 1,
                        'max_selections' => 99,
                        'product_ids' => $validated['eligibleProductIds'],
                    ]];
                }
                foreach (array_values($groupPayloads) as $position => $groupData) {
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
            'primaryProductId', 'eligibleProductIds', 'wizardStep', 'previewDevice', 'discountPercentage', 'price', 'pricingMechanic',
            'startsOn', 'endsOn', 'weekdays', 'scheduleType', 'monthlyDay',
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
        $this->pricingMechanic = 'fixed_price';
        $this->scheduleType = 'date_range';
        $this->monthlyDay = 1;
        $this->wizardStep = 1;
        $this->previewDevice = 'mobile';
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

    private function validateFixedProductPrice(): void
    {
        if ($this->pricingMechanic !== 'fixed_product_price' || ! $this->primaryProductId || ! is_numeric($this->price)) {
            return;
        }

        $catalogPrice = Product::query()->where('is_active', true)->find((int) $this->primaryProductId)?->price;
        if ($catalogPrice !== null && (float) $this->price >= (float) $catalogPrice) {
            throw ValidationException::withMessages([
                'price' => 'El precio especial debe ser menor que el precio actual del producto ($'.number_format((float) $catalogPrice, 2).').',
            ]);
        }
    }

    private function isAutomaticMechanic(): bool
    {
        return in_array($this->pricingMechanic, ['fixed_product_price', 'percentage_discount', 'two_for_one', 'second_half', 'custom_quantity'], true);
    }

    private function usesEligibleProductGroup(): bool
    {
        return in_array($this->pricingMechanic, ['two_for_one', 'second_half', 'custom_quantity'], true);
    }

    private function allowedPricingMechanics(): array
    {
        return match ($this->presentationType) {
            'new' => ['catalog_price', 'fixed_product_price', 'percentage_discount', 'two_for_one', 'second_half', 'custom_quantity'],
            'discount' => ['fixed_product_price', 'percentage_discount'],
            default => ['fixed_price', 'two_for_one', 'second_half', 'custom_quantity'],
        };
    }

    public function render()
    {
        return view('livewire.admin.promotion-manager')->layout('layouts.app');
    }
}
