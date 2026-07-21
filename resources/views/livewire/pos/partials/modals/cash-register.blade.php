@if($showCashModal)
<div class="pos-modal-wrap show" wire:click.self="$set('showCashModal',false)">
    <div class="pos-modal" @click.stop>
        <div class="modal-header-pos">
            <i class="bx bx-lock-open" data-ui="xui-bra89r"></i>
            <h4>Abrir caja</h4>
            <button wire:click="$set('showCashModal',false)" class="pos-btn pos-btn-secondary" data-ui="xui-1a0g5qw"><i class="bx bx-x"></i></button>
        </div>
        <div class="modal-body-pos">
            <div data-ui="xui-n3c866">
                <label class="co-label">Nombre de la caja</label>
                <input type="text" wire:model="cashName" class="co-input" placeholder="Caja 1">
            </div>
            <div>
                <label class="co-label">Fondo inicial</label>
                <input type="number" wire:model="cashInitialAmount" class="co-input" placeholder="500.00" step="0.01" min="0">
            </div>
        </div>
        <div class="modal-footer-pos">
            <button wire:click="$set('showCashModal',false)" class="pos-btn pos-btn-secondary">Cancelar</button>
            <button wire:click="openCashRegister" wire:loading.attr="disabled" class="pos-btn pos-btn-primary" data-ui="xui-c5fram">
                <i class="bx bx-check-circle"></i> Abrir caja
            </button>
        </div>
    </div>
</div>
@endif
