<div class="pos-toolbar-bottom">
    <button class="tb-btn" @click="panels.mesas = true; $wire.$refresh()" title="Cobrar mesa">
        <i class="bx bx-table"></i>
        <span>Mesas</span>
        @if($this->mesasPendientes->count() > 0)
            <span class="badge-dot" style="background:#ef4444;width:auto;height:18px;min-width:18px;border-radius:9px;font-size:.65rem;font-weight:700;color:#fff;display:flex;align-items:center;justify-content:center;padding:0 4px;line-height:1;top:4px;right:4px">
                {{ $this->mesasPendientes->count() }}
            </span>
        @endif
    </button>
    <button class="tb-btn" @click="panels.pickup = true" wire:click="$refresh">
        <i class="bx bx-store-alt"></i>
        <span>Para recoger</span>
        @if($this->pickupOrders->count() > 0)
            <span class="badge-dot" style="background:#f59e0b"></span>
        @endif
    </button>
    <button class="tb-btn" wire:click="openExpenseModal">
        <i class="bx bx-wallet"></i>
        <span>Gastos</span>
    </button>
    <button class="tb-btn" @click="panels.kitchen = true; $wire.$refresh()" title="Cocina" wire:poll.10s="$refresh">
        <i class="bx bx-restaurant"></i>
        <span>Cocina</span>
        @if($this->kitchenPendingCount > 0)
            <span class="badge-dot" style="background:#f59e0b;width:auto;height:18px;min-width:18px;border-radius:9px;font-size:.65rem;font-weight:700;color:#fff;display:flex;align-items:center;justify-content:center;padding:0 4px;line-height:1;top:4px;right:4px">
                {{ $this->kitchenPendingCount }}
            </span>
        @endif
    </button>
    <button class="tb-btn" @click="panels.reprint = true" wire:click="$refresh">
        <i class="bx bx-printer"></i>
        <span>Reimprimir</span>
    </button>
</div>
