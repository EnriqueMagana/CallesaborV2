@if($showExpenseModal)
<div class="pos-modal-wrap show" wire:click.self="$set('showExpenseModal',false)" role="dialog" aria-modal="true" aria-labelledby="expense-modal-title">
    <div class="pos-modal" @click.stop>
        <div class="modal-header-pos">
            <i class="bx bx-wallet" data-ui="xui-1vp2017"></i>
            <h4 id="expense-modal-title">Registrar gasto</h4>
            <button type="button" wire:click="$set('showExpenseModal',false)" class="pos-btn pos-btn-secondary" aria-label="Cerrar" data-ui="xui-1a0g5qw"><i class="bx bx-x"></i></button>
        </div>
        <div class="modal-body-pos">
            <div class="co-grid">
                <div>
                    <label class="co-label">Monto <span class="req">*</span></label>
                    <input type="number" wire:model="expenseAmount" class="co-input" placeholder="0.00" step="0.01">
                    @error('expenseAmount')<div data-ui="xui-1bwpvep">{{ $message }}</div>@enderror
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
                    @error('expenseDescription')<div data-ui="xui-1bwpvep">{{ $message }}</div>@enderror
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
                    <label class="co-label">Notas <span data-ui="xui-b1shr1">(opcional)</span></label>
                    <input type="text" wire:model="expenseNotes" class="co-input" placeholder="Detalle adicional…">
                </div>
            </div>
        </div>
        <div class="modal-footer-pos">
            <button type="button" wire:click="$set('showExpenseModal',false)" class="pos-btn pos-btn-secondary">Cancelar</button>
            <button type="button" wire:click="saveExpense" wire:loading.attr="disabled" class="pos-btn pos-btn-primary">
                <span wire:loading wire:target="saveExpense" class="spinner-border spinner-border-sm" data-ui="xui-a2bbzz"></span>
                <i wire:loading.remove wire:target="saveExpense" class="bx bx-save"></i>
                Guardar gasto
            </button>
        </div>
    </div>
</div>
@endif
