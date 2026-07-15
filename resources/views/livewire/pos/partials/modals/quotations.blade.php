@if($showQuotationsModal)
<div class="pos-modal-wrap show" wire:click.self="$set('showQuotationsModal',false)">
    <div class="pos-modal" @click.stop>
        <div class="modal-header-pos">
            <i class="bx bx-bookmark" style="font-size:1.2rem;color:var(--pos-accent)"></i>
            <h4>Cotizaciones guardadas</h4>
            <button wire:click="$set('showQuotationsModal',false)" class="pos-btn pos-btn-secondary" style="padding:4px 8px"><i class="bx bx-x"></i></button>
        </div>
        <div class="modal-body-pos">
            @forelse($this->quotations as $q)
                <div style="background:var(--pos-bg);border:1px solid var(--pos-border);border-radius:var(--pos-radius);padding:12px 14px;margin-bottom:8px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                        <span style="font-size:.85rem;font-weight:700;color:var(--pos-text)">{{ $q->display_name }}</span>
                        <span style="font-size:.9rem;font-weight:800;color:var(--pos-accent)">${{ number_format($q->total,2) }}</span>
                    </div>
                    @if($q->customer_name)<div style="font-size:.75rem;color:var(--pos-muted)"><i class="bx bx-user me-1"></i>{{ $q->customer_name }}</div>@endif
                    <div style="font-size:.72rem;color:var(--pos-muted);margin-top:2px">{{ $q->created_at->diffForHumans() }}</div>
                    <div style="display:flex;gap:6px;margin-top:8px">
                        <button wire:click="loadQuotation({{ $q->id }})" class="pos-btn pos-btn-primary" style="flex:1;justify-content:center;font-size:.78rem;padding:6px">
                            <i class="bx bx-upload"></i> Cargar
                        </button>
                        <button wire:click="deleteQuotation({{ $q->id }})" onclick="return confirm('¿Eliminar cotización?')"
                                class="pos-btn pos-btn-secondary" style="padding:6px 10px;font-size:.78rem">
                            <i class="bx bx-trash"></i>
                        </button>
                    </div>
                </div>
            @empty
                <div style="text-align:center;padding:40px 20px;color:var(--pos-muted)">
                    <i class="bx bx-bookmark" style="font-size:2.5rem;display:block;margin-bottom:8px;opacity:.3"></i>
                    <span style="font-size:.82rem">No hay cotizaciones guardadas</span>
                </div>
            @endforelse
        </div>
        <div class="modal-footer-pos">
            <button wire:click="$set('showQuotationsModal',false)" class="pos-btn pos-btn-secondary" style="width:100%;justify-content:center">Cerrar</button>
        </div>
    </div>
</div>
@endif

@if($showSaveQuotationModal)
<div class="pos-modal-wrap show" wire:click.self="$set('showSaveQuotationModal',false)">
    <div class="pos-modal" @click.stop>
        <div class="modal-header-pos">
            <i class="bx bx-save" style="font-size:1.2rem;color:var(--pos-accent)"></i>
            <h4>Guardar cotización</h4>
            <button wire:click="$set('showSaveQuotationModal',false)" class="pos-btn pos-btn-secondary" style="padding:4px 8px"><i class="bx bx-x"></i></button>
        </div>
        <div class="modal-body-pos">
            <div style="margin-bottom:12px">
                <label class="co-label">Nombre de la cotización</label>
                <input type="text" wire:model="quotationName" class="co-input" placeholder="Ej: Cumpleaños Carlos">
            </div>
            <div>
                <label class="co-label">Notas</label>
                <input type="text" wire:model="quotationNotes" class="co-input" placeholder="Observaciones opcionales…">
            </div>
        </div>
        <div class="modal-footer-pos">
            <button wire:click="$set('showSaveQuotationModal',false)" class="pos-btn pos-btn-secondary">Cancelar</button>
            <button wire:click="saveQuotation" class="pos-btn pos-btn-primary">
                <i class="bx bx-save"></i> Guardar
            </button>
        </div>
    </div>
</div>
@endif
