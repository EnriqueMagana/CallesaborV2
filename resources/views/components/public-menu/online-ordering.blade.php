@props(['business'])
<div data-online-ordering data-store-url="{{ route('online-orders.store') }}">
    <button type="button" class="online-cart-fab" data-online-cart-open aria-label="Abrir carrito">
        <i class="bx bx-shopping-bag"></i><span>Mi pedido</span><strong data-online-cart-count>0</strong>
    </button>

    <dialog class="online-dialog online-customizer" id="online-customizer-dialog" aria-labelledby="online-customizer-title">
        <form method="dialog" class="online-dialog__shell" data-online-customizer-form>
            <header><div><span>Personaliza tu pedido</span><h2 id="online-customizer-title" data-customizer-title></h2></div><button value="cancel" aria-label="Cerrar"><i class="bx bx-x"></i></button></header>
            <div class="online-dialog__body" data-customizer-options></div>
            <footer><label>Cantidad <input type="number" min="1" max="99" value="1" data-customizer-quantity></label><strong data-customizer-total></strong><button type="button" class="online-primary" data-customizer-add>Agregar</button></footer>
        </form>
    </dialog>

    <dialog class="online-dialog online-cart" id="online-cart-dialog" aria-labelledby="online-cart-title">
        <div class="online-dialog__shell">
            <header><div><span>Compra sin crear una cuenta</span><h2 id="online-cart-title">Tu pedido</h2></div><button type="button" data-online-cart-close aria-label="Cerrar"><i class="bx bx-x"></i></button></header>
            <div class="online-progress" data-online-progress><span class="is-active">1 Pedido</span><span>2 Servicio</span><span>3 Tus datos</span><span>4 Pago</span></div>
            <form class="online-checkout" data-online-checkout novalidate>
                <section class="online-step is-active" data-online-step="1"><div data-online-cart-lines></div><div class="online-cart-total"><span>Total estimado</span><strong data-online-cart-total>$0.00</strong></div></section>
                <section class="online-step" data-online-step="2"><h3>¿Cómo recibirás tu pedido?</h3><div class="online-choice-grid"><label><input type="radio" name="fulfillment" value="takeaway" checked><span><i class="bx bx-store"></i><strong>Pasaré a recoger</strong><small>Te avisaremos cuando esté listo.</small></span></label><label><input type="radio" name="fulfillment" value="delivery"><span><i class="bx bx-cycling"></i><strong>Servicio a domicilio</strong><small>Entregaremos en la dirección indicada.</small></span></label></div></section>
                <section class="online-step" data-online-step="3"><h3>Datos de contacto</h3><div class="online-fields"><label class="is-wide">Nombre completo y apellidos<input name="customer_name" autocomplete="name" maxlength="160" required></label><label>Teléfono<input name="customer_phone" inputmode="tel" autocomplete="tel" maxlength="20" required></label><label class="is-wide" data-delivery-field hidden>Calle, número e interior<input name="customer_address" autocomplete="street-address" maxlength="255"></label><label data-delivery-field hidden>Colonia<input name="customer_neighborhood" maxlength="120"></label><label class="is-wide" data-delivery-field hidden>Referencias<textarea name="customer_references" maxlength="500"></textarea></label><label class="is-wide">Indicaciones del pedido<textarea name="notes" maxlength="500" placeholder="Ej. sin cubiertos"></textarea></label></div></section>
                <section class="online-step" data-online-step="4"><h3>¿Cómo planeas pagar?</h3><div class="online-choice-grid"><label><input type="radio" name="payment_method" value="efectivo" checked><span><i class="bx bx-money"></i><strong>Efectivo</strong><small>Indica con cuánto pagas.</small></span></label><label><input type="radio" name="payment_method" value="tarjeta"><span><i class="bx bx-credit-card"></i><strong>Tarjeta</strong><small>Podrás ajustarlo al pagar.</small></span></label><label><input type="radio" name="payment_method" value="transferencia"><span><i class="bx bx-transfer"></i><strong>Transferencia</strong><small>El restaurante confirmará los datos.</small></span></label></div><label class="online-cash-field">¿Con cuánto pagas?<input type="number" name="cash_tendered" min="0" step="0.01" inputmode="decimal"><small data-online-change></small></label><div class="online-whatsapp-notice"><i class="bx bxl-whatsapp"></i><p><strong>Último paso obligatorio</strong> Al ordenar abriremos WhatsApp para que envíes la solicitud. El pedido no se prepara hasta que el restaurante lo confirme.</p></div></section>
                <p class="online-form-error" data-online-error role="alert" hidden></p>
                <footer>
                    <button type="button" class="online-secondary" data-online-prev hidden>Anterior</button>
                    <button type="button" class="online-primary" data-online-next>Continuar</button>
                    <button type="submit" class="online-primary online-order-submit" data-online-submit hidden>
                        <span data-submit-label><i class="bx bx-check-circle" aria-hidden="true"></i>Ordenar</span>
                        <span data-submit-loading hidden><i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i>Procesando pedido…</span>
                    </button>
                </footer>
            </form>
        </div>
    </dialog>
</div>
