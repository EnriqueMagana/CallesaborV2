<?php

namespace App\Livewire\Pos\Concerns;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\InventoryItem;
use App\Models\Mesa;
use App\Models\MesaAssignment;
use App\Models\MesaGroup;
use App\Models\MesaService;
use App\Models\MesaSplit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use App\Models\OrderItemIngredient;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationItemAddon;
use App\Models\QuotationItemIngredient;
use App\Models\User;
use App\Services\DeliveryModulePolicy;
use App\Services\DeliveryWorkflow;
use App\Services\DiscountPricingService;
use App\Services\InventoryService;
use App\Services\ManualDeliveryAccountingService;
use App\Services\MesaServiceManager;
use App\Services\OrderOperationalDataService;
use App\Services\PromotionPricingService;
use App\Services\ThermalTicketRenderer;
use App\Support\BusinessTime;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use ManagesQuotations;

/**
 * Clientes, identidad fiscal y descuento de empleado.
 * 
 * Todo lo que responde a la pregunta "a quien le estoy vendiendo": buscar y
 * elegir cliente, capturar uno nuevo, y resolver a que empleado se le carga el
 * descuento. Los importes que eso produce se calculan en el checkout, no aqui.
 */
trait ManagesCustomers
{
    #[Computed]
    public function checkoutIdentitySearchResults()
    {
        $search = trim($this->customerSearch);
        if (mb_strlen($search) < 2) {
            return collect();
        }

        if ($this->checkoutIdentityType === 'employee') {
            if (! auth()->user()?->can('aplicar descuentos')) {
                return collect();
            }

            return User::query()
                ->whereNull('banned_at')
                ->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                })
                ->orderBy('name')
                ->limit(8)
                ->get(['id', 'name', 'email', 'phone']);
        }

        return Customer::query()
            ->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function selectedDiscountEmployee(): ?User
    {
        if (! $this->discountEmployeeId || ! auth()->user()?->can('aplicar descuentos')) {
            return null;
        }

        return User::query()
            ->whereKey($this->discountEmployeeId)
            ->whereNull('banned_at')
            ->first(['id', 'name', 'email', 'phone']);
    }

    public function selectCustomer(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $this->checkoutIdentityType = 'customer';
        $this->discountEmployeeId = null;
        $this->customerId = $customer->id;
        $this->customerName = $customer->name;
        $this->customerPhone = $customer->phone ?? '';
        $this->customerAddress = $customer->address ?? '';
        $this->customerNeighborhood = $customer->neighborhood ?? '';
        $this->customerReferences = $customer->references ?? '';
        $this->customerSearch = '';
        unset($this->checkoutIdentitySearchResults, $this->selectedDiscountEmployee);
        $this->saveCart();
        $this->syncPendingPaymentAmount();
    }

    public function clearCustomer(): void
    {
        $this->customerId = null;
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerAddress = '';
        $this->customerNeighborhood = '';
        $this->customerReferences = '';
        $this->customerSearch = '';
        unset($this->checkoutIdentitySearchResults);
        $this->saveCart();
        $this->syncPendingPaymentAmount();
    }

    public function setCheckoutIdentityType(string $type): void
    {
        abort_unless(in_array($type, ['customer', 'employee'], true), 404);
        if ($type === 'employee') {
            abort_unless(auth()->user()?->can('aplicar descuentos'), 403);
        }

        if ($this->checkoutIdentityType === $type) {
            return;
        }

        $this->checkoutIdentityType = $type;
        $this->customerId = null;
        $this->discountEmployeeId = null;
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerAddress = '';
        $this->customerNeighborhood = '';
        $this->customerReferences = '';
        $this->customerSearch = '';
        unset($this->checkoutIdentitySearchResults, $this->selectedDiscountEmployee);
        $this->saveCart();
        $this->syncPendingPaymentAmount();
    }

    public function selectCheckoutIdentity(int $id): void
    {
        if ($this->checkoutIdentityType === 'employee') {
            $this->selectDiscountEmployee($id);

            return;
        }

        $this->selectCustomer($id);
    }

    public function selectDiscountEmployee(int $employeeId): void
    {
        abort_unless(auth()->user()?->can('aplicar descuentos'), 403);

        $employee = User::query()
            ->whereKey($employeeId)
            ->whereNull('banned_at')
            ->firstOrFail(['id', 'name', 'email', 'phone']);

        $this->checkoutIdentityType = 'employee';
        $this->customerId = null;
        $this->discountEmployeeId = $employee->id;
        $this->customerName = $employee->name;
        $this->customerPhone = $employee->phone ?? '';
        $this->customerAddress = '';
        $this->customerNeighborhood = '';
        $this->customerReferences = '';
        $this->customerSearch = '';
        unset($this->checkoutIdentitySearchResults, $this->selectedDiscountEmployee);
        $this->saveCart();
        $this->syncPendingPaymentAmount();
    }

    public function clearDiscountEmployee(): void
    {
        abort_unless(auth()->user()?->can('aplicar descuentos'), 403);

        $this->discountEmployeeId = null;
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerAddress = '';
        $this->customerNeighborhood = '';
        $this->customerReferences = '';
        $this->customerSearch = '';
        unset($this->checkoutIdentitySearchResults, $this->selectedDiscountEmployee);
        $this->saveCart();
        $this->syncPendingPaymentAmount();
    }

    public function updatedCustomerSearch(): void
    {
        unset($this->checkoutIdentitySearchResults);
    }

    public function openAddCustomerModal(): void
    {
        $this->newCustomerName = '';
        $this->newCustomerPhone = '';
        $this->newCustomerEmail = '';
        $this->newCustomerAddress = '';
        $this->newCustomerNeighborhood = '';
        $this->newCustomerReferences = '';
        $this->showAddCustomerModal = true;
        $this->resetErrorBag();
    }

    public function saveNewCustomer(): void
    {
        abort_unless(auth()->user()?->can('crear ordenes'), 403);
        $this->validate([
            'newCustomerName' => 'required|string|max:120',
            'newCustomerPhone' => 'required|string|max:30',
            'newCustomerEmail' => 'nullable|email:rfc|max:160',
            'newCustomerNeighborhood' => 'required|string|max:120',
        ], [
            'newCustomerNeighborhood.required' => 'Escribe la colonia o zona del cliente.',
            'newCustomerNeighborhood.max' => 'La colonia o zona no puede superar 120 caracteres.',
        ]);

        $customer = Customer::create([
            'name' => $this->newCustomerName,
            'phone' => $this->newCustomerPhone,
            'email' => $this->newCustomerEmail ?: null,
            'address' => $this->newCustomerAddress ?: null,
            'neighborhood' => trim($this->newCustomerNeighborhood),
            'references' => $this->newCustomerReferences ?: null,
        ]);

        $this->selectCustomer($customer->id);
        $this->showAddCustomerModal = false;
        $this->dispatch('notify', type: 'success', message: 'Cliente registrado.');
    }

    private function validDiscountEmployeeId(): ?int
    {
        if (! $this->discountEmployeeId || ! auth()->user()?->can('aplicar descuentos')) {
            return null;
        }

        return User::query()->whereKey($this->discountEmployeeId)->whereNull('banned_at')->exists()
            ? $this->discountEmployeeId
            : null;
    }

    private function formattedCustomerDeliveryAddress(): ?string
    {
        $parts = array_filter([
            trim($this->customerAddress),
            trim($this->customerNeighborhood),
        ], fn (string $part) => $part !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function rememberMissingCustomerDeliveryData(): void
    {
        if (! $this->customerId) {
            return;
        }

        $customer = Customer::query()->lockForUpdate()->find($this->customerId);
        if (! $customer) {
            return;
        }

        $updates = [];
        if (blank($customer->address) && filled($this->customerAddress)) {
            $updates['address'] = trim($this->customerAddress);
        }
        if (blank($customer->neighborhood) && filled($this->customerNeighborhood)) {
            $updates['neighborhood'] = trim($this->customerNeighborhood);
        }
        if (blank($customer->references) && filled($this->customerReferences)) {
            $updates['references'] = trim($this->customerReferences);
        }

        if ($updates !== []) {
            $customer->update($updates);
        }
    }
}
