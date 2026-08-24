@if($showExpenseModal)
<div class="pos-modal-wrap show pos-operations-wrap" wire:click.self="$set('showExpenseModal', false)" role="dialog" aria-modal="true" aria-labelledby="operations-modal-title">
    <div class="pos-modal pos-operations-modal" @click.stop>
        <div class="modal-header-pos pos-operations-header">
            <i class="bx bx-transfer-alt"></i>
            <div>
                <span class="pos-operations-eyebrow">Control operativo</span>
                <h4 id="operations-modal-title">Registrar movimiento</h4>
                <p>Caja e inventario desde un solo lugar.</p>
            </div>
            <button type="button" wire:click="$set('showExpenseModal', false)" class="pos-btn pos-btn-secondary" aria-label="Cerrar movimientos">
                <i class="bx bx-x"></i>
            </button>
        </div>

        <div class="pos-operations-tabs" role="tablist" aria-label="Tipo de movimiento">
            @canany(['registrar movimientos de caja', 'registrar gastos'])
                <button type="button" role="tab" wire:click="openOperationsModal('expense')"
                    class="pos-operation-tab {{ $operationType === 'expense' ? 'is-active is-expense' : '' }}"
                    aria-selected="{{ $operationType === 'expense' ? 'true' : 'false' }}">
                    <span><i class="bx bx-trending-down"></i></span>
                    <div><strong>Salida de caja</strong><small>Registrar un gasto</small></div>
                </button>
                <button type="button" role="tab" wire:click="openOperationsModal('income')"
                    class="pos-operation-tab {{ $operationType === 'income' ? 'is-active is-income' : '' }}"
                    aria-selected="{{ $operationType === 'income' ? 'true' : 'false' }}">
                    <span><i class="bx bx-trending-up"></i></span>
                    <div><strong>Ingreso de caja</strong><small>Agregar efectivo</small></div>
                </button>
            @endcanany
            @canany(['registrar salida de insumos', 'ajustar inventario'])
                <button type="button" role="tab" wire:click="openOperationsModal('inventory_out')"
                    class="pos-operation-tab {{ $operationType === 'inventory_out' ? 'is-active is-inventory' : '' }}"
                    aria-selected="{{ $operationType === 'inventory_out' ? 'true' : 'false' }}">
                    <span><i class="bx bx-package"></i></span>
                    <div><strong>Salida de insumos</strong><small>Descontar inventario</small></div>
                </button>
            @endcanany
        </div>

        <div class="modal-body-pos pos-operations-body">
            @if($operationType === 'inventory_out')
                <div class="pos-operation-context is-inventory">
                    <i class="bx bx-info-circle"></i>
                    <p>Este movimiento descuenta la existencia inmediatamente y queda registrado con tu usuario.</p>
                </div>

                <div class="pos-operation-form-grid">
                    <label class="pos-operation-field is-wide">
                        <span>Insumo <b>*</b></span>
                        <select wire:model.live="inventoryItemId" class="co-input">
                            <option value="">Selecciona un insumo</option>
                            @foreach($this->operationInventoryItems as $item)
                                <option value="{{ $item->id }}">{{ $item->name }} · {{ rtrim(rtrim(number_format((float) $item->current_stock, 3, '.', ''), '0'), '.') }} {{ $item->unit_short }}</option>
                            @endforeach
                        </select>
                        @error('inventoryItemId')<small class="pos-operation-error">{{ $message }}</small>@enderror
                    </label>

                    <label class="pos-operation-field">
                        <span>Cantidad de salida <b>*</b></span>
                        <input type="number" wire:model="adjustQuantity" class="co-input" placeholder="0" step="0.001" min="0.001" inputmode="decimal">
                        @error('adjustQuantity')<small class="pos-operation-error">{{ $message }}</small>@enderror
                    </label>

                    <label class="pos-operation-field">
                        <span>Motivo <b>*</b></span>
                        <input type="text" wire:model="inventoryReason" class="co-input" maxlength="255" placeholder="Ej. Merma, consumo interno o traslado">
                        @error('inventoryReason')<small class="pos-operation-error">{{ $message }}</small>@enderror
                    </label>
                </div>
            @else
                <div class="pos-operation-context {{ $operationType === 'income' ? 'is-income' : 'is-expense' }}">
                    <i class="bx {{ $operationType === 'income' ? 'bx-plus-circle' : 'bx-minus-circle' }}"></i>
                    <p>{{ $operationType === 'income' ? 'El ingreso se suma al efectivo esperado de la caja abierta.' : 'Los gastos pagados en efectivo se descuentan del efectivo esperado.' }}</p>
                </div>

                <div class="pos-operation-form-grid">
                    <label class="pos-operation-field">
                        <span>Monto <b>*</b></span>
                        <div class="pos-operation-money">
                            <span>$</span>
                            <input type="number" wire:model="expenseAmount" class="co-input" placeholder="0.00" step="0.01" min="0.01" inputmode="decimal">
                        </div>
                        @error('expenseAmount')<small class="pos-operation-error">{{ $message }}</small>@enderror
                    </label>

                    <label class="pos-operation-field">
                        <span>Categoría <b>*</b></span>
                        <select wire:model="expenseCategory" class="co-input">
                            @if($operationType === 'income')
                                <option value="fondo">Fondo adicional</option>
                                <option value="devolucion">Devolución recibida</option>
                                <option value="otro_ingreso">Otro ingreso</option>
                            @else
                                <option value="insumos">Compra de insumos</option>
                                <option value="operativo">Operación</option>
                                <option value="personal">Personal</option>
                                <option value="otro">Otro</option>
                            @endif
                        </select>
                        @error('expenseCategory')<small class="pos-operation-error">{{ $message }}</small>@enderror
                    </label>

                    <label class="pos-operation-field is-wide">
                        <span>Concepto <b>*</b></span>
                        <input type="text" wire:model="expenseDescription" class="co-input" maxlength="255"
                            placeholder="{{ $operationType === 'income' ? '¿Por qué ingresó efectivo?' : '¿En qué se utilizó?' }}">
                        @error('expenseDescription')<small class="pos-operation-error">{{ $message }}</small>@enderror
                    </label>

                    @if($operationType === 'expense')
                        <label class="pos-operation-field">
                            <span>Método de pago</span>
                            <select wire:model="expensePaymentMethod" class="co-input">
                                <option value="cash">Efectivo</option>
                                <option value="card">Tarjeta</option>
                                <option value="transfer">Transferencia</option>
                            </select>
                            @error('expensePaymentMethod')<small class="pos-operation-error">{{ $message }}</small>@enderror
                        </label>
                    @else
                        <div class="pos-operation-field">
                            <span>Destino</span>
                            <div class="pos-operation-static"><i class="bx bx-money"></i><strong>Efectivo en caja</strong></div>
                        </div>
                    @endif

                    <label class="pos-operation-field">
                        <span>Notas <small>Opcional</small></span>
                        <input type="text" wire:model="expenseNotes" class="co-input" maxlength="1000" placeholder="Detalle adicional">
                        @error('expenseNotes')<small class="pos-operation-error">{{ $message }}</small>@enderror
                    </label>
                </div>
            @endif
        </div>

        <div class="modal-footer-pos pos-operations-footer">
            <button type="button" wire:click="$set('showExpenseModal', false)" class="pos-btn pos-btn-secondary">Cancelar</button>
            <button type="button" wire:click="saveOperation" wire:loading.attr="disabled" wire:target="saveOperation" class="pos-btn pos-btn-primary">
                <span wire:loading wire:target="saveOperation" class="spinner-border spinner-border-sm"></span>
                <i wire:loading.remove wire:target="saveOperation" class="bx {{ $operationType === 'inventory_out' ? 'bx-package' : ($operationType === 'income' ? 'bx-plus-circle' : 'bx-minus-circle') }}"></i>
                <span>{{ $operationType === 'inventory_out' ? 'Registrar salida' : ($operationType === 'income' ? 'Registrar ingreso' : 'Registrar salida') }}</span>
            </button>
        </div>
    </div>
</div>
@endif
