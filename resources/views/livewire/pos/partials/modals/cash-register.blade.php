@if($showCashModal)
<div class="pos-modal-wrap show" wire:click.self="$set('showCashModal',false)" role="dialog" aria-modal="true" aria-labelledby="cash-modal-title">
    <div class="pos-modal" @click.stop>
        <div class="modal-header-pos">
            <i class="bx bx-lock-open" data-ui="xui-bra89r"></i>
            <h4 id="cash-modal-title">Abrir caja</h4>
            <button type="button" wire:click="$set('showCashModal',false)" class="pos-btn pos-btn-secondary" aria-label="Cerrar" data-ui="xui-1a0g5qw"><i class="bx bx-x"></i></button>
        </div>
        <div class="modal-body-pos">
            <div data-ui="xui-n3c866">
                <label class="co-label" for="pos-cash-name">Nombre de la caja</label>
                <input id="pos-cash-name" type="text" wire:model="cashName" class="co-input" placeholder="Caja 1"
                    aria-invalid="{{ $errors->has('cashName') ? 'true' : 'false' }}">
                @error('cashName')<p class="pos-field-error" role="alert"><i class="bx bx-error-circle" aria-hidden="true"></i> {{ $message }}</p>@enderror
            </div>
            <div>
                <label class="co-label" for="pos-cash-initial">Fondo inicial</label>
                <input id="pos-cash-initial" type="number" wire:model="cashInitialAmount" class="co-input" placeholder="500.00" step="0.01" min="0">
                @error('cashInitialAmount')<p class="pos-field-error" role="alert"><i class="bx bx-error-circle" aria-hidden="true"></i> {{ $message }}</p>@enderror
            </div>
        </div>
        <div class="modal-footer-pos">
            <button type="button" wire:click="$set('showCashModal',false)" class="pos-btn pos-btn-secondary">Cancelar</button>
            <button type="button" wire:click="openCashRegister" wire:loading.attr="disabled" class="pos-btn pos-btn-primary" data-ui="xui-c5fram">
                <i class="bx bx-check-circle"></i> Abrir caja
            </button>
        </div>
    </div>
</div>
@endif
