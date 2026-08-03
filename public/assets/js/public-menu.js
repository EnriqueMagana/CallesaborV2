(() => {
    const input = document.getElementById('menu-search-input');
    const clear = document.getElementById('menu-search-clear');
    const status = document.getElementById('menu-search-status');
    const empty = document.getElementById('menu-no-results');
    const cards = [...document.querySelectorAll('.menu-catalog [data-menu-product]')];
    const sections = [...document.querySelectorAll('[data-menu-section]')];

    if (input) {
        const filterMenu = () => {
            const query = input.value.trim().toLocaleLowerCase('es');
            let visible = 0;

            cards.forEach((card) => {
                const matches = !query || card.dataset.search.includes(query);
                card.hidden = !matches;
                if (matches) visible += 1;
            });

            sections.forEach((section) => {
                section.hidden = ![...section.querySelectorAll('[data-menu-product]')].some((card) => !card.hidden);
            });

            clear.hidden = !query;
            empty.hidden = visible > 0;
            status.textContent = query ? `${visible} ${visible === 1 ? 'resultado' : 'resultados'} para “${input.value.trim()}”` : '';
        };

        input.addEventListener('input', filterMenu);
        clear.addEventListener('click', () => {
            input.value = '';
            filterMenu();
            input.focus();
        });
    }

    if ('IntersectionObserver' in window) {
        const links = [...document.querySelectorAll('[data-category-link]')];
        const observer = new IntersectionObserver((entries) => {
            const current = entries.find((entry) => entry.isIntersecting);
            if (!current) return;
            links.forEach((link) => link.classList.toggle('is-active', link.dataset.categoryLink === current.target.id));
        }, { rootMargin: '-35% 0px -55% 0px', threshold: 0 });

        sections.forEach((section) => observer.observe(section));
    }

    const modal = document.getElementById('product-detail-modal');
    if (modal) {
        const image = modal.querySelector('[data-modal-image]');
        const placeholder = modal.querySelector('[data-modal-placeholder]');
        const name = modal.querySelector('[data-modal-name]');
        const price = modal.querySelector('[data-modal-price]');
        const description = modal.querySelector('[data-modal-description]');
        const category = modal.querySelector('[data-modal-category]');
        const limits = modal.querySelector('[data-modal-limits]');
        const ingredientsSection = modal.querySelector('[data-modal-ingredients-section]');
        const ingredientsHelp = modal.querySelector('[data-modal-ingredients-help]');
        const ingredientsList = modal.querySelector('[data-modal-ingredients]');
        const groups = modal.querySelector('[data-modal-groups]');
        let lastTrigger = null;

        const createElement = (tag, className, text) => {
            const element = document.createElement(tag);
            if (className) element.className = className;
            if (text) element.textContent = text;
            return element;
        };

        const addLimit = (icon, label, value) => {
            const item = createElement('div', 'product-modal__limit');
            const iconElement = createElement('i', `bx ${icon}`);
            iconElement.setAttribute('aria-hidden', 'true');
            const copy = createElement('span');
            copy.append(createElement('small', '', label), createElement('strong', '', value));
            item.append(iconElement, copy);
            limits.append(item);
        };

        const openProduct = (trigger) => {
            let product;
            try {
                product = JSON.parse(trigger.dataset.productDetail);
            } catch {
                return;
            }

            lastTrigger = trigger;
            name.textContent = product.name;
            price.textContent = product.price;
            description.textContent = product.description;
            category.textContent = product.category;
            image.hidden = !product.image;
            placeholder.hidden = Boolean(product.image);
            if (product.image) {
                image.src = product.image;
                image.alt = product.name;
            } else {
                image.removeAttribute('src');
                image.alt = '';
            }

            limits.replaceChildren();
            if (product.customizable) addLimit('bx-slider-alt', 'Preparación', 'Personalizable');
            if (product.maxIngredients) {
                const range = product.minIngredients > 0
                    ? `${product.minIngredients} a ${product.maxIngredients}`
                    : `Hasta ${product.maxIngredients}`;
                addLimit('bx-leaf', 'Ingredientes', range);
            }
            if (product.maxAddons) addLimit('bx-plus-circle', 'Complementos', `Hasta ${product.maxAddons}`);

            ingredientsList.replaceChildren();
            ingredientsSection.hidden = product.ingredients.length === 0;
            if (product.ingredients.length) {
                const minimum = product.minIngredients || 0;
                const maximum = product.maxIngredients || product.ingredients.length;
                ingredientsHelp.textContent = minimum > 0
                    ? `La preparación admite de ${minimum} a ${maximum} ingredientes.`
                    : `La preparación admite hasta ${maximum} ingredientes.`;
                product.ingredients.forEach((ingredient) => {
                    const item = createElement('li');
                    const media = createElement('span', 'product-modal__ingredient-media');
                    if (ingredient.image) {
                        const ingredientImage = createElement('img');
                        ingredientImage.src = ingredient.image;
                        ingredientImage.alt = ingredient.name;
                        ingredientImage.width = 48;
                        ingredientImage.height = 48;
                        ingredientImage.loading = 'lazy';
                        media.append(ingredientImage);
                    } else {
                        const ingredientIcon = createElement('i', 'bx bx-leaf');
                        ingredientIcon.setAttribute('aria-hidden', 'true');
                        media.append(ingredientIcon);
                    }
                    const copy = createElement('span');
                    copy.append(
                        createElement('strong', '', ingredient.name),
                        ingredient.description ? createElement('small', '', ingredient.description) : document.createTextNode(''),
                    );
                    item.append(media, copy);
                    if (ingredient.extraPrice) item.append(createElement('b', '', ingredient.extraPrice));
                    ingredientsList.append(item);
                });
            }

            groups.replaceChildren();
            product.addonGroups.forEach((group) => {
                const section = createElement('section', 'product-modal__section product-modal__group');
                const heading = createElement('div');
                const icon = createElement('i', 'bx bx-list-check');
                icon.setAttribute('aria-hidden', 'true');
                const headingCopy = createElement('span');
                const rule = group.required
                    ? `Requerido · elige de ${group.minimum} a ${group.maximum}`
                    : `Opcional · hasta ${group.maximum}`;
                headingCopy.append(createElement('h3', '', group.name), createElement('p', '', group.description || rule));
                if (group.description) headingCopy.append(createElement('small', '', rule));
                heading.append(icon, headingCopy);

                const options = createElement('ul');
                group.options.forEach((option) => {
                    const item = createElement('li');
                    const marker = createElement('span', 'product-modal__option-marker');
                    marker.setAttribute('aria-hidden', 'true');
                    const copy = createElement('span');
                    copy.append(
                        createElement('strong', '', option.name),
                        option.description ? createElement('small', '', option.description) : document.createTextNode(''),
                    );
                    item.append(marker, copy, createElement('b', '', option.extraPrice));
                    options.append(item);
                });
                section.append(heading, options);
                groups.append(section);
            });

            document.body.classList.add('has-product-modal');
            modal.showModal();
        };

        document.querySelectorAll('[data-product-detail]').forEach((trigger) => {
            trigger.addEventListener('click', () => openProduct(trigger));
        });

        modal.querySelectorAll('[data-modal-close]').forEach((button) => {
            button.addEventListener('click', () => modal.close());
        });
        modal.addEventListener('click', (event) => {
            if (event.target === modal) modal.close();
        });
        modal.addEventListener('close', () => {
            document.body.classList.remove('has-product-modal');
            lastTrigger?.focus();
        });
    }
})();
