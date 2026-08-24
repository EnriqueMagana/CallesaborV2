<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class CustomerManager extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public string $modalMode = 'view';

    public ?int $selectedCustomerId = null;

    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $neighborhood = '';

    public string $references = '';

    protected string $paginationTheme = 'bootstrap';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('ver clientes'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorizeAction('crear clientes');
        $this->resetForm();
        $this->selectedCustomerId = null;
        $this->modalMode = 'create';
        $this->showModal = true;
    }

    public function openDetails(int $customerId): void
    {
        $this->authorizeAction('ver clientes');
        Customer::query()->findOrFail($customerId);
        $this->resetForm();
        $this->selectedCustomerId = $customerId;
        $this->modalMode = 'view';
        $this->showModal = true;
    }

    public function openEdit(int $customerId): void
    {
        $this->authorizeAction('editar clientes');
        $customer = Customer::query()->findOrFail($customerId);

        $this->selectedCustomerId = $customer->id;
        $this->name = $customer->name;
        $this->phone = $customer->phone ?? '';
        $this->email = $customer->email ?? '';
        $this->address = $customer->address ?? '';
        $this->neighborhood = $customer->neighborhood ?? '';
        $this->references = $customer->references ?? '';
        $this->modalMode = 'edit';
        $this->showModal = true;
        $this->resetValidation();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->selectedCustomerId = null;
        $this->modalMode = 'view';
        $this->resetForm();
    }

    public function save(): void
    {
        $permission = $this->modalMode === 'edit' ? 'editar clientes' : 'crear clientes';
        $this->authorizeAction($permission);
        $validated = $this->validate($this->rules(), $this->messages());

        $payload = [
            'name' => trim($validated['name']),
            'phone' => trim($validated['phone']),
            'email' => filled($validated['email'] ?? null) ? mb_strtolower(trim($validated['email'])) : null,
            'address' => filled($validated['address'] ?? null) ? trim($validated['address']) : null,
            'neighborhood' => trim($validated['neighborhood']),
            'references' => filled($validated['references'] ?? null) ? trim($validated['references']) : null,
        ];

        if ($this->modalMode === 'edit') {
            $customer = Customer::query()->findOrFail($this->selectedCustomerId);
            $customer->update($payload);
            $message = 'Los datos del cliente se actualizaron correctamente.';
        } else {
            $customer = Customer::query()->create($payload);
            $message = 'Cliente agregado y disponible para utilizarse en el POS.';
        }

        $this->selectedCustomerId = $customer->id;
        $this->modalMode = 'view';
        $this->resetForm();
        $this->resetPage();
        $this->dispatch('notify', type: 'success', message: $message);
    }

    public function confirmDelete(int $customerId): void
    {
        $this->authorizeAction('eliminar clientes');
        $customer = Customer::query()->withCount('orders')->findOrFail($customerId);
        $history = $customer->orders_count > 0
            ? " Sus {$customer->orders_count} pedidos conservarán el nombre y los datos capturados en cada venta."
            : '';

        $this->dispatch(
            'open-confirm',
            type: 'danger',
            title: 'Eliminar cliente',
            message: 'Se eliminará a <strong>'.e($customer->name).'</strong> del directorio.'.$history,
            action: 'delete-customer',
            params: ['customer_id' => $customer->id],
            confirmText: 'Eliminar cliente',
        );
    }

    #[On('modal-confirmed')]
    public function handleModalConfirmed(string $action, array $params = []): void
    {
        if ($action !== 'delete-customer') {
            return;
        }

        $this->deleteCustomer((int) ($params['customer_id'] ?? 0));
    }

    public function deleteCustomer(int $customerId): void
    {
        $this->authorizeAction('eliminar clientes');
        $customer = Customer::query()->findOrFail($customerId);
        $name = $customer->name;
        $customer->delete();

        if ($this->selectedCustomerId === $customerId) {
            $this->closeModal();
        }

        $this->resetPage();
        $this->dispatch('notify', type: 'success', message: "{$name} fue eliminado del directorio.");
    }

    public function render()
    {
        $term = trim($this->search);
        $customers = Customer::query()
            ->withCount('orders')
            ->withMax('orders', 'created_at')
            ->when($term !== '', function (Builder $query) use ($term): void {
                $query->where(function (Builder $search) use ($term): void {
                    $search->where('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('address', 'like', "%{$term}%")
                        ->orWhere('neighborhood', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(12);

        $stats = [
            'total' => Customer::query()->count(),
            'with_orders' => Customer::query()->whereHas('orders')->count(),
            'new_this_month' => Customer::query()->where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        $selectedCustomer = $this->selectedCustomer();

        return view('livewire.customers.customer-manager', compact('customers', 'stats', 'selectedCustomer'))
            ->layout('layouts.app');
    }

    private function selectedCustomer(): ?Customer
    {
        if (! $this->showModal || ! $this->selectedCustomerId || $this->modalMode !== 'view') {
            return null;
        }

        $query = Customer::query()
            ->withCount('orders')
            ->withMax('orders', 'created_at')
            ->with([
                'orders' => fn ($orders) => $orders->latest()->limit(5),
            ]);

        if (auth()->user()?->can('ver reportes financieros')) {
            $query->withSum([
                'orders as paid_orders_total' => fn ($orders) => $orders->where('status', 'pagada'),
            ], 'total');
        }

        return $query->find($this->selectedCustomerId);
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', "regex:/^[\\pL\\pM\\s'.-]+$/u"],
            'phone' => ['required', 'string', 'min:7', 'max:30', 'regex:/^[0-9+()\\s.-]+$/'],
            'email' => ['nullable', 'email:rfc', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['required', 'string', 'max:120'],
            'references' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre del cliente.',
            'name.regex' => 'El nombre sólo puede contener letras, espacios, puntos, apóstrofes y guiones.',
            'name.max' => 'El nombre no puede superar 120 caracteres.',
            'phone.required' => 'Escribe un teléfono para localizar al cliente desde el POS.',
            'phone.min' => 'El teléfono debe contener al menos 7 caracteres.',
            'phone.regex' => 'Usa únicamente números, espacios y los símbolos + ( ) . -.',
            'email.email' => 'Escribe un correo válido, por ejemplo cliente@correo.com.',
            'email.max' => 'El correo no puede superar 160 caracteres.',
            'address.max' => 'La dirección no puede superar 255 caracteres.',
            'neighborhood.required' => 'Escribe la colonia o zona del cliente.',
            'neighborhood.max' => 'La colonia o zona no puede superar 120 caracteres.',
            'references.max' => 'Las referencias no pueden superar 500 caracteres.',
        ];
    }

    private function resetForm(): void
    {
        $this->reset('name', 'phone', 'email', 'address', 'neighborhood', 'references');
        $this->resetValidation();
    }

    private function authorizeAction(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
