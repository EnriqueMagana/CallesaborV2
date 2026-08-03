<?php

namespace App\Livewire\Caja;

use App\Models\CashRegister;
use App\Models\DeliveryAssignment;
use App\Models\Order;
use App\Models\User;
use App\Services\DeliverySettlementService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Dashboard extends Component
{
    // Apertura
    public string $registerName = '';

    public string $initialAmount = '';

    public ?int $settlementDriverId = null;

    public string $settlementDeclaredCash = '';

    public string $settlementNotes = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('ver caja'), 403);
    }

    #[Computed]
    public function activeRegister(): ?CashRegister
    {
        return CashRegister::where('is_open', true)->latest()->first();
    }

    public function openRegister(): void
    {
        abort_unless(auth()->user()?->can('abrir caja'), 403);
        $this->validate([
            'registerName' => 'required|string|max:60',
            'initialAmount' => 'required|numeric|min:0',
        ], [
            'registerName.required' => 'El nombre de la caja es obligatorio.',
            'initialAmount.required' => 'Ingresa el fondo inicial.',
            'initialAmount.min' => 'El fondo no puede ser negativo.',
        ]);

        CashRegister::create([
            'name' => trim($this->registerName),
            'opened_by' => auth()->id(),
            'initial_amount' => (float) $this->initialAmount,
            'opened_at' => now(),
            'is_open' => true,
        ]);

        $this->registerName = '';
        $this->initialAmount = '';
        unset($this->activeRegister);

        $this->dispatch('caja-opened');
    }

    #[Computed]
    public function deliveryReconciliations(): Collection
    {
        if (! $this->activeRegister) {
            return collect();
        }

        $assignments = DeliveryAssignment::query()
            ->whereHas('order', fn ($orders) => $orders->where('cash_register_id', $this->activeRegister->id))
            ->with(['driver', 'order.payments', 'settlement'])
            ->get()
            ->groupBy('driver_id');

        return $assignments->map(function (Collection $driverAssignments): array {
            $driver = $driverAssignments->first()->driver;
            $inRoute = $driverAssignments->where('status', 'asignado');
            $delivered = $driverAssignments->where('status', 'entregado');
            $pendingSettlement = $delivered->whereNull('delivery_settlement_id');
            $orders = $pendingSettlement->pluck('order');
            $sumMethod = fn (string $method): float => (float) $orders
                ->flatMap(fn (Order $order) => $order->payments->where('method', $method))
                ->sum('amount');

            return [
                'driver_id' => $driver?->id,
                'name' => $driver?->name ?? 'Usuario eliminado',
                'in_route' => $inRoute->count(),
                'delivered' => $delivered->count(),
                'pending_notes' => $pendingSettlement->count(),
                'cash_expected' => $sumMethod('efectivo'),
                'transfer_total' => $sumMethod('transferencia'),
                'card_total' => $sumMethod('tarjeta'),
                'sales_total' => (float) $orders->sum('total'),
                'can_settle' => $pendingSettlement->isNotEmpty() && $inRoute->isEmpty(),
                'settlements' => $driverAssignments
                    ->pluck('settlement')
                    ->filter()
                    ->unique('id')
                    ->sortByDesc('completed_at')
                    ->values(),
            ];
        })->sortByDesc('in_route')->values();
    }

    #[Computed]
    public function unassignedDeliveryCount(): int
    {
        if (! $this->activeRegister) {
            return 0;
        }

        return Order::query()
            ->where('cash_register_id', $this->activeRegister->id)
            ->where('type', 'delivery')
            ->where('status', '!=', 'cancelada')
            ->whereDoesntHave('deliveryAssignment')
            ->count();
    }

    public function openDeliverySettlement(int $driverId): void
    {
        abort_unless(auth()->user()?->can('cerrar caja'), 403);
        abort_unless($this->deliveryReconciliations->contains('driver_id', $driverId), 404);

        $this->settlementDriverId = $driverId;
        $row = $this->deliveryReconciliations->firstWhere('driver_id', $driverId);
        $this->settlementDeclaredCash = number_format((float) $row['cash_expected'], 2, '.', '');
        $this->settlementNotes = '';
        $this->resetErrorBag('deliverySettlement');
    }

    public function closeDeliverySettlement(): void
    {
        $this->settlementDriverId = null;
        $this->settlementDeclaredCash = '';
        $this->settlementNotes = '';
        $this->resetErrorBag('deliverySettlement');
    }

    public function completeDeliverySettlement(DeliverySettlementService $service): void
    {
        abort_unless(auth()->user()?->can('cerrar caja'), 403);
        abort_unless($this->activeRegister && $this->settlementDriverId, 422);

        $this->validate([
            'settlementDeclaredCash' => ['required', 'numeric', 'min:0'],
            'settlementNotes' => ['nullable', 'string', 'max:500'],
        ], [
            'settlementDeclaredCash.required' => 'Ingresa el efectivo entregado por el repartidor.',
        ]);

        try {
            $service->complete(
                $this->activeRegister,
                User::query()->findOrFail($this->settlementDriverId),
                auth()->user(),
                (float) $this->settlementDeclaredCash,
                $this->settlementNotes,
            );
        } catch (ValidationException $exception) {
            $this->addError('deliverySettlement', $exception->validator->errors()->first('deliverySettlement'));

            return;
        }

        $driverName = $this->deliveryReconciliations
            ->firstWhere('driver_id', $this->settlementDriverId)['name'] ?? 'Repartidor';
        $this->closeDeliverySettlement();
        unset($this->deliveryReconciliations, $this->unassignedDeliveryCount);
        $this->dispatch('notify', type: 'success', message: $driverName.' · arqueo completado.');
    }

    public function goToPOS(): mixed
    {
        return $this->redirect(route('app.pos'), navigate: true);
    }

    public function render()
    {
        return view('livewire.caja.dashboard')
            ->layout('layouts.app');
    }
}
