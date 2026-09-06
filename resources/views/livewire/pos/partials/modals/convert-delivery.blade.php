@if ($showConvertDeliveryModal)
    <div class="pos-modal-wrap show" wire:click.self="closeConvertDeliveryModal">
        <div class="pos-modal pos-convert-delivery-modal" role="dialog" aria-modal="true" aria-labelledby="convert-delivery-title" x-on:click.stop="void 0">
            <header class="modal-header-pos">
                <span class="pos-modal-icon is-delivery"><i class="bx bx-cycling"></i></span>
                <div>
                    <span class="pos-modal-eyebrow">Actualizar entrega</span>
                    <h4 id="convert-delivery-title">Enviar pedido a delivery</h4>
                </div>
                <button type="button" class="btn-modal-close" wire:click="closeConvertDeliveryModal" aria-label="Cerrar"><i class="bx bx-x"></i></button>
            </header>

            <div class="modal-body-pos">
                <p class="pos-convert-delivery-intro"><i class="bx bx-info-circle"></i> Conserva los productos de la orden y agrega los datos para enviarla a domicilio.</p>
                <div class="co-grid">
                    <label class="co-field"><span class="co-label">Nombre del cliente</span><input class="co-input" type="text" wire:model.defer="convertDeliveryName" autocomplete="name" placeholder="Nombre completo">@error('convertDeliveryName')<small class="co-error">{{ $message }}</small>@enderror</label>
                    <label class="co-field"><span class="co-label">Teléfono (10 dígitos)</span><input class="co-input" type="tel" inputmode="numeric" maxlength="10" wire:model.defer="convertDeliveryPhone" autocomplete="tel" placeholder="5512345678">@error('convertDeliveryPhone')<small class="co-error">{{ $message }}</small>@enderror</label>
                    <label class="co-field co-field--full"><span class="co-label">Dirección de entrega</span><input class="co-input" type="text" wire:model.defer="convertDeliveryAddress" autocomplete="street-address" placeholder="Calle, número, colonia y ciudad">@error('convertDeliveryAddress')<small class="co-error">{{ $message }}</small>@enderror</label>
                    <label class="co-field co-field--full"><span class="co-label">Referencias <small>(opcional)</small></span><textarea class="co-input co-textarea" rows="2" maxlength="255" wire:model.defer="convertDeliveryReferences" placeholder="Entre calles, color de casa, indicaciones para el repartidor"></textarea>@error('convertDeliveryReferences')<small class="co-error">{{ $message }}</small>@enderror</label>
                </div>

                <div class="co-section-title">Forma de pago del delivery</div>
                <div class="pay-methods" role="radiogroup" aria-label="Forma de pago del delivery">
                    <button type="button" class="pay-btn {{ $convertDeliveryMethod === 'contra_entrega' ? 'active' : '' }}" wire:click="$set('convertDeliveryMethod','contra_entrega')"><i class="bx bx-package"></i><span>Contra entrega</span></button>
                    <button type="button" class="pay-btn {{ $convertDeliveryMethod === 'transferencia' ? 'active' : '' }}" wire:click="$set('convertDeliveryMethod','transferencia')"><i class="bx bx-transfer"></i><span>Transferencia</span></button>
                </div>
                <p class="pos-convert-delivery-note"><i class="bx bx-shield-quarter"></i> Los pedidos contra entrega no se registran en la caja local.</p>
            </div>

            <footer class="modal-footer-pos">
                <button type="button" class="pos-btn pos-btn-secondary" wire:click="closeConvertDeliveryModal">Cancelar</button>
                <button type="button" class="pos-btn pos-btn-primary" wire:click="convertOrderToDelivery" wire:loading.attr="disabled" wire:target="convertOrderToDelivery"><i class="bx bx-cycling"></i> Confirmar delivery</button>
            </footer>
        </div>
    </div>
@endif
