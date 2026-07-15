@if($showExpenseModal)
<div class="pos-modal-wrap show" wire:click.self="$set('showExpenseModal',false)">
    <div class="pos-modal" @click.stop>
        <div class="modal-header-pos">
            <i class="bx bx-wallet" style="font-size:1.2rem;color:#f59e0b"></i>
            <h4>Registrar gasto</h4>
            <button wire:click="$set('showExpenseModal',false)" class="pos-btn pos-btn-secondary" style="padding:4px 8px"><i class="bx bx-x"></i></button>
        </div>
        <div class="modal-body-pos">
            <div class="co-grid">
                <div>
                    <label class="co-label">Monto <span class="req">*</span></label>
                    <input type="number" wire:model="expenseAmount" class="co-input" placeholder="0.00" step="0.01">
                    @error('expenseAmount')<div style="font-size:.72rem;color:var(--pos-danger);margin-top:3px">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="co-label">Categoría</label>
                    <select wire:model="expenseCategory" class="co-input">
                        <option value="insumos">Insumos</option>
                        <option value="operativo">Operativo</option>
                        <option value="personal">Personal</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
            </div>
            <div class="co-grid full">
                <div>
                    <label class="co-label">Descripción <span class="req">*</span></label>
                    <input type="text" wire:model="expenseDescription" class="co-input" placeholder="¿En qué se gastó?">
                    @error('expenseDescription')<div style="font-size:.72rem;color:var(--pos-danger);margin-top:3px">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="co-grid">
                <div>
                    <label class="co-label">Método de pago</label>
                    <select wire:model="expensePaymentMethod" class="co-input">
                        <option value="cash">Efectivo</option>
                        <option value="card">Tarjeta</option>
                        <option value="transfer">Transferencia</option>
                    </select>
                </div>
            </div>
            <div class="co-grid full">
                <div>
                    <label class="co-label">Notas <span style="color:var(--pos-muted);font-weight:400">(opcional)</span></label>
                    <input type="text" wire:model="expenseNotes" class="co-input" placeholder="Detalle adicional…">
                </div>
            </div>
        </div>
        <div class="modal-footer-pos">
            <button wire:click="$set('showExpenseModal',false)" class="pos-btn pos-btn-secondary">Cancelar</button>
            <button wire:click="saveExpense" wire:loading.attr="disabled" class="pos-btn pos-btn-primary">
                <span wire:loading wire:target="saveExpense" class="spinner-border spinner-border-sm" style="width:14px;height:14px"></span>
                <i wire:loading.remove wire:target="saveExpense" class="bx bx-save"></i>
                Guardar gasto
            </button>
        </div>
    </div>
</div>
@endif
