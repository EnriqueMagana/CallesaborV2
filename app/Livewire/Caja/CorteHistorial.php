<?php

namespace App\Livewire\Caja;

use App\Models\CashRegister;
use App\Models\CashRegisterCut;
use App\Services\CashRegisterReopenService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class CorteHistorial extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $reopenCutId = null;

    public string $reopenReason = '';

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
        return CashRegisterCut::with(['cashRegister', 'generator', 'reopener'])
            ->when($this->search, function ($q) {
                $q->where('folio', 'like', '%'.$this->search.'%')
                  ->orWhereHas('cashRegister', fn($q2) => $q2->where('name', 'like', '%'.$this->search.'%'));
            })
            ->latest('generated_at')
            ->latest('id')
            ->paginate(15);
    }

    #[Computed]
    public function canReopen(): bool
    {
        return app(CashRegisterReopenService::class)->canReopen(auth()->user());
    }

    /**
     * El único corte que hoy puede anularse: el vigente de la última caja
     * cerrada, siempre que no haya otra abierta.
     */
    #[Computed]
    public function reopenableCutId(): ?int
    {
        if (! $this->canReopen) {
            return null;
        }

        $register = CashRegister::query()
            ->where('is_open', false)
            ->whereNotNull('closed_at')
            ->latest('closed_at')
            ->latest('id')
            ->first();
        $cut = $register?->cuts()->with('cashRegister')->whereNull('reopened_at')->latest('generated_at')->latest('id')->first();

        return $cut && app(CashRegisterReopenService::class)->blockReason($cut) === null ? $cut->id : null;
    }

    #[Computed]
    public function reopenCut(): ?CashRegisterCut
    {
        return $this->reopenCutId
            ? CashRegisterCut::with('cashRegister')->find($this->reopenCutId)
            : null;
    }

    public function openReopen(int $cutId): void
    {
        abort_unless($this->canReopen, 403);
        $this->resetValidation();
        $this->reopenReason = '';
        $this->reopenCutId = CashRegisterCut::query()->findOrFail($cutId)->id;
        unset($this->reopenCut);
    }

    public function closeReopen(): void
    {
        $this->reopenCutId = null;
        $this->reopenReason = '';
        $this->resetValidation();
    }

    public function confirmReopen(CashRegisterReopenService $service)
    {
        abort_unless($this->canReopen, 403);
        $this->validate(
            ['reopenReason' => 'required|string|min:10|max:1000'],
            ['reopenReason.required' => 'Indica por qué se reabre la caja.', 'reopenReason.min' => 'Explica el motivo con al menos 10 caracteres.']
        );

        $register = $service->reopen($this->reopenCut, auth()->user(), $this->reopenReason);

        session()->flash('success', "Caja \"{$register->name}\" reabierta. El corte {$this->reopenCut->folio} quedó anulado y conserva su registro.");

        return $this->redirectRoute('app.caja');
    }

    public function render()
    {
        return view('livewire.caja.corte-historial')
            ->layout('layouts.app');
    }
}
