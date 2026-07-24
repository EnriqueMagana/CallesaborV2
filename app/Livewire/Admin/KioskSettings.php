<?php

namespace App\Livewire\Admin;

use App\Models\KioskTerminal;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class KioskSettings extends Component
{
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $name = '';

    public ?int $userId = null;

    public bool $isActive = true;

    public bool $allowDineIn = true;

    public bool $allowTakeaway = true;

    public bool $allowDelivery = false;

    public bool $requireCustomerPhone = false;

    public int $ordersPerMinute = 8;

    public int $autoResetSeconds = 45;

    public string $welcomeTitle = '¿Cómo quieres disfrutar tu pedido?';

    public string $welcomeMessage = 'Elige una opción para comenzar. Podrás personalizar cada producto antes de confirmar.';

    public string $paymentInstructions = 'Paga en caja mostrando tu número de pedido.';

    public string $successMessage = 'Tu pedido fue recibido y pronto comenzaremos a prepararlo.';

    public bool $promotionEnabled = false;

    public string $promotionBadge = 'Especiales de la casa';

    public string $promotionTitle = 'Descubre algo delicioso';

    public string $promotionMessage = 'Conoce nuestras recomendaciones y encuentra tu próximo favorito.';

    public array $featuredProductIds = [];

    public array $promotionPrices = [];

    public array $promotionDiscounts = [];

    public array $promotionLabels = [];

    public string $productSearch = '';

    public ?string $issuedUrl = null;

    public ?string $issuedTerminalName = null;

    public function mount(): void
    {
        $this->authorizeManage();
    }

    #[Computed]
    public function terminals()
    {
        return KioskTerminal::query()
            ->with('user:id,name,email')
            ->withCount(['orders', 'orders as today_orders_count' => fn ($query) => $query->whereDate('created_at', today())])
            ->latest()
            ->get();
    }

    #[Computed]
    public function users()
    {
        return User::query()->orderBy('name')->get(['id', 'name', 'email']);
    }

    #[Computed]
    public function promotionalProducts()
    {
        return Product::query()
            ->with('category:id,name')
            ->where('is_active', true)
            ->when(trim($this->productSearch), function ($query): void {
                $term = '%'.trim($this->productSearch).'%';
                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'category_id', 'name', 'description', 'image', 'price']);
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => KioskTerminal::count(),
            'active' => KioskTerminal::where('is_active', true)->count(),
            'today' => Order::where('source', 'kiosk')->whereDate('created_at', today())->count(),
            'sales' => (float) Order::where('source', 'kiosk')->whereDate('created_at', today())->sum('total'),
        ];
    }

    public function createTerminal(): void
    {
        $this->authorizeManage();
        $this->resetForm();
        $this->userId = auth()->id();
        $this->showForm = true;
    }

    public function editTerminal(int $terminalId): void
    {
        $this->authorizeManage();
        $terminal = KioskTerminal::with('productPromotions')->findOrFail($terminalId);
        $this->editingId = $terminal->id;
        $this->name = $terminal->name;
        $this->userId = $terminal->user_id;
        $this->isActive = $terminal->is_active;
        $this->allowDineIn = $terminal->allow_dine_in;
        $this->allowTakeaway = $terminal->allow_takeaway;
        $this->allowDelivery = $terminal->allow_delivery;
        $this->requireCustomerPhone = $terminal->require_customer_phone;
        $this->ordersPerMinute = $terminal->orders_per_minute;
        $this->autoResetSeconds = $terminal->auto_reset_seconds;
        $this->welcomeTitle = $terminal->welcome_title;
        $this->welcomeMessage = $terminal->welcome_message;
        $this->paymentInstructions = $terminal->payment_instructions;
        $this->successMessage = $terminal->success_message;
        $this->promotionEnabled = $terminal->promotion_enabled;
        $this->promotionBadge = $terminal->promotion_badge;
        $this->promotionTitle = $terminal->promotion_title;
        $this->promotionMessage = $terminal->promotion_message;
        $this->featuredProductIds = $terminal->productPromotions->pluck('product_id')->map(fn ($id) => (int) $id)->all();
        $this->promotionPrices = $terminal->productPromotions
            ->filter(fn ($promotion) => $promotion->promotional_price !== null)
            ->mapWithKeys(fn ($promotion) => [$promotion->product_id => $promotion->promotional_price])
            ->all();
        $this->promotionDiscounts = $terminal->productPromotions
            ->mapWithKeys(fn ($promotion) => [$promotion->product_id => $promotion->promotional_price !== null])
            ->all();
        $this->promotionLabels = $terminal->productPromotions
            ->filter(fn ($promotion) => filled($promotion->label))
            ->mapWithKeys(fn ($promotion) => [$promotion->product_id => $promotion->label])
            ->all();
        $this->showForm = true;
        $this->resetValidation();
    }

    public function toggleFeaturedProduct(int $productId): void
    {
        $this->authorizeManage();
        abort_unless(Product::query()->whereKey($productId)->where('is_active', true)->exists(), 422);

        $selected = array_map('intval', $this->featuredProductIds);
        if (in_array($productId, $selected, true)) {
            $this->featuredProductIds = array_values(array_diff($selected, [$productId]));
            unset($this->promotionPrices[$productId], $this->promotionDiscounts[$productId], $this->promotionLabels[$productId]);
            if ($this->featuredProductIds === []) {
                $this->promotionEnabled = false;
            }
            $this->resetErrorBag('featuredProductIds');

            return;
        }

        if (count($selected) >= 6) {
            $this->addError('featuredProductIds', 'Puedes destacar hasta 6 productos por kiosco.');

            return;
        }

        $selected[] = $productId;
        $this->featuredProductIds = $selected;
        $this->promotionEnabled = true;
        $this->promotionDiscounts[$productId] = false;
        $this->resetErrorBag('featuredProductIds');
    }

    public function saveTerminal(): void
    {
        $this->authorizeManage();
        $this->validate($this->rules(), [
            'name.required' => 'Escribe un nombre para identificar el terminal.',
            'userId.required' => 'Selecciona una persona responsable.',
            'allowDineIn.accepted' => 'Debe habilitarse al menos una modalidad.',
        ]);

        if (! $this->allowDineIn && ! $this->allowTakeaway && ! $this->allowDelivery) {
            $this->addError('fulfillment', 'Habilita “Comer aquí”, “Para llevar” o “Para domicilio”.');

            return;
        }

        if ($this->promotionEnabled && empty($this->featuredProductIds)) {
            $this->addError('featuredProductIds', 'Selecciona al menos un producto para mostrar el escaparate.');

            return;
        }

        $selectedProducts = Product::query()
            ->whereIn('id', array_map('intval', $this->featuredProductIds))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($selectedProducts->count() !== count(array_unique(array_map('intval', $this->featuredProductIds)))) {
            $this->addError('featuredProductIds', 'Uno de los productos seleccionados ya no está disponible.');

            return;
        }

        foreach ($selectedProducts as $product) {
            $discountEnabled = (bool) ($this->promotionDiscounts[$product->id] ?? false);
            $promotionPrice = $this->promotionPrices[$product->id] ?? null;

            if ($discountEnabled && ($promotionPrice === null || $promotionPrice === '')) {
                $this->addError("promotionPrices.{$product->id}", 'Escribe el precio especial o desactiva el descuento.');

                return;
            }

            if ($discountEnabled && (float) $promotionPrice >= (float) $product->price) {
                $this->addError("promotionPrices.{$product->id}", 'El precio promocional debe ser menor al precio normal.');

                return;
            }
        }

        $data = [
            'name' => trim($this->name),
            'user_id' => $this->userId,
            'is_active' => $this->isActive,
            'allow_dine_in' => $this->allowDineIn,
            'allow_takeaway' => $this->allowTakeaway,
            'allow_delivery' => $this->allowDelivery,
            'require_customer_phone' => $this->requireCustomerPhone,
            'orders_per_minute' => $this->ordersPerMinute,
            'auto_reset_seconds' => $this->autoResetSeconds,
            'welcome_title' => trim($this->welcomeTitle),
            'welcome_message' => trim($this->welcomeMessage),
            'payment_instructions' => trim($this->paymentInstructions),
            'success_message' => trim($this->successMessage),
            'promotion_enabled' => $this->promotionEnabled,
            'promotion_badge' => trim($this->promotionBadge),
            'promotion_title' => trim($this->promotionTitle),
            'promotion_message' => trim($this->promotionMessage),
        ];

        if ($this->editingId) {
            $terminal = KioskTerminal::findOrFail($this->editingId);
            $terminal->update($data);
            session()->flash('kioskNotice', 'Los ajustes del terminal fueron guardados.');
        } else {
            $token = Str::random(64);
            $terminal = KioskTerminal::create($data + [
                'token_hash' => hash('sha256', $token),
                'token_secret' => $token,
                'token_hint' => Str::substr($token, -8),
            ]);
            $this->issuedUrl = route('kiosk.order', $token);
            $this->issuedTerminalName = $terminal->name;
        }

        $this->syncPromotions($terminal);
        $this->showForm = false;
        unset($this->terminals, $this->stats);
    }

    public function rotateToken(int $terminalId): void
    {
        $this->authorizeManage();
        $terminal = KioskTerminal::findOrFail($terminalId);
        $token = Str::random(64);
        $terminal->update([
            'token_hash' => hash('sha256', $token),
            'token_secret' => $token,
            'token_hint' => Str::substr($token, -8),
            'is_active' => true,
        ]);

        $this->issuedUrl = route('kiosk.order', $token);
        $this->issuedTerminalName = $terminal->name;
        unset($this->terminals, $this->stats);
    }

    public function confirmRotateToken(int $terminalId): void
    {
        $this->authorizeManage();
        $terminal = KioskTerminal::findOrFail($terminalId);
        $this->dispatch('open-confirm',
            type: 'warning',
            title: 'Generar una nueva URL',
            message: 'La URL actual de <strong>'.e($terminal->name).'</strong> dejará de funcionar inmediatamente. Tendrás que abrir la nueva URL en el kiosco.',
            action: 'rotateKioskToken',
            params: [$terminal->id],
            confirmText: 'Sí, generar nueva URL',
            cancelText: 'Conservar URL actual',
        );
    }

    #[On('modal-confirmed')]
    public function handleModalConfirmed(string $action, array $params = []): void
    {
        if ($action === 'rotateKioskToken' && isset($params[0])) {
            $this->rotateToken((int) $params[0]);
        }
    }

    public function toggleTerminal(int $terminalId): void
    {
        $this->authorizeManage();
        $terminal = KioskTerminal::findOrFail($terminalId);
        $terminal->update(['is_active' => ! $terminal->is_active]);
        session()->flash('kioskNotice', $terminal->is_active ? 'Terminal activado.' : 'Terminal pausado.');
        unset($this->terminals, $this->stats);
    }

    public function dismissIssuedUrl(): void
    {
        $this->issuedUrl = null;
        $this->issuedTerminalName = null;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'userId' => ['required', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'isActive' => ['boolean'],
            'allowDineIn' => ['boolean'],
            'allowTakeaway' => ['boolean'],
            'allowDelivery' => ['boolean'],
            'requireCustomerPhone' => ['boolean'],
            'ordersPerMinute' => ['required', 'integer', 'min:1', 'max:60'],
            'autoResetSeconds' => ['required', 'integer', 'min:10', 'max:600'],
            'welcomeTitle' => ['required', 'string', 'max:100'],
            'welcomeMessage' => ['required', 'string', 'max:240'],
            'paymentInstructions' => ['required', 'string', 'max:180'],
            'successMessage' => ['required', 'string', 'max:180'],
            'promotionEnabled' => ['boolean'],
            'promotionBadge' => ['required', 'string', 'max:60'],
            'promotionTitle' => ['required', 'string', 'max:120'],
            'promotionMessage' => ['required', 'string', 'max:240'],
            'featuredProductIds' => ['array', 'max:6'],
            'featuredProductIds.*' => ['integer', Rule::exists('products', 'id')->where('is_active', true)],
            'promotionPrices' => ['array'],
            'promotionPrices.*' => ['nullable', 'numeric', 'min:0.01'],
            'promotionDiscounts' => ['array'],
            'promotionDiscounts.*' => ['boolean'],
            'promotionLabels' => ['array'],
            'promotionLabels.*' => ['nullable', 'string', 'max:40'],
        ];
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'userId', 'showForm', 'issuedUrl', 'issuedTerminalName',
            'featuredProductIds', 'promotionPrices', 'promotionDiscounts', 'promotionLabels', 'productSearch',
        ]);
        $this->isActive = true;
        $this->allowDineIn = true;
        $this->allowTakeaway = true;
        $this->allowDelivery = false;
        $this->requireCustomerPhone = false;
        $this->ordersPerMinute = 8;
        $this->autoResetSeconds = 45;
        $this->welcomeTitle = '¿Cómo quieres disfrutar tu pedido?';
        $this->welcomeMessage = 'Elige una opción para comenzar. Podrás personalizar cada producto antes de confirmar.';
        $this->paymentInstructions = 'Paga en caja mostrando tu número de pedido.';
        $this->successMessage = 'Tu pedido fue recibido y pronto comenzaremos a prepararlo.';
        $this->promotionEnabled = false;
        $this->promotionBadge = 'Especiales de la casa';
        $this->promotionTitle = 'Descubre algo delicioso';
        $this->promotionMessage = 'Conoce nuestras recomendaciones y encuentra tu próximo favorito.';
        $this->resetValidation();
    }

    private function syncPromotions(KioskTerminal $terminal): void
    {
        $selectedIds = array_values(array_unique(array_map('intval', $this->featuredProductIds)));

        $obsoletePromotions = $terminal->productPromotions();
        if ($selectedIds) {
            $obsoletePromotions->whereNotIn('product_id', $selectedIds);
        }
        $obsoletePromotions->delete();

        foreach ($selectedIds as $index => $productId) {
            $discountEnabled = (bool) ($this->promotionDiscounts[$productId] ?? false);
            $price = $this->promotionPrices[$productId] ?? null;
            $terminal->productPromotions()->updateOrCreate(
                ['product_id' => $productId],
                [
                    'promotional_price' => ! $discountEnabled || $price === null || $price === ''
                        ? null
                        : round((float) $price, 2),
                    'label' => trim((string) ($this->promotionLabels[$productId] ?? '')) ?: null,
                    'sort_order' => $index,
                ],
            );
        }
    }

    private function authorizeManage(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->hasAnyRole(['owner', 'super-admin']) || $user->can('gestionar kioscos')), 403);
    }

    public function render()
    {
        return view('livewire.admin.kiosk-settings');
    }
}
