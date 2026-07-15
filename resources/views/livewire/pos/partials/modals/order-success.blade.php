@if($showOrderSuccess)
<div class="pos-modal-wrap show">
    <div class="pos-modal" style="max-width:400px;text-align:center">
        <div class="modal-body-pos" style="padding:32px 24px">
            <div style="width:64px;height:64px;border-radius:50%;background:rgba(40,167,69,.12);display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                <i class="bx bx-check-circle" style="font-size:2rem;color:#28a745"></i>
            </div>
            <h4 style="margin:0 0 6px;color:var(--pos-text)">¡Pedido creado!</h4>
            <p style="color:var(--pos-muted);font-size:.82rem;margin:0 0 20px">
                Orden #{{ $lastOrderId }}
                @if($lastOrderType === 'pick_up') · Para recoger
                @elseif($lastOrderType === 'delivery') · Delivery
                @else · Ventanilla @endif
            </p>
            <div style="display:flex;flex-direction:column;gap:8px">
                <button wire:click="openReprintModal({{ $lastOrderId }})"
                        class="pos-btn pos-btn-secondary" style="width:100%;justify-content:center">
                    <i class="bx bx-printer"></i> Ver e imprimir ticket
                </button>
                <button wire:click="startNewSale" class="pos-btn pos-btn-primary" style="width:100%;justify-content:center">
                    <i class="bx bx-plus"></i> Nuevo pedido
                </button>
            </div>
        </div>
    </div>
</div>
@endif
