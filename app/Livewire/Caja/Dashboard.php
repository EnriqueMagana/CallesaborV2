<?php

namespace App\Livewire\Caja;

use App\Models\CashRegister;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Dashboard extends Component
{
    // Apertura
    public string $registerName  = '';
    public string $initialAmount = '';

    #[Computed]
    public function activeRegister(): ?CashRegister
    {
        return CashRegister::where('is_open', true)->latest()->first();
    }

    public function openRegister(): void
    {
        $this->validate([
            'registerName'  => 'required|string|max:60',
            'initialAmount' => 'required|numeric|min:0',
        ], [
            'registerName.required'  => 'El nombre de la caja es obligatorio.',
            'initialAmount.required' => 'Ingresa el fondo inicial.',
            'initialAmount.min'      => 'El fondo no puede ser negativo.',
        ]);

        CashRegister::create([
            'name'           => trim($this->registerName),
            'opened_by'      => auth()->id(),
            'initial_amount' => (float) $this->initialAmount,
            'opened_at'      => now(),
            'is_open'        => true,
        ]);

        $this->registerName  = '';
        $this->initialAmount = '';
        unset($this->activeRegister);

        $this->dispatch('caja-opened');
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
