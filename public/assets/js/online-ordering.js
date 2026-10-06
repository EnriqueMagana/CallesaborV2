(() => {
    const root = document.querySelector('[data-online-ordering]');
    if (!root) return;

    const storageKey = 'calle-sabor-online-cart-v1';
    const customizer = document.getElementById('online-customizer-dialog');
    const cartDialog = document.getElementById('online-cart-dialog');
    const optionsRoot = root.querySelector('[data-customizer-options]');
    const customizerTitle = root.querySelector('[data-customizer-title]');
    const customizerContext = root.querySelector('[data-customizer-context]');
    const customizerImage = root.querySelector('[data-customizer-image]');
    const customizerFallback = root.querySelector('[data-customizer-fallback]');
    const customizerQuantity = root.querySelector('[data-customizer-quantity]');
    const customizerTotal = root.querySelector('[data-customizer-total]');
    const customizerAdd = root.querySelector('[data-customizer-add]');
    const customizerActionLabel = root.querySelector('[data-customizer-action-label]');
    const checkout = root.querySelector('[data-online-checkout]');
    const errorBox = root.querySelector('[data-online-error]');
    let activeItem = null;
    let editingIndex = null;
    let step = 1;
    let cart = [];
    let isSubmitting = false;

    try { cart = JSON.parse(localStorage.getItem(storageKey) || '[]'); } catch { cart = []; }
    if (!Array.isArray(cart)) cart = [];

    const money = value => `$${Number(value || 0).toFixed(2)}`;
    const escape = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
    const sum = values => Object.values(values).reduce((total, value) => total + Number(value || 0), 0);
    const estimatedTotal = () => cart.reduce((total, line) => total + Number(line.display_subtotal || 0), 0);
    const persist = () => { localStorage.setItem(storageKey, JSON.stringify(cart)); renderCart(); };

    document.addEventListener('click', event => {
        const productTrigger = event.target.closest('[data-product-detail]');
        const promotionTrigger = event.target.closest('[data-promotion-detail]');
        if (productTrigger) {
            try { activeItem = { type: 'product', data: JSON.parse(productTrigger.dataset.productDetail) }; } catch { activeItem = null; }
        }
        if (promotionTrigger) {
            try { activeItem = { type: 'promotion', data: JSON.parse(promotionTrigger.dataset.promotionDetail) }; } catch { activeItem = null; }
        }
    }, true);

    root.querySelector('[data-online-cart-open]').addEventListener('click', () => { step = 1; showStep(); cartDialog.showModal(); });
    root.querySelector('[data-online-cart-close]').addEventListener('click', () => cartDialog.close());
    document.querySelector('[data-online-add-product]')?.addEventListener('click', () => openCustomizer('product'));
    document.querySelector('[data-online-add-promotion]')?.addEventListener('click', () => openCustomizer('promotion'));

    function openCustomizer(type, lineIndex = null) {
        if (!activeItem || activeItem.type !== type) return;
        document.getElementById(type === 'product' ? 'product-detail-modal' : 'promotion-detail-modal')?.close();
        editingIndex = Number.isInteger(lineIndex) ? lineIndex : null;
        customizerTitle.textContent = activeItem.data.name;
        customizerImage.hidden = !activeItem.data.image;
        customizerFallback.hidden = Boolean(activeItem.data.image);
        if (activeItem.data.image) {
            customizerImage.src = activeItem.data.image;
            customizerImage.alt = activeItem.data.name;
        } else {
            customizerImage.removeAttribute('src');
            customizerImage.alt = '';
        }
        customizerActionLabel.textContent = editingIndex === null ? 'Agregar' : 'Guardar cambios';
        optionsRoot.innerHTML = type === 'product' ? productOptions(activeItem.data) : promotionOptions(activeItem.data);
        optionsRoot.insertAdjacentHTML('beforeend', '<label class="online-notes"><span>Indicaciones especiales <small>Opcional</small></span><textarea data-line-notes maxlength="300" placeholder="Ej. sin cebolla, salsa aparte"></textarea></label>');
        hydrateCustomizer(editingIndex === null ? null : cart[editingIndex]);
        refreshCustomizerState();
        customizer.showModal();
    }

    function productOptions(product) {
        let html = '';
        (product.addonGroups || []).forEach(group => {
            const minimum = group.required ? Math.max(1, Number(group.minimum || 0)) : Number(group.minimum || 0);
            const maximum = Math.max(1, Number(group.maximum || 1));
            html += groupStart('addon', group.id, group.name, minimum, maximum, minimum > 0, group.description || 'Elige las opciones que prefieras.');
            group.options.forEach(item => { html += quantityOption('addon', item); });
            html += '</div></section>';
        });
        if (product.ingredients?.length) {
            const minimum = Number(product.minIngredients || 0);
            const maximum = Number(product.maxIngredients || 0);
            html += groupStart('ingredient', '', 'Ingredientes', minimum, maximum, minimum > 0, 'Personaliza los ingredientes de tu platillo.');
            product.ingredients.forEach(item => { html += quantityOption('ingredient', item); });
            html += '</div></section>';
        }
        return html;
    }

    function promotionOptions(promotion) {
        return (promotion.groups || []).map(group => {
            const minimum = Number(group.minimum || 0);
            const maximum = Math.max(1, Number(group.maximum || 1));
            return `${groupStart('promotion', group.id, group.name, minimum, maximum, minimum > 0, group.rule || 'Completa este grupo.')}${group.products.map(product => quantityOption('promotion', product)).join('')}</div></section>`;
        }).join('');
    }

    function groupStart(kind, id, name, minimum, maximum, required, description) {
        const limit = maximum > 0 ? maximum : 'Sin l\u00edmite';
        return `<section class="online-option-group" data-option-group data-group-kind="${kind}" data-group-id="${id}" data-min="${minimum}" data-max="${maximum}">
            <header><div><span class="online-option-group__icon"><i class="bx bx-list-check"></i></span><span><h3>${escape(name)}</h3><p>${escape(description)}</p></span></div><b data-group-status>${required ? 'Obligatorio' : 'Opcional'}</b></header>
            <div class="online-option-group__progress" aria-live="polite"><span data-group-count>0 de ${limit}</span><span data-group-message>${minimum > 0 ? `Elige al menos ${minimum}` : 'Puedes omitir este grupo'}</span></div>
            <div class="online-option-grid">`;
    }

    function quantityOption(kind, item) {
        const media = item.image
            ? `<img src="${escape(item.image)}" alt="" width="56" height="56" loading="lazy">`
            : '<i class="bx bx-dish" aria-hidden="true"></i>';
        const price = item.extraPrice || '';
        return `<article class="online-quantity-option" data-option-row>
            <span class="online-quantity-option__media">${media}</span>
            <span class="online-quantity-option__copy"><strong>${escape(item.name)}</strong><small>${escape(item.description || price || 'Incluido')}</small>${item.description && price ? `<b>${escape(price)}</b>` : ''}</span>
            <div class="online-option-stepper" role="group" aria-label="Cantidad de ${escape(item.name)}">
                <button type="button" data-option-decrement aria-label="Quitar ${escape(item.name)}" disabled><i class="bx bx-minus"></i></button>
                <input type="number" value="0" min="0" max="99" inputmode="numeric" data-kind="${kind}" data-option-id="${item.id}" aria-label="Cantidad seleccionada de ${escape(item.name)}">
                <button type="button" data-option-increment aria-label="Agregar ${escape(item.name)}"><i class="bx bx-plus"></i></button>
            </div>
        </article>`;
    }

    function hydrateCustomizer(line) {
        customizerQuantity.value = line?.quantity || 1;
        optionsRoot.querySelector('[data-line-notes]').value = line?.notes || '';
        if (!line) return;
        const maps = { addon: line.addon_quantities || {}, ingredient: line.ingredient_quantities || {}, promotion: {} };
        (line.promotion_selections || []).forEach(group => {
            (group.items || []).forEach(item => { maps.promotion[item.product_id] = item.quantity; });
        });
        optionsRoot.querySelectorAll('[data-kind]').forEach(input => {
            input.value = Number(maps[input.dataset.kind]?.[input.dataset.optionId] || 0);
        });
    }

    const groupInputs = group => [...group.querySelectorAll('[data-kind]')];
    const groupTotal = group => groupInputs(group).reduce((total, input) => total + Number(input.value || 0), 0);
    const kindTotal = kind => [...optionsRoot.querySelectorAll(`[data-kind="${kind}"]`)].reduce((total, input) => total + Number(input.value || 0), 0);

    function canIncrement(input) {
        const group = input.closest('[data-option-group]');
        if (!group) return false;
        const maximum = Number(group.dataset.max || 0);
        const currentGroupTotal = groupTotal(group);
        const isReplacement = maximum === 1 && Number(input.value || 0) === 0;
        if (maximum > 0 && currentGroupTotal >= maximum && !isReplacement) return false;
        if (input.dataset.kind === 'ingredient') {
            const ingredientMaximum = Number(activeItem?.data?.maxIngredients || 0);
            return ingredientMaximum === 0 || kindTotal('ingredient') < ingredientMaximum;
        }
        if (input.dataset.kind === 'addon') {
            const productMaximum = Number(activeItem?.data?.maxAddons || 0);
            const prospective = isReplacement ? kindTotal('addon') - currentGroupTotal + 1 : kindTotal('addon') + 1;
            return productMaximum === 0 || prospective <= productMaximum;
        }
        return true;
    }

    function adjustOption(button, delta) {
        const row = button.closest('[data-option-row]');
        const input = row?.querySelector('[data-kind]');
        const group = row?.closest('[data-option-group]');
        if (!input || !group) return;
        const current = Number(input.value || 0);
        if (delta > 0) {
            if (!canIncrement(input)) return;
            if (Number(group.dataset.max || 0) === 1) {
                groupInputs(group).forEach(option => { option.value = 0; });
                input.value = 1;
            } else {
                input.value = Math.min(99, current + 1);
            }
        } else {
            input.value = Math.max(0, current - 1);
        }
        clearCustomizerError();
        refreshCustomizerState();
    }

    function setOptionValue(input, requestedValue) {
        const group = input.closest('[data-option-group]');
        if (!group) return;
        const maximum = Number(group.dataset.max || 0);
        const current = Number(input.value || 0);
        let requested = Math.max(0, Math.min(99, Number.isFinite(requestedValue) ? Math.trunc(requestedValue) : 0));

        if (maximum === 1 && requested > 0) {
            groupInputs(group).forEach(option => { option.value = 0; });
            input.value = 1;
            return;
        }

        const groupWithoutCurrent = groupTotal(group) - current;
        if (maximum > 0) requested = Math.min(requested, Math.max(0, maximum - groupWithoutCurrent));

        const globalMaximum = input.dataset.kind === 'ingredient'
            ? Number(activeItem?.data?.maxIngredients || 0)
            : (input.dataset.kind === 'addon' ? Number(activeItem?.data?.maxAddons || 0) : 0);
        if (globalMaximum > 0) {
            const kindWithoutCurrent = kindTotal(input.dataset.kind) - current;
            requested = Math.min(requested, Math.max(0, globalMaximum - kindWithoutCurrent));
        }

        input.value = requested;
    }

    function isCustomizerValid() {
        return [...optionsRoot.querySelectorAll('[data-option-group]')].every(group => {
            const count = groupTotal(group);
            const minimum = Number(group.dataset.min || 0);
            const maximum = Number(group.dataset.max || 0);
            return count >= minimum && (maximum === 0 || count <= maximum);
        });
    }

    function refreshCustomizerState() {
        optionsRoot.querySelectorAll('[data-option-group]').forEach(group => {
            const count = groupTotal(group);
            const minimum = Number(group.dataset.min || 0);
            const maximum = Number(group.dataset.max || 0);
            const complete = count >= minimum;
            const visuallyComplete = count > 0 || (minimum > 0 && complete);
            const atLimit = maximum > 0 && count >= maximum;
            group.classList.toggle('is-complete', visuallyComplete);
            group.classList.toggle('is-at-limit', atLimit);
            group.querySelector('[data-group-status]').textContent = atLimit ? 'L\u00edmite alcanzado' : (visuallyComplete ? 'Listo' : (minimum > 0 ? 'Obligatorio' : 'Opcional'));
            group.querySelector('[data-group-count]').textContent = maximum > 0 ? `${count} de ${maximum}` : `${count} seleccionados`;
            group.querySelector('[data-group-message]').textContent = atLimit ? 'Alcanzaste el m\u00e1ximo permitido' : (complete ? (count ? 'Selecci\u00f3n completa' : 'Puedes omitir este grupo') : `Te faltan ${minimum - count}`);
            groupInputs(group).forEach(input => {
                const row = input.closest('[data-option-row]');
                row.classList.toggle('is-selected', Number(input.value || 0) > 0);
                row.querySelector('[data-option-decrement]').disabled = Number(input.value || 0) === 0;
                row.querySelector('[data-option-increment]').disabled = !canIncrement(input);
            });
        });
        const addonMaximum = Number(activeItem?.type === 'product' ? activeItem.data.maxAddons || 0 : 0);
        const addonLimitReached = addonMaximum > 0 && kindTotal('addon') >= addonMaximum;
        const basePrice = money(activeItem?.data?.priceValue || 0);
        const productLimit = addonMaximum > 0 ? ` \u00b7 M\u00e1ximo ${addonMaximum} complementos` : '';
        customizerContext.textContent = addonLimitReached
            ? `${basePrice} base${productLimit} \u00b7 L\u00edmite alcanzado`
            : `${basePrice} base${productLimit}`;
        customizerAdd.disabled = !isCustomizerValid();
        root.querySelector('[data-customizer-quantity-minus]').disabled = Number(customizerQuantity.value) <= 1;
        root.querySelector('[data-customizer-quantity-plus]').disabled = Number(customizerQuantity.value) >= 99;
        updateCustomizerTotal();
    }

    optionsRoot.addEventListener('click', event => {
        const decrement = event.target.closest('[data-option-decrement]');
        const increment = event.target.closest('[data-option-increment]');
        if (decrement) adjustOption(decrement, -1);
        if (increment) adjustOption(increment, 1);
    });
    optionsRoot.addEventListener('focusin', event => {
        if (event.target.matches('[data-kind]')) event.target.select();
    });
    optionsRoot.addEventListener('click', event => {
        if (event.target.matches('[data-kind]')) event.target.select();
    });
    optionsRoot.addEventListener('input', event => {
        const input = event.target.closest('[data-kind]');
        if (!input) return;
        setOptionValue(input, Number(input.value));
        clearCustomizerError();
        refreshCustomizerState();
    });
    root.querySelector('[data-customizer-quantity-minus]').addEventListener('click', () => {
        customizerQuantity.value = Math.max(1, Number(customizerQuantity.value || 1) - 1);
        refreshCustomizerState();
    });
    root.querySelector('[data-customizer-quantity-plus]').addEventListener('click', () => {
        customizerQuantity.value = Math.min(99, Number(customizerQuantity.value || 1) + 1);
        refreshCustomizerState();
    });
    customizerQuantity.addEventListener('focus', () => customizerQuantity.select());
    customizerQuantity.addEventListener('click', () => customizerQuantity.select());
    customizerQuantity.addEventListener('input', () => {
        customizerQuantity.value = Math.max(1, Math.min(99, Math.trunc(Number(customizerQuantity.value) || 1)));
        refreshCustomizerState();
    });

    function updateCustomizerTotal() {
        const data = activeItem?.data || {};
        let unit = Number(data.priceValue || 0);
        if (activeItem?.type === 'product') {
            optionsRoot.querySelectorAll('[data-kind="addon"], [data-kind="ingredient"]').forEach(input => {
                const collection = input.dataset.kind === 'addon' ? data.addonGroups.flatMap(group => group.options) : data.ingredients;
                const item = collection.find(option => option.id === Number(input.dataset.optionId));
                unit += Number(item?.extraPriceValue || 0) * Number(input.value || 0);
            });
        }
        customizerTotal.textContent = money(unit * Math.max(1, Number(customizerQuantity.value || 1)));
    }

    customizerAdd.addEventListener('click', () => {
        if (!activeItem || !isCustomizerValid()) return;
        const quantity = Math.max(1, Math.min(99, Number(customizerQuantity.value || 1)));
        const selected = inputMap();
        let payload;
        if (activeItem.type === 'product') {
            const product = activeItem.data;
            const ingredientCount = sum(selected.ingredient);
            if (ingredientCount < Number(product.minIngredients || 0) || (Number(product.maxIngredients || 0) > 0 && ingredientCount > Number(product.maxIngredients))) return showCustomizerError('Revisa la cantidad de ingredientes.');
            if (Number(product.maxAddons || 0) > 0 && sum(selected.addon) > Number(product.maxAddons)) return showCustomizerError(`Este producto permite hasta ${product.maxAddons} complementos.`);
            for (const group of product.addonGroups || []) {
                const count = group.options.reduce((total, item) => total + Number(selected.addon[item.id] || 0), 0);
                const minimum = group.required ? Math.max(1, Number(group.minimum)) : Number(group.minimum);
                if (count < minimum || count > Number(group.maximum)) return showCustomizerError(`Revisa las selecciones de ${group.name}.`);
            }
            const addons = (product.addonGroups || []).flatMap(group => group.options).filter(item => selected.addon[item.id]).map(item => ({ ...item, quantity: selected.addon[item.id] }));
            const ingredients = (product.ingredients || []).filter(item => selected.ingredient[item.id]).map(item => ({ ...item, quantity: selected.ingredient[item.id] }));
            const unit = Number(product.priceValue) + addons.reduce((total, item) => total + Number(item.extraPriceValue) * item.quantity, 0) + ingredients.reduce((total, item) => total + Number(item.extraPriceValue) * item.quantity, 0);
            payload = { product_id: product.id, quantity, addon_quantities: selected.addon, ingredient_quantities: selected.ingredient, notes: optionsRoot.querySelector('[data-line-notes]').value.trim(), display_name: product.name, display_details: [...addons, ...ingredients].map(item => `${item.name} \u00d7${item.quantity}`), display_subtotal: unit * quantity };
        } else {
            const promotion = activeItem.data;
            const snapshots = [];
            for (const group of promotion.groups || []) {
                const items = group.products.filter(product => selected.promotion[product.id]).map(product => ({ product_id: product.id, product_name: product.name, quantity: selected.promotion[product.id] }));
                const count = items.reduce((total, item) => total + item.quantity, 0);
                if (count < Number(group.minimum) || count > Number(group.maximum)) return showCustomizerError(`Revisa las selecciones de ${group.name}.`);
                snapshots.push({ group_id: group.id, group_name: group.name, min_selections: group.minimum, max_selections: group.maximum, items });
            }
            payload = { promotion_id: promotion.id, promotion_selections: snapshots, quantity, display_name: promotion.name, display_details: snapshots.flatMap(group => group.items.map(item => `${item.product_name} \u00d7${item.quantity}`)), display_subtotal: Number(promotion.priceValue) * quantity };
        }
        const line = { ...payload, key: editingIndex === null ? (crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`) : cart[editingIndex].key, editor_item: activeItem };
        if (editingIndex === null) cart.push(line); else cart[editingIndex] = line;
        persist();
        customizer.close();
        editingIndex = null;
        root.querySelector('[data-online-cart-open]').classList.add('is-bouncing');
        setTimeout(() => root.querySelector('[data-online-cart-open]').classList.remove('is-bouncing'), 450);
    });

    function inputMap() {
        const values = { addon: {}, ingredient: {}, promotion: {} };
        optionsRoot.querySelectorAll('[data-kind]').forEach(input => {
            const value = Math.max(0, Number(input.value || 0));
            if (value) values[input.dataset.kind][input.dataset.optionId] = value;
        });
        return values;
    }

    function showCustomizerError(message) {
        let box = optionsRoot.querySelector('.online-customizer-error');
        if (!box) {
            box = document.createElement('p');
            box.className = 'online-customizer-error';
            box.setAttribute('role', 'alert');
            optionsRoot.prepend(box);
        }
        box.textContent = message;
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    function clearCustomizerError() { optionsRoot.querySelector('.online-customizer-error')?.remove(); }

    function renderCart() {
        const count = cart.reduce((total, line) => total + Number(line.quantity), 0);
        root.querySelector('[data-online-cart-count]').textContent = count;
        root.querySelector('[data-online-cart-count]').hidden = count === 0;
        root.querySelector('[data-online-cart-total]').textContent = money(estimatedTotal());
        root.querySelector('[data-online-cart-lines]').innerHTML = cart.length
            ? cart.map((line, index) => `<article class="online-cart-line"><div><strong>${line.quantity}\u00d7 ${escape(line.display_name)}</strong><small>${escape((line.display_details || []).join(' \u00b7 '))}</small><span class="online-cart-line__actions">${line.editor_item ? `<button type="button" class="online-cart-line__edit" data-edit-line="${index}"><i class="bx bx-edit-alt"></i>Editar</button>` : ''}<button type="button" class="online-cart-line__remove" data-remove-line="${index}" aria-label="Quitar ${escape(line.display_name)}"><i class="bx bx-trash"></i>Quitar</button></span></div><b>${money(line.display_subtotal)}</b></article>`).join('')
            : '<div class="online-cart-empty"><i class="bx bx-shopping-bag"></i><h3>Tu carrito est\u00e1 vac\u00edo</h3><p>Abre un platillo y agr\u00e9galo a tu pedido.</p></div>';
        root.querySelector('[data-online-next]').disabled = cart.length === 0;
        root.querySelectorAll('[data-remove-line]').forEach(button => button.addEventListener('click', () => { cart.splice(Number(button.dataset.removeLine), 1); persist(); }));
        root.querySelectorAll('[data-edit-line]').forEach(button => button.addEventListener('click', () => {
            const index = Number(button.dataset.editLine);
            if (!cart[index]?.editor_item) return;
            activeItem = cart[index].editor_item;
            cartDialog.close();
            openCustomizer(activeItem.type, index);
        }));
        updateChange();
    }

    function showStep() {
        root.querySelectorAll('[data-online-step]').forEach(section => section.classList.toggle('is-active', Number(section.dataset.onlineStep) === step));
        root.querySelectorAll('[data-online-progress] span').forEach((item, index) => item.classList.toggle('is-active', index < step));
        root.querySelector('[data-online-prev]').hidden = step === 1;
        root.querySelector('[data-online-next]').hidden = step === 4;
        root.querySelector('[data-online-submit]').hidden = step !== 4;
        errorBox.hidden = true;
    }
    root.querySelector('[data-online-next]').addEventListener('click', () => { if (step === 1 && !cart.length) return; if (step === 3 && !validateContact()) return; step = Math.min(4, step + 1); showStep(); });
    root.querySelector('[data-online-prev]').addEventListener('click', () => { step = Math.max(1, step - 1); showStep(); });

    checkout.addEventListener('change', event => { if (event.target.name === 'fulfillment') toggleDeliveryFields(); if (event.target.name === 'payment_method') toggleCashField(); });
    checkout.querySelector('[name="cash_tendered"]').addEventListener('input', updateChange);
    function toggleDeliveryFields() { const delivery = checkout.elements.fulfillment.value === 'delivery'; root.querySelectorAll('[data-delivery-field]').forEach(field => { field.hidden = !delivery; const input = field.querySelector('input,textarea'); input.required = delivery && input.name !== 'customer_references'; }); }
    function toggleCashField() { root.querySelector('.online-cash-field').hidden = checkout.elements.payment_method.value !== 'efectivo'; }
    function updateChange() { const input = checkout.querySelector('[name="cash_tendered"]'); const change = Number(input.value || 0) - estimatedTotal(); root.querySelector('[data-online-change]').textContent = input.value ? (change >= 0 ? `Cambio estimado: ${money(change)}` : 'El monto es menor al total estimado.') : ''; }
    function validateContact() { toggleDeliveryFields(); const fields = [...checkout.querySelectorAll('[data-online-step="3"] [required]')]; const invalid = fields.find(field => !field.value.trim()); if (invalid) { invalid.focus(); showError('Completa los datos requeridos para continuar.'); return false; } return true; }
    function showError(message) { errorBox.textContent = message; errorBox.hidden = false; }
    function setSubmitting(loading) { isSubmitting = loading; checkout.setAttribute('aria-busy', String(loading)); root.querySelector('[data-online-submit]').disabled = loading; root.querySelector('[data-online-prev]').disabled = loading; root.querySelector('[data-online-next]').disabled = loading || (step === 1 && cart.length === 0); root.querySelector('[data-submit-label]').hidden = loading; root.querySelector('[data-submit-loading]').hidden = !loading; }

    checkout.addEventListener('submit', async event => {
        event.preventDefault();
        if (isSubmitting) return;
        const form = new FormData(checkout);
        if (!validateContact()) { step = 3; showStep(); return; }
        if (form.get('payment_method') === 'efectivo' && Number(form.get('cash_tendered') || 0) < estimatedTotal()) return showError('Indica un monto suficiente para calcular el cambio.');
        const whatsappWindow = window.open('about:blank', '_blank');
        if (whatsappWindow) whatsappWindow.document.title = 'Preparando WhatsApp\u2026';
        setSubmitting(true);
        const payload = Object.fromEntries(form.entries());
        payload.items = cart.map(({ display_name, display_details, display_subtotal, key, editor_item, ...line }) => line);
        try {
            const response = await fetch(root.dataset.storeUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(payload) });
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'No se pudo generar el pedido.');
            cart = [];
            persist();
            if (whatsappWindow) { whatsappWindow.opener = null; whatsappWindow.location.replace(data.whatsapp_url); }
            window.location.replace(data.tracking_url);
        } catch (error) {
            whatsappWindow?.close();
            showError(error.message);
            setSubmitting(false);
        }
    });

    renderCart();
    toggleDeliveryFields();
    toggleCashField();
})();
