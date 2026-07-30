@if($showQuotationsModal)
<div class="pos-modal-wrap show" wire:click.self="$set('showQuotationsModal',false)">
    <div class="pos-modal" @click.stop>
        <div class="modal-header-pos">
            <i class="bx bx-bookmark" data-ui="xui-zbum5m"></i>
            <h4>Pedidos guardados</h4>
            <button wire:click="$set('showQuotationsModal',false)" class="pos-btn pos-btn-secondary" data-ui="xui-1a0g5qw"><i class="bx bx-x"></i></button>
        </div>
        <div class="modal-body-pos">
            @forelse($this->quotations as $q)
                <div data-ui="xui-qgnhtb">
                    <div data-ui="xui-kfq101">
                        <span data-ui="xui-pt1n3">{{ $q->display_name }}</span>
                        <span data-ui="xui-18m4gf1">${{ number_format($q->total,2) }}</span>
                    </div>
                    @if($q->customer_name)<div data-ui="xui-y4gxmi"><i class="bx bx-user me-1"></i>{{ $q->customer_name }}</div>@endif
                    <div data-ui="xui-14yui1o">{{ $q->created_at->diffForHumans() }}</div>
                    <div data-ui="xui-6889c4">
                        <button wire:click="loadQuotation({{ $q->id }})" class="pos-btn pos-btn-primary" data-ui="xui-1lde97g">
                            <i class="bx bx-upload"></i> Cargar
                        </button>
                        <button wire:click="deleteQuotation({{ $q->id }})" onclick="return confirm('¿Eliminar pedido guardado?')"
                                class="pos-btn pos-btn-secondary" data-ui="xui-8475pq">
                            <i class="bx bx-trash"></i>
                        </button>
                    </div>
                </div>
            @empty
                <div data-ui="xui-ggsjjx">
                    <i class="bx bx-bookmark" data-ui="xui-qcmw2v"></i>
                    <span data-ui="xui-1fzausk">No hay pedidos guardados</span>
                </div>
            @endforelse
        </div>
        <div class="modal-footer-pos">
            <button wire:click="$set('showQuotationsModal',false)" class="pos-btn pos-btn-secondary" data-ui="xui-12o4wxl">Cerrar</button>
        </div>
    </div>
</div>
@endif

@if($showSaveQuotationModal)
<div class="pos-modal-wrap show" wire:click.self="$set('showSaveQuotationModal',false)">
    <div class="pos-modal" @click.stop>
        <div class="modal-header-pos">
            <i class="bx bx-save" data-ui="xui-zbum5m"></i>
            <h4>Guardar pedido</h4>
            <button wire:click="$set('showSaveQuotationModal',false)" class="pos-btn pos-btn-secondary" data-ui="xui-1a0g5qw"><i class="bx bx-x"></i></button>
        </div>
        <div class="modal-body-pos">
            <div data-ui="xui-n3c866">
                <label class="co-label">Nombre o referencia</label>
                <input type="text" wire:model="quotationName" class="co-input" placeholder="Ej: Cumpleaños Carlos">
            </div>
            <div>
                <label class="co-label">Notas</label>
                <input type="text" wire:model="quotationNotes" class="co-input" placeholder="Observaciones opcionales…">
            </div>
        </div>
        <div class="modal-footer-pos">
            <button wire:click="$set('showSaveQuotationModal',false)" class="pos-btn pos-btn-secondary">Cancelar</button>
            <button wire:click="saveQuotation" wire:loading.attr="disabled" wire:target="saveQuotation"
                class="pos-btn pos-btn-primary">
                <span wire:loading wire:target="saveQuotation" class="pos-btn-spinner"></span>
                <i wire:loading.remove wire:target="saveQuotation" class="bx bx-save"></i>
                <span wire:loading.remove wire:target="saveQuotation">Guardar y limpiar</span>
                <span wire:loading wire:target="saveQuotation">Guardando…</span>
            </button>
        </div>
    </div>
</div>
@endif
