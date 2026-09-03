<?php

namespace App\Livewire\Admin;

use App\Models\Customer;
use App\Models\Discount;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DiscountManager extends Component
{
    use AuthorizesRequests;

    public bool $showEditor = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $category = 'occasional';

    public string $valueType = 'percentage';

    public string $value = '10';

    public string $scope = 'order';

    public string $audience = 'everyone';

    public string $minimumPurchase = '0';

    public string $maximumDiscount = '';

    public array $fulfillmentModes = Discount::FULFILLMENT_MODES;

    public string $startsAt = '';

    public string $endsAt = '';

    public int $priority = 100;

    public bool $combineWithPromotions = false;

    public bool $isActive = true;

    public array $productIds = [];

    public array $customerIds = [];

    public array $employeeIds = [];

    public string $search = '';

    public string $statusFilter = 'all';

    public function mount(): void
    {
        $this->authorize('ver descuentos');
    }

    #[Computed]
    public function discounts()
    {
        return Discount::query()
            ->withCount(['products', 'customers', 'employees'])
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('name', 'like', '%'.trim($this->search).'%')
                ->orWhere('description', 'like', '%'.trim($this->search).'%')))
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->statusFilter === 'paused', fn ($query) => $query->where('is_active', false))
            ->orderBy('priority')
            ->latest('id')
            ->get();
    }

    #[Computed]
    public function products()
    {
        return Product::query()->where('is_active', true)->with('category:id,name')
            ->orderBy('name')->get(['id', 'category_id', 'name', 'price']);
    }

    #[Computed]
    public function customers()
    {
        return Customer::query()->orderBy('name')->get(['id', 'name', 'phone', 'email']);
    }

    #[Computed]
    public function employees()
    {
        return User::query()->whereNull('banned_at')->orderBy('name')->get(['id', 'name', 'email']);
    }

    public function openCreate(): void
    {
        $this->authorize('crear descuentos');
        $this->resetForm();
        $this->showEditor = true;
    }

    public function openEdit(int $discountId): void
    {
        $this->authorize('editar descuentos');
        $discount = Discount::with(['products:id', 'customers:id', 'employees:id'])->findOrFail($discountId);
        $this->resetForm();
        $this->editingId = $discount->id;
        $this->name = $discount->name;
        $this->description = (string) $discount->description;
        $this->category = $discount->category;
        $this->valueType = $discount->value_type;
        $this->value = (string) $discount->value;
        $this->scope = $discount->scope;
        $this->audience = $discount->audience;
        $this->minimumPurchase = (string) $discount->minimum_purchase;
        $this->maximumDiscount = (string) ($discount->maximum_discount ?? '');
        $this->fulfillmentModes = $discount->fulfillment_modes ?: Discount::FULFILLMENT_MODES;
        $this->startsAt = $discount->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->endsAt = $discount->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->priority = $discount->priority;
        $this->combineWithPromotions = $discount->combine_with_promotions;
        $this->isActive = $discount->is_active;
        $this->productIds = $discount->products->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->customerIds = $discount->customers->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->employeeIds = $discount->employees->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->showEditor = true;
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'editar descuentos' : 'crear descuentos');
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['required', Rule::in(Discount::CATEGORIES)],
            'valueType' => ['required', Rule::in(Discount::VALUE_TYPES)],
            'value' => ['required', 'numeric', 'gt:0', $this->valueType === 'percentage' ? 'max:100' : 'max:999999.99'],
            'scope' => ['required', Rule::in(Discount::SCOPES)],
            'audience' => ['required', Rule::in(Discount::AUDIENCES)],
            'minimumPurchase' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'maximumDiscount' => ['nullable', 'numeric', 'gt:0', 'max:999999.99'],
            'fulfillmentModes' => ['required', 'array', 'min:1'],
            'fulfillmentModes.*' => [Rule::in(Discount::FULFILLMENT_MODES)],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date', 'after:startsAt'],
            'priority' => ['required', 'integer', 'between:1,999'],
            'combineWithPromotions' => ['boolean'],
            'isActive' => ['boolean'],
            'productIds' => [$this->scope === 'products' ? 'required' : 'nullable', 'array', $this->scope === 'products' ? 'min:1' : 'max:500'],
            'productIds.*' => ['integer', 'exists:products,id'],
            'customerIds' => [$this->audience === 'selected_customers' ? 'required' : 'nullable', 'array'],
            'customerIds.*' => ['integer', 'exists:customers,id'],
            'employeeIds' => [$this->audience === 'selected_employees' ? 'required' : 'nullable', 'array'],
            'employeeIds.*' => ['integer', 'exists:users,id'],
        ];
        $this->validate($rules, [
            'productIds.required' => 'Selecciona al menos un producto elegible.',
            'customerIds.required' => 'Selecciona los clientes que recibirán el descuento.',
            'employeeIds.required' => 'Selecciona al personal que recibirá el descuento.',
            'endsAt.after' => 'La fecha final debe ser posterior al inicio.',
        ]);

        DB::transaction(function (): void {
            $discount = Discount::updateOrCreate(['id' => $this->editingId], [
                'name' => trim($this->name),
                'description' => trim($this->description) ?: null,
                'category' => $this->category,
                'value_type' => $this->valueType,
                'value' => round((float) $this->value, 2),
                'scope' => $this->scope,
                'audience' => $this->audience,
                'minimum_purchase' => round((float) $this->minimumPurchase, 2),
                'maximum_discount' => filled($this->maximumDiscount) ? round((float) $this->maximumDiscount, 2) : null,
                'fulfillment_modes' => array_values(array_unique($this->fulfillmentModes)),
                'starts_at' => filled($this->startsAt) ? $this->startsAt : null,
                'ends_at' => filled($this->endsAt) ? $this->endsAt : null,
                'priority' => $this->priority,
                'combine_with_promotions' => $this->combineWithPromotions,
                'auto_apply' => true,
                'is_active' => $this->isActive,
                'created_by' => $this->editingId ? Discount::find($this->editingId)?->created_by : auth()->id(),
            ]);
            $discount->products()->sync($this->scope === 'products' ? $this->productIds : []);
            $discount->customers()->sync($this->audience === 'selected_customers' ? $this->customerIds : []);
            $discount->employees()->sync($this->audience === 'selected_employees' ? $this->employeeIds : []);
        });

        unset($this->discounts);
        $this->showEditor = false;
        $this->dispatch('notify', type: 'success', message: 'Descuento guardado y disponible para el POS.');
    }

    public function toggleActive(int $discountId): void
    {
        $this->authorize('editar descuentos');
        $discount = Discount::findOrFail($discountId);
        $discount->update(['is_active' => ! $discount->is_active]);
        unset($this->discounts);
    }

    public function delete(int $discountId): void
    {
        $this->authorize('eliminar descuentos');
        Discount::findOrFail($discountId)->delete();
        unset($this->discounts);
        $this->dispatch('notify', type: 'success', message: 'Descuento eliminado. Las órdenes conservan su historial.');
    }

    public function closeEditor(): void
    {
        $this->showEditor = false;
        $this->resetValidation();
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'description', 'startsAt', 'endsAt', 'maximumDiscount',
            'productIds', 'customerIds', 'employeeIds',
        ]);
        $this->category = 'occasional';
        $this->valueType = 'percentage';
        $this->value = '10';
        $this->scope = 'order';
        $this->audience = 'everyone';
        $this->minimumPurchase = '0';
        $this->fulfillmentModes = Discount::FULFILLMENT_MODES;
        $this->priority = 100;
        $this->combineWithPromotions = false;
        $this->isActive = true;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.discount-manager')->layout('layouts.app');
    }
}
