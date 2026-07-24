<?php

namespace App\Livewire\Caja;

use App\Models\CashRegisterCut;
use App\Models\Expense;
use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CorteDetalle extends Component
{
    public int $cutId;

    public function mount(CashRegisterCut $cut): void
    {
        abort_unless(auth()->user()?->can('ver caja'), 403);
        $this->cutId = $cut->id;
    }

    #[Computed]
    public function cut(): CashRegisterCut
    {
        return CashRegisterCut::with(['cashRegister.opener', 'cashRegister.closer', 'generator'])
            ->findOrFail($this->cutId);
    }

    #[Computed]
    public function orders()
    {
        return Order::where('cash_register_id', $this->cut->cash_register_id)
            ->where('status', 'pagada')
            ->with('payments')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function expenses()
    {
        return Expense::where('cash_register_id', $this->cut->cash_register_id)
            ->orderBy('created_at')
            ->get();
    }

    #[Computed]
    public function ordersByArea(): array
    {
        $all = $this->orders;
        return [
            'ventanilla' => $all->filter(fn($o) => in_array($o->type, ['ventanilla', 'pick_up'])),
            'mesas'      => $all->filter(fn($o) => $o->type === 'mesa'),
            'delivery'   => $all->filter(fn($o) => $o->type === 'delivery'),
        ];
    }

    public function render()
    {
        return view('livewire.caja.corte-detalle')
            ->layout('layouts.app');
    }
}
