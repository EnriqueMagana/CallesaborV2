<?php

namespace App\Livewire\Caja;

use App\Models\CashRegisterCut;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class CorteHistorial extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('ver caja'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function cuts()
    {
        return CashRegisterCut::with(['cashRegister', 'generator'])
            ->when($this->search, function ($q) {
                $q->where('folio', 'like', '%'.$this->search.'%')
                  ->orWhereHas('cashRegister', fn($q2) => $q2->where('name', 'like', '%'.$this->search.'%'));
            })
            ->latest('generated_at')
            ->paginate(15);
    }

    public function render()
    {
        return view('livewire.caja.corte-historial')
            ->layout('layouts.app');
    }
}
