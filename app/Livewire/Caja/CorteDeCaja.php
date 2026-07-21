<?php

namespace App\Livewire\Caja;

use App\Models\CashRegister;
use App\Models\CashRegisterCut;
use App\Models\Expense;
use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CorteDeCaja extends Component
{
    public ?int    $registerId    = null;
    public string  $declaredCash  = '';
    public string  $closingNotes  = '';
    public bool    $showConfirm   = false;
    public bool    $cutDone       = false;
    public ?int    $cutId         = null;

    public function mount(?int $id = null): void
    {
        $register = $id
            ? CashRegister::findOrFail($id)
            : CashRegister::where('is_open', true)->latest()->firstOrFail();

        abort_unless($register->is_open, 403, 'Esta caja ya está cerrada.');
        $this->registerId = $register->id;
    }

    #[Computed]
    public function register(): CashRegister
    {
        return CashRegister::findOrFail($this->registerId);
    }

    #[Computed]
    public function orders(): \Illuminate\Support\Collection
    {
        return Order::where('cash_register_id', $this->registerId)
            ->where('status', 'pagada')
            ->with(['payments', 'seller'])
            ->get();
    }

    #[Computed]
    public function auditOrders(): \Illuminate\Support\Collection
    {
        return Order::where('cash_register_id', $this->registerId)
            ->with(['payments', 'seller', 'cancelledBy'])
            ->orderBy('created_at')
            ->get();
    }

    #[Computed]
    public function expenses(): \Illuminate\Support\Collection
    {
        return Expense::where('cash_register_id', $this->registerId)->get();
    }

    // ────────── Totales por área y método ──────────

    private function sumPayments(string $type, string $method): float
    {
        return (float) $this->orders
            ->filter(fn ($o) => $o->type === $type || ($type === 'ventanilla' && $o->type === 'pick_up'))
            ->flatMap(fn ($o) => $o->payments->where('method', $method))
            ->sum('amount');
    }

    #[Computed]
    public function totals(): array
    {
        $orders = $this->orders;

        // Ventanilla + pick_up agrupados
        $ventanilla = $orders->filter(fn ($o) => in_array($o->type, ['ventanilla', 'pick_up']));
        $mesas      = $orders->filter(fn ($o) => $o->type === 'mesa');
        $delivery   = $orders->filter(fn ($o) => $o->type === 'delivery');

        $sum = fn ($col, string $method) => (float) $col
            ->flatMap(fn ($o) => $o->payments->where('method', $method))
            ->sum('amount');

        // Delivery contra_entrega se cobra al repartidor → efectivo entra a caja del repartidor
        // aquí solo contamos efectivo que literalmente ingresó a la caja física:
        // delivery con payment method='efectivo' (si algún día el cliente paga en caja)
        // Pero per nuestra lógica: contra_entrega → va al repartidor, no a caja.
        // → delivery efectivo = $0 a menos que haya un pago efectivo directo.

        return [
            'v' => [
                'efectivo' => $sum($ventanilla, 'efectivo'),
                'tarjeta'  => $sum($ventanilla, 'tarjeta'),
                'transfer' => $sum($ventanilla, 'transferencia'),
                'total'    => $ventanilla->sum('total'),
            ],
            'm' => [
                'efectivo' => $sum($mesas, 'efectivo'),
                'tarjeta'  => $sum($mesas, 'tarjeta'),
                'transfer' => $sum($mesas, 'transferencia'),
                'total'    => $mesas->sum('total'),
            ],
            'd' => [
                'efectivo' => $sum($delivery, 'efectivo'),   // contra_entrega no entra aquí en la lógica normal
                'tarjeta'  => $sum($delivery, 'tarjeta'),
                'transfer' => $sum($delivery, 'transferencia'),
                'total'    => $delivery->sum('total'),
            ],
        ];
    }

    #[Computed]
    public function operatorTotals(): array
    {
        return $this->orders
            ->groupBy('served_by')
            ->map(function ($orders) {
                $first = $orders->first();
                $byArea = fn (string $type) => $orders->filter(fn ($order) => $type === 'ventanilla'
                    ? in_array($order->type, ['ventanilla', 'pick_up'], true)
                    : $order->type === $type);
                $areaTotals = [];
                foreach (['ventanilla', 'mesa', 'delivery'] as $area) {
                    $areaTotals[$area] = (float) $byArea($area)->sum('total');
                }

                return [
                    'id' => $first->served_by,
                    'name' => $first->seller?->name ?? 'Usuario eliminado',
                    'orders' => $orders->count(),
                    'total' => (float) $orders->sum('total'),
                    'cash' => (float) $orders->flatMap(fn ($order) => $order->payments->where('method', 'efectivo'))->sum('amount'),
                    'areas' => $areaTotals,
                ];
            })->values()->all();
    }

    #[Computed]
    public function totalCashIn(): float
    {
        return $this->totals['v']['efectivo']
             + $this->totals['m']['efectivo']
             + $this->totals['d']['efectivo'];
    }

    #[Computed]
    public function totalExpensesCash(): float
    {
        return (float) $this->expenses
            ->where('payment_method', 'cash')
            ->sum('amount');
    }

    #[Computed]
    public function expectedCash(): float
    {
        return (float) $this->register->initial_amount
             + $this->totalCashIn
             - $this->totalExpensesCash;
    }

    #[Computed]
    public function difference(): float
    {
        if ($this->declaredCash === '' || $this->declaredCash === null) {
            return 0;
        }
        return (float) $this->declaredCash - $this->expectedCash;
    }

    // ────────── Acciones ──────────

    public function confirmCut(): void
    {
        $this->validate([
            'declaredCash' => 'required|numeric|min:0',
        ], ['declaredCash.required' => 'Ingresa el efectivo declarado.']);

        $this->showConfirm = true;
    }

    public function generateCut(): void
    {
        $t        = $this->totals;
        $register = $this->register;

        $folio = 'CORTE-' . str_pad($register->id, 4, '0', STR_PAD_LEFT);

        $cut = CashRegisterCut::create([
            'cash_register_id'    => $register->id,
            'generated_by'        => auth()->id(),
            'folio'               => $folio,
            'v_efectivo'          => $t['v']['efectivo'],
            'v_tarjeta'           => $t['v']['tarjeta'],
            'v_transfer'          => $t['v']['transfer'],
            'm_efectivo'          => $t['m']['efectivo'],
            'm_tarjeta'           => $t['m']['tarjeta'],
            'm_transfer'          => $t['m']['transfer'],
            'd_efectivo'          => $t['d']['efectivo'],
            'd_tarjeta'           => $t['d']['tarjeta'],
            'd_transfer'          => $t['d']['transfer'],
            'initial_amount'      => $register->initial_amount,
            'total_cash_in'       => $this->totalCashIn,
            'total_expenses_cash' => $this->totalExpensesCash,
            'expected_cash'       => $this->expectedCash,
            'declared_cash'       => (float) $this->declaredCash,
            'difference'          => $this->difference,
            'cut_data'            => [
                'orders_count' => $this->orders->count(),
                'audit_orders_count' => $this->auditOrders->count(),
                'cancelled_orders_count' => $this->auditOrders->where('status', 'cancelada')->count(),
                'operators' => $this->operatorTotals,
                'expenses'     => $this->expenses->toArray(),
            ],
            'generated_at' => now(),
        ]);

        $register->update([
            'is_open'           => false,
            'closed_by'         => auth()->id(),
            'closed_at'         => now(),
            'final_amount'      => $this->totalCashIn,
            'declared_amount'   => (float) $this->declaredCash,
            'difference_amount' => $this->difference,
            'closing_notes'     => $this->closingNotes ?: null,
        ]);

        $this->showConfirm = false;
        $this->cutDone     = true;
        $this->cutId       = $cut->id;
        unset($this->register, $this->orders, $this->expenses, $this->totals);
    }

    public function render()
    {
        return view('livewire.caja.corte-de-caja')
            ->layout('layouts.app');
    }
}
