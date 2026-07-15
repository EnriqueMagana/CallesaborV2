<?php

namespace App\Livewire\Reservas;

use App\Models\Customer;
use App\Models\Reservation;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CalendarioReservas extends Component
{
    // Panel mode: 'new' | 'detail' | null
    public ?string $panelMode      = null;
    public ?int    $selectedId     = null;

    // Form fields
    public string  $customerSearch = '';
    public ?int    $customerId     = null;
    public string  $customerName   = '';
    public string  $customerPhone  = '';
    public int     $guests         = 2;
    public string  $reservedDate   = '';
    public string  $reservedTime   = '19:00';
    public string  $notes          = '';

    // Cancel reason
    public string  $cancelReason   = '';
    public bool    $showCancelForm = false;

    // ── Computed ──

    #[Computed]
    public function customerSuggestions()
    {
        if (strlen($this->customerSearch) < 2) return collect();

        return Customer::where('name', 'like', '%'.$this->customerSearch.'%')
            ->orWhere('phone', 'like', '%'.$this->customerSearch.'%')
            ->limit(6)
            ->get(['id','name','phone']);
    }

    #[Computed]
    public function selectedReservation(): ?Reservation
    {
        return $this->selectedId ? Reservation::find($this->selectedId) : null;
    }

    // ── Handlers ──

    public function openNew(string $date = ''): void
    {
        $this->resetForm();
        $this->reservedDate = $date ?: now()->format('Y-m-d');
        $this->panelMode    = 'new';
        $this->selectedId   = null;
    }

    public function openDetail(int $id): void
    {
        $this->selectedId   = $id;
        $this->panelMode    = 'detail';
        $this->showCancelForm = false;
        unset($this->selectedReservation);
    }

    public function selectCustomer(int $id): void
    {
        $c = Customer::find($id);
        if (!$c) return;

        $this->customerId    = $c->id;
        $this->customerName  = $c->name;
        $this->customerPhone = $c->phone ?? '';
        $this->customerSearch = '';
        unset($this->customerSuggestions);
    }

    public function updatedCustomerSearch(): void
    {
        $this->customerId = null;
        unset($this->customerSuggestions);
    }

    public function save(): void
    {
        $this->validate([
            'customerName' => 'required|string|max:100',
            'guests'       => 'required|integer|min:1|max:500',
            'reservedDate' => 'required|date',
            'reservedTime' => 'required',
        ], [
            'customerName.required' => 'El nombre es obligatorio.',
            'guests.required'       => 'Indica cuántos asistirán.',
            'reservedDate.required' => 'Selecciona la fecha.',
            'reservedTime.required' => 'Selecciona la hora.',
        ]);

        Reservation::create([
            'customer_id'    => $this->customerId,
            'created_by'     => auth()->id(),
            'customer_name'  => $this->customerName,
            'customer_phone' => $this->customerPhone ?: null,
            'guests'         => $this->guests,
            'reserved_at'    => $this->reservedDate.' '.$this->reservedTime.':00',
            'notes'          => $this->notes ?: null,
            'status'         => 'pendiente',
        ]);

        $this->resetForm();
        $this->panelMode = null;
        $this->dispatch('reservation-saved');
    }

    public function startEdit(): void
    {
        $r = $this->selectedReservation;
        if (!$r) return;

        $this->customerId    = $r->customer_id;
        $this->customerName  = $r->customer_name;
        $this->customerPhone = $r->customer_phone ?? '';
        $this->guests        = $r->guests;
        $this->reservedDate  = $r->reserved_at->format('Y-m-d');
        $this->reservedTime  = $r->reserved_at->format('H:i');
        $this->notes         = $r->notes ?? '';
        $this->panelMode     = 'edit';
    }

    public function update(): void
    {
        $this->validate([
            'customerName' => 'required|string|max:100',
            'guests'       => 'required|integer|min:1|max:500',
            'reservedDate' => 'required|date',
            'reservedTime' => 'required',
        ]);

        $r = $this->selectedReservation;
        if (!$r) return;

        $r->update([
            'customer_id'    => $this->customerId,
            'customer_name'  => $this->customerName,
            'customer_phone' => $this->customerPhone ?: null,
            'guests'         => $this->guests,
            'reserved_at'    => $this->reservedDate.' '.$this->reservedTime.':00',
            'notes'          => $this->notes ?: null,
        ]);

        unset($this->selectedReservation);
        $this->panelMode = 'detail';
        $this->dispatch('reservation-saved');
    }

    public function changeStatus(string $status): void
    {
        $r = $this->selectedReservation;
        if (!$r) return;

        $r->update(['status' => $status]);
        unset($this->selectedReservation);
        $this->dispatch('reservation-saved');
    }

    public function cancel(): void
    {
        $r = $this->selectedReservation;
        if (!$r) return;

        $r->update([
            'status'              => 'cancelada',
            'cancellation_reason' => $this->cancelReason ?: null,
        ]);

        $this->showCancelForm = false;
        $this->cancelReason   = '';
        unset($this->selectedReservation);
        $this->dispatch('reservation-saved');
    }

    public function incrementGuests(): void
    {
        $this->guests++;
    }

    public function decrementGuests(): void
    {
        if ($this->guests > 1) $this->guests--;
    }

    public function closePanel(): void
    {
        $this->panelMode    = null;
        $this->selectedId   = null;
        $this->showCancelForm = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->customerId    = null;
        $this->customerSearch = '';
        $this->customerName  = '';
        $this->customerPhone = '';
        $this->guests        = 2;
        $this->reservedDate  = '';
        $this->reservedTime  = '19:00';
        $this->notes         = '';
        unset($this->customerSuggestions);
    }

    public function render()
    {
        return view('livewire.reservas.calendario-reservas')
            ->layout('layouts.app');
    }
}
