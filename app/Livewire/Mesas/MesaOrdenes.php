<?php

namespace App\Livewire\Mesas;

use App\Models\Mesa;
use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Component;

class MesaOrdenes extends Component
{
    public int $mesaId;

    public function mount(Mesa $mesa): void
    {
        $user = auth()->user();
        $assignment = $mesa->currentAssignment;

        if (!$user->hasAnyRole(['admin', 'super-admin', 'gerente', 'cajero'])) {
            if (!$assignment || $assignment->user_id !== $user->id) {
                $this->redirect(route('app.mesas'));
                return;
            }
        }

        $this->mesaId = $mesa->id;
    }

    #[Computed]
    public function mesa(): Mesa
    {
        return Mesa::with(['area', 'currentAssignment.waiter'])->findOrFail($this->mesaId);
    }

    #[Computed]
    public function orders()
    {
        return Order::with([
            'items.addons',
            'items.ingredients',
            'seller',
        ])
        ->where('mesa_id', $this->mesaId)
        ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada'])
        ->latest()
        ->get();
    }

    public function goBack(): void
    {
        $this->redirect(route('app.mesas'));
    }

    public function render()
    {
        return view('livewire.mesas.mesa-ordenes')
            ->layout('layouts.app');
    }
}
