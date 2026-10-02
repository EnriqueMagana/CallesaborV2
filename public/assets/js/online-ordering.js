(() => {
    const root = document.querySelector('[data-online-ordering]');
    if (!root) return;

    const storageKey = 'calle-sabor-online-cart-v1';
    const customizer = document.getElementById('online-customizer-dialog');
    const cartDialog = document.getElementById('online-cart-dialog');
    const optionsRoot = root.querySelector('[data-customizer-options]');
    const customizerTitle = root.querySelector('[data-customizer-title]');
    const customizerQuantity = root.querySelector('[data-customizer-quantity]');
    const customizerTotal = root.querySelector('[data-customizer-total]');
    const checkout = root.querySelector('[data-online-checkout]');
    const errorBox = root.querySelector('[data-online-error]');
    let activeItem = null;
    let step = 1;
    let cart = [];
    let isSubmitting = false;

    try { cart = JSON.parse(localStorage.getItem(storageKey) || '[]'); } catch { cart = []; }
    if (!Array.isArray(cart)) cart = [];

    const money = value => `$${Number(value || 0).toFixed(2)}`;
    const escape = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
    const estimatedTotal = () => cart.reduce((sum, line) => sum + Number(line.display_subtotal || 0), 0);
    const persist = () => { localStorage.setItem(storageKey, JSON.stringify(cart)); renderCart(); };

    document.addEventListener('click', event => {
        const productTrigger = event.target.closest('[data-product-detail]');
        const promotionTrigger = event.target.closest('[data-promotion-detail]');
        if (productTrigger) { try { activeItem = { type: 'product', data: JSON.parse(productTrigger.dataset.productDetail) }; } catch {} }
        if (promotionTrigger) { try { activeItem = { type: 'promotion', data: JSON.parse(promotionTrigger.dataset.promotionDetail) }; } catch {} }
    }, true);

    root.querySelector('[data-online-cart-open]').addEventListener('click', () => { step = 1; showStep(); cartDialog.showModal(); });
    root.querySelector('[data-online-cart-close]').addEventListener('click', () => cartDialog.close());
    document.querySelector('[data-online-add-product]')?.addEventListener('click', () => openCustomizer('product'));
    document.querySelector('[data-online-add-promotion]')?.addEventListener('click', () => openCustomizer('promotion'));

    function openCustomizer(type) {
        if (!activeItem || activeItem.type !== type) return;
        document.getElementById(type === 'product' ? 'product-detail-modal' : 'promotion-detail-modal')?.close();
        customizerTitle.textContent = activeItem.data.name;
        customizerQuantity.value = 1;
        optionsRoot.innerHTML = type === 'product' ? productOptions(activeItem.data) : promotionOptions(activeItem.data);
        optionsRoot.insertAdjacentHTML('beforeend', '<label class="online-notes">Indicaciones para este producto<textarea data-line-notes maxlength="300" placeholder="Ej. sin cebolla"></textarea></label>');
        optionsRoot.querySelectorAll('input').forEach(input => input.addEventListener('change', updateCustomizerTotal));
        customizerQuantity.addEventListener('input', updateCustomizerTotal, { once: true });
        updateCustomizerTotal();
        customizer.showModal();
    }

    function productOptions(product) {
        let html = '';
        if (product.ingredients?.length) {
            html += `<section class="online-option-group"><h3>Ingredientes</h3><p>Elige entre ${product.minIngredients || 0} y ${product.maxIngredients || 'los que quieras'}.</p>`;
            product.ingredients.forEach(item => html += quantityOption('ingredient', item.id, item.name, item.extraPrice || 'Incluido'));
            html += '</section>';
        }
        (product.addonGroups || []).forEach(group => {
            html += `<section class="online-option-group" data-addon-group="${group.id}" data-min="${group.minimum}" data-max="${group.maximum}"><h3>${escape(group.name)}</h3><p>${group.required ? 'Obligatorio' : 'Opcional'} · ${group.minimum}–${group.maximum} selecciones.</p>`;
            group.options.forEach(item => html += quantityOption('addon', item.id, item.name, item.extraPrice));
            html += '</section>';
        });
        return html || '<p class="online-empty-options">Este producto no requiere personalización.</p>';
    }

    function promotionOptions(promotion) {
        return (promotion.groups || []).map(group => `<section class="online-option-group" data-promotion-group="${group.id}" data-min="${group.minimum}" data-max="${group.maximum}"><h3>${escape(group.name)}</h3><p>Elige de ${group.minimum} a ${group.maximum}.</p>${group.products.map(product => quantityOption('promotion', product.id, product.name, '')).join('')}</section>`).join('');
    }

    function quantityOption(kind, id, name, price) {
        return `<label class="online-quantity-option"><span><strong>${escape(name)}</strong><small>${escape(price)}</small></span><input type="number" value="0" min="0" max="99" inputmode="numeric" data-kind="${kind}" data-option-id="${id}"></label>`;
    }

    function updateCustomizerTotal() {
        const data = activeItem?.data || {};
        let unit = Number(data.priceValue || 0);
        if (activeItem?.type === 'product') {
            optionsRoot.querySelectorAll('[data-kind="addon"], [data-kind="ingredient"]').forEach(input => {
                const collection = input.dataset.kind === 'addon' ? data.addonGroups.flatMap(group => group.options) : data.ingredients;
                unit += Number(collection.find(item => item.id === Number(input.dataset.optionId))?.extraPriceValue || 0) * Number(input.value || 0);
            });
        }
        customizerTotal.textContent = money(unit * Math.max(1, Number(customizerQuantity.value || 1)));
    }

    root.querySelector('[data-customizer-add]').addEventListener('click', () => {
        if (!activeItem) return;
        const quantity = Math.max(1, Math.min(99, Number(customizerQuantity.value || 1)));
        const selected = inputMap();
        let payload;
        if (activeItem.type === 'product') {
            const product = activeItem.data;
            const ingredientCount = Object.values(selected.ingredient).reduce((a,b)=>a+b,0);
            if (ingredientCount < Number(product.minIngredients || 0) || (product.maxIngredients !== null && ingredientCount > Number(product.maxIngredients))) return showCustomizerError('Revisa la cantidad de ingredientes.');
            for (const group of product.addonGroups || []) {
                const count = group.options.reduce((sum, item) => sum + Number(selected.addon[item.id] || 0), 0);
                const min = group.required ? Math.max(1, Number(group.minimum)) : Number(group.minimum);
                if (count < min || count > Number(group.maximum)) return showCustomizerError(`Revisa las selecciones de ${group.name}.`);
            }
            const addons = (product.addonGroups || []).flatMap(group => group.options).filter(item => selected.addon[item.id]).map(item => ({...item, quantity:selected.addon[item.id]}));
            const ingredients = (product.ingredients || []).filter(item => selected.ingredient[item.id]).map(item => ({...item, quantity:selected.ingredient[item.id]}));
            const unit = Number(product.priceValue) + addons.reduce((s,a)=>s+Number(a.extraPriceValue)*a.quantity,0) + ingredients.reduce((s,i)=>s+Number(i.extraPriceValue)*i.quantity,0);
            payload = { product_id: product.id, quantity, addon_quantities:selected.addon, ingredient_quantities:selected.ingredient, notes: optionsRoot.querySelector('[data-line-notes]').value.trim(), display_name:product.name, display_details:[...addons,...ingredients].map(item=>`${item.name} ×${item.quantity}`), display_subtotal:unit*quantity };
        } else {
            const promotion = activeItem.data;
            const snapshots = [];
            for (const group of promotion.groups || []) {
                const items = group.products.filter(product => selected.promotion[product.id]).map(product => ({product_id:product.id, product_name:product.name, quantity:selected.promotion[product.id]}));
                const count = items.reduce((sum,item)=>sum+item.quantity,0);
                if (count < Number(group.minimum) || count > Number(group.maximum)) return showCustomizerError(`Revisa las selecciones de ${group.name}.`);
                snapshots.push({group_id:group.id, group_name:group.name, min_selections:group.minimum, max_selections:group.maximum, items});
            }
            payload = { promotion_id:promotion.id, promotion_selections:snapshots, quantity, display_name:promotion.name, display_details:snapshots.flatMap(group=>group.items.map(item=>`${item.product_name} ×${item.quantity}`)), display_subtotal:Number(promotion.priceValue)*quantity };
        }
        cart.push({...payload, key: crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`});
        persist();
        customizer.close();
        root.querySelector('[data-online-cart-open]').classList.add('is-bouncing');
        setTimeout(()=>root.querySelector('[data-online-cart-open]').classList.remove('is-bouncing'),450);
    });

    function inputMap() {
        const values = { addon:{}, ingredient:{}, promotion:{} };
        optionsRoot.querySelectorAll('[data-kind]').forEach(input => { const value = Math.max(0, Number(input.value || 0)); if (value) values[input.dataset.kind][input.dataset.optionId] = value; });
        return values;
    }
    function showCustomizerError(message) { let box=optionsRoot.querySelector('.online-customizer-error'); if(!box){box=document.createElement('p');box.className='online-customizer-error';optionsRoot.prepend(box)} box.textContent=message; }

    function renderCart() {
        const count = cart.reduce((sum,line)=>sum+Number(line.quantity),0);
        root.querySelector('[data-online-cart-count]').textContent = count;
        root.querySelector('[data-online-cart-count]').hidden = count === 0;
        root.querySelector('[data-online-cart-total]').textContent = money(estimatedTotal());
        root.querySelector('[data-online-cart-lines]').innerHTML = cart.length ? cart.map((line,index)=>`<article class="online-cart-line"><div><strong>${line.quantity}× ${escape(line.display_name)}</strong><small>${escape((line.display_details||[]).join(' · '))}</small></div><b>${money(line.display_subtotal)}</b><button type="button" data-remove-line="${index}" aria-label="Quitar ${escape(line.display_name)}"><i class="bx bx-trash"></i></button></article>`).join('') : '<div class="online-cart-empty"><i class="bx bx-shopping-bag"></i><h3>Tu carrito está vacío</h3><p>Abre un platillo y agrégalo a tu pedido.</p></div>';
        root.querySelector('[data-online-next]').disabled = cart.length === 0;
        root.querySelectorAll('[data-remove-line]').forEach(button=>button.addEventListener('click',()=>{cart.splice(Number(button.dataset.removeLine),1);persist()}));
        updateChange();
    }

    function showStep() {
        root.querySelectorAll('[data-online-step]').forEach(section=>section.classList.toggle('is-active', Number(section.dataset.onlineStep)===step));
        root.querySelectorAll('[data-online-progress] span').forEach((item,index)=>item.classList.toggle('is-active', index < step));
        root.querySelector('[data-online-prev]').hidden = step === 1;
        root.querySelector('[data-online-next]').hidden = step === 4;
        root.querySelector('[data-online-submit]').hidden = step !== 4;
        errorBox.hidden = true;
    }
    root.querySelector('[data-online-next]').addEventListener('click',()=>{ if(step===1&&!cart.length)return; if(step===3&&!validateContact())return; step=Math.min(4,step+1);showStep(); });
    root.querySelector('[data-online-prev]').addEventListener('click',()=>{step=Math.max(1,step-1);showStep()});

    checkout.addEventListener('change', event => {
        if (event.target.name === 'fulfillment') toggleDeliveryFields();
        if (event.target.name === 'payment_method') toggleCashField();
    });
    checkout.querySelector('[name="cash_tendered"]').addEventListener('input', updateChange);
    function toggleDeliveryFields(){const delivery=checkout.elements.fulfillment.value==='delivery';root.querySelectorAll('[data-delivery-field]').forEach(field=>{field.hidden=!delivery;field.querySelector('input,textarea').required=delivery&&field.querySelector('input,textarea').name!=='customer_references'})}
    function toggleCashField(){root.querySelector('.online-cash-field').hidden=checkout.elements.payment_method.value!=='efectivo'}
    function updateChange(){const input=checkout.querySelector('[name="cash_tendered"]');const change=Number(input.value||0)-estimatedTotal();root.querySelector('[data-online-change]').textContent=input.value?(change>=0?`Cambio estimado: ${money(change)}`:'El monto es menor al total estimado.'):''}
    function validateContact(){toggleDeliveryFields();const fields=[...checkout.querySelectorAll('[data-online-step="3"] [required]')];const invalid=fields.find(field=>!field.value.trim());if(invalid){invalid.focus();showError('Completa los datos requeridos para continuar.');return false}return true}
    function showError(message){errorBox.textContent=message;errorBox.hidden=false}

    function setSubmitting(loading) {
        isSubmitting = loading;
        checkout.setAttribute('aria-busy', String(loading));
        root.querySelector('[data-online-submit]').disabled = loading;
        root.querySelector('[data-online-prev]').disabled = loading;
        root.querySelector('[data-online-next]').disabled = loading || (step === 1 && cart.length === 0);
        root.querySelector('[data-submit-label]').hidden = loading;
        root.querySelector('[data-submit-loading]').hidden = !loading;
    }

    checkout.addEventListener('submit', async event => {
        event.preventDefault();
        if (isSubmitting) return;
        const form = new FormData(checkout);
        if (!validateContact()) { step=3;showStep();return; }
        if (form.get('payment_method')==='efectivo' && Number(form.get('cash_tendered')||0) < estimatedTotal()) return showError('Indica un monto suficiente para calcular el cambio.');
        const whatsappWindow = window.open('about:blank', '_blank');
        if (whatsappWindow) whatsappWindow.document.title = 'Preparando WhatsApp…';
        setSubmitting(true);
        const payload=Object.fromEntries(form.entries());payload.items=cart.map(({display_name,display_details,display_subtotal,key,...line})=>line);
        try {
            const response=await fetch(root.dataset.storeUrl,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(payload)});
            const data=await response.json();
            if(!response.ok) throw new Error(Object.values(data.errors||{}).flat()[0]||data.message||'No se pudo generar el pedido.');
            cart=[];
            persist();
            if (whatsappWindow) {
                whatsappWindow.opener = null;
                whatsappWindow.location.replace(data.whatsapp_url);
            }
            window.location.replace(data.tracking_url);
        } catch(error) {
            whatsappWindow?.close();
            showError(error.message);
            setSubmitting(false);
        }
    });

    renderCart(); toggleDeliveryFields(); toggleCashField();
})();
