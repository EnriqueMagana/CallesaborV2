@if($showOrderSuccess)
<div class="pos-modal-wrap show">
    <div class="pos-modal" data-ui="xui-kjsmvj">
        <div class="modal-body-pos" data-ui="xui-pi6pkp">
            <div data-ui="xui-1fmkvgw">
                <i class="bx bx-check-circle" data-ui="xui-19c67ck"></i>
            </div>
            <h4 data-ui="xui-impet3">¡Pedido creado!</h4>
            <p data-ui="xui-1mfwfnd">
                Orden ORD-{{ str_pad((string) ($lastOrderFolio ?: $lastOrderId), 3, '0', STR_PAD_LEFT) }}
                @if($lastOrderType === 'pick_up') · Para recoger
                @elseif($lastOrderType === 'delivery') · Delivery
                @else · Ventanilla @endif
            </p>
            <div data-ui="xui-14ilvqh">
                <button wire:click="openReprintModal({{ $lastOrderId }})"
                        class="pos-btn pos-btn-secondary" data-ui="xui-12o4wxl">
                    <i class="bx bx-printer"></i> Ver e imprimir ticket
                </button>
                <button wire:click="startNewSale" class="pos-btn pos-btn-primary" data-ui="xui-12o4wxl">
                    <i class="bx bx-plus"></i> Nuevo pedido
                </button>
            </div>
        </div>
    </div>
</div>
@endif
