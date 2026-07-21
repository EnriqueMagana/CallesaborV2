<?php

namespace App\Livewire\Admin;

use App\Models\KioskTerminal;
use App\Models\Order;
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
        $terminal = KioskTerminal::findOrFail($terminalId);
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
        $this->showForm = true;
        $this->resetValidation();
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
        ];

        if ($this->editingId) {
            KioskTerminal::findOrFail($this->editingId)->update($data);
            session()->flash('kioskNotice', 'Los ajustes del terminal fueron guardados.');
        } else {
            $token = Str::random(64);
            $terminal = KioskTerminal::create($data + [
                'token_hash' => hash('sha256', $token),
                'token_hint' => Str::substr($token, -8),
            ]);
            $this->issuedUrl = route('kiosk.order', $token);
            $this->issuedTerminalName = $terminal->name;
        }

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
        ];
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'userId', 'showForm', 'issuedUrl', 'issuedTerminalName',
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
        $this->resetValidation();
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
