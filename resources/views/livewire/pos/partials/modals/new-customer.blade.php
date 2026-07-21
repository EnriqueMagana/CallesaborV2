@if($showAddCustomerModal)
<div class="pos-modal-wrap show" wire:click.self="$set('showAddCustomerModal',false)" data-ui="xui-5zthtb">
    <div class="pos-modal" @click.stop>
        <div class="modal-header-pos">
            <i class="bx bx-user-plus" data-ui="xui-zbum5m"></i>
            <h4>Registrar nuevo cliente</h4>
            <button wire:click="$set('showAddCustomerModal',false)" class="pos-btn pos-btn-secondary" data-ui="xui-1a0g5qw"><i class="bx bx-x"></i></button>
        </div>
        <div class="modal-body-pos">
            <div data-ui="xui-n3c866">
                <label class="co-label">Nombre <span class="req">*</span></label>
                <input type="text" wire:model="newCustomerName" class="co-input" placeholder="Nombre completo" autofocus>
                @error('newCustomerName')<div data-ui="xui-1bwpvep">{{ $message }}</div>@enderror
            </div>
            <div data-ui="xui-n3c866">
                <label class="co-label">Teléfono <span class="req">*</span></label>
                <input type="tel" wire:model="newCustomerPhone" class="co-input" placeholder="+52 999 000 0000">
                @error('newCustomerPhone')<div data-ui="xui-1bwpvep">{{ $message }}</div>@enderror
            </div>
            <div data-ui="xui-n3c866">
                <label class="co-label">Correo <span data-ui="xui-b1shr1">(opcional)</span></label>
                <input type="email" wire:model="newCustomerEmail" class="co-input" placeholder="cliente@correo.com">
            </div>
            <div data-ui="xui-n3c866">
                <label class="co-label">Dirección <span data-ui="xui-b1shr1">(opcional)</span></label>
                <input type="text" wire:model="newCustomerAddress" class="co-input" placeholder="Calle, número, colonia">
            </div>
            <div>
                <label class="co-label">Referencias <span data-ui="xui-b1shr1">(opcional)</span></label>
                <input type="text" wire:model="newCustomerReferences" class="co-input" placeholder="Casa azul, frente al parque…">
            </div>
        </div>
        <div class="modal-footer-pos">
            <button wire:click="$set('showAddCustomerModal',false)" class="pos-btn pos-btn-secondary">Cancelar</button>
            <button wire:click="saveNewCustomer" wire:loading.attr="disabled" wire:target="saveNewCustomer" class="pos-btn pos-btn-primary">
                <span wire:loading wire:target="saveNewCustomer" class="spinner-border spinner-border-sm" data-ui="xui-a2bbzz"></span>
                <i wire:loading.remove wire:target="saveNewCustomer" class="bx bx-save"></i>
                Guardar y usar
            </button>
        </div>
    </div>
</div>
@endif
