(() => {
    document.querySelectorAll('[data-menu-banner-carousel]').forEach((carousel) => {
        const slides = [...carousel.querySelectorAll('[data-menu-banner-slide]')];
        const dots = [...carousel.querySelectorAll('[data-banner-dot]')];
        const previous = carousel.querySelector('[data-banner-previous]');
        const next = carousel.querySelector('[data-banner-next]');
        const pause = carousel.querySelector('[data-banner-pause]');
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const interval = Math.max(3000, Number(carousel.dataset.interval) || 5000);
        const canAutoplay = carousel.dataset.autoplay === 'true' && !prefersReducedMotion;
        let activeIndex = 0;
        let paused = false;
        let timer = null;

        const stop = () => {
            window.clearTimeout(timer);
            timer = null;
        };

        const schedule = () => {
            stop();
            if (canAutoplay && !paused && !document.hidden) {
                timer = window.setTimeout(() => show(activeIndex + 1), interval);
            }
        };

        const show = (requestedIndex) => {
            activeIndex = (requestedIndex + slides.length) % slides.length;
            slides.forEach((slide, index) => {
                const isActive = index === activeIndex;
                slide.classList.toggle('is-active', isActive);
                slide.setAttribute('aria-hidden', String(!isActive));
            });
            dots.forEach((dot, index) => {
                const isActive = index === activeIndex;
                dot.classList.toggle('is-active', isActive);
                dot.setAttribute('aria-selected', String(isActive));
            });
            schedule();
        };

        previous?.addEventListener('click', () => show(activeIndex - 1));
        next?.addEventListener('click', () => show(activeIndex + 1));
        dots.forEach((dot, index) => dot.addEventListener('click', () => show(index)));
        pause?.addEventListener('click', () => {
            paused = !paused;
            pause.setAttribute('aria-pressed', String(paused));
            pause.setAttribute('aria-label', paused ? 'Reanudar carrusel' : 'Pausar carrusel');
            pause.querySelector('i')?.classList.toggle('bx-pause', !paused);
            pause.querySelector('i')?.classList.toggle('bx-play', paused);
            schedule();
        });
        carousel.addEventListener('mouseenter', stop);
        carousel.addEventListener('mouseleave', schedule);
        carousel.addEventListener('focusin', stop);
        carousel.addEventListener('focusout', schedule);
        document.addEventListener('visibilitychange', schedule);
        schedule();
    });

    document.querySelectorAll('[data-featured-carousel]').forEach((carousel) => {
        const rail = carousel.querySelector('.featured-menu__rail');
        const items = [...carousel.querySelectorAll('[data-featured-item]')];
        const previous = carousel.querySelector('[data-featured-previous]');
        const next = carousel.querySelector('[data-featured-next]');
        const pause = carousel.querySelector('[data-featured-pause]');
        const status = carousel.querySelector('[data-featured-status]');
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const interval = Math.max(3000, Number(carousel.dataset.interval) || 5000);
        const canAutoplay = carousel.dataset.autoplay === 'true' && !prefersReducedMotion && items.length > 1;
        let activeIndex = 0;
        let paused = false;
        let timer = null;
        let scrollFrame = null;

        if (!rail || items.length < 2) return;

        const stop = () => {
            window.clearTimeout(timer);
            timer = null;
        };

        const updateStatus = () => {
            if (status) status.textContent = `${activeIndex + 1} / ${items.length}`;
        };

        const schedule = () => {
            stop();
            if (canAutoplay && !paused && !document.hidden) {
                timer = window.setTimeout(() => goTo(activeIndex + 1), interval);
            }
        };

        const goTo = (requestedIndex) => {
            activeIndex = (requestedIndex + items.length) % items.length;
            rail.scrollTo({
                left: items[activeIndex].offsetLeft,
                behavior: prefersReducedMotion ? 'auto' : 'smooth',
            });
            updateStatus();
            schedule();
        };

        const syncFromScroll = () => {
            if (scrollFrame !== null) return;
            scrollFrame = window.requestAnimationFrame(() => {
                scrollFrame = null;
                const center = rail.scrollLeft + (rail.clientWidth / 2);
                activeIndex = items.reduce((closest, item, index) => {
                    const itemCenter = item.offsetLeft + (item.offsetWidth / 2);
                    const closestCenter = items[closest].offsetLeft + (items[closest].offsetWidth / 2);
                    return Math.abs(itemCenter - center) < Math.abs(closestCenter - center) ? index : closest;
                }, 0);
                updateStatus();
            });
        };

        previous?.addEventListener('click', () => goTo(activeIndex - 1));
        next?.addEventListener('click', () => goTo(activeIndex + 1));
        pause?.addEventListener('click', () => {
            paused = !paused;
            pause.setAttribute('aria-pressed', String(paused));
            pause.setAttribute('aria-label', paused ? 'Reanudar carrusel' : 'Pausar carrusel');
            pause.querySelector('i')?.classList.toggle('bx-pause', !paused);
            pause.querySelector('i')?.classList.toggle('bx-play', paused);
            schedule();
        });
        pause?.toggleAttribute('hidden', !canAutoplay);
        rail.addEventListener('scroll', syncFromScroll, { passive: true });
        rail.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                event.preventDefault();
                goTo(activeIndex + (event.key === 'ArrowRight' ? 1 : -1));
            }
        });
        carousel.addEventListener('mouseenter', stop);
        carousel.addEventListener('mouseleave', schedule);
        carousel.addEventListener('focusin', stop);
        carousel.addEventListener('focusout', (event) => {
            if (!carousel.contains(event.relatedTarget)) schedule();
        });
        rail.addEventListener('pointerdown', stop, { passive: true });
        rail.addEventListener('pointerup', schedule, { passive: true });
        document.addEventListener('visibilitychange', schedule);
        updateStatus();
        schedule();
    });

    document.querySelectorAll('[data-quick-access-carousel]').forEach((carousel) => {
        const rail = carousel.querySelector('[data-quick-access-rail]');
        const items = [...carousel.querySelectorAll('[data-quick-access-item]')];
        const dots = [...carousel.querySelectorAll('[data-quick-access-dot]')];
        const previous = carousel.querySelector('[data-quick-access-previous]');
        const next = carousel.querySelector('[data-quick-access-next]');
        const current = carousel.querySelector('[data-quick-access-current]');
        const currentLabel = carousel.querySelector('[data-quick-access-label]');
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let activeIndex = 0;
        let scrollFrame = null;
        let programmaticScroll = false;
        let settleTimer = null;

        if (!rail || items.length === 0) return;

        const itemLabel = (item) => item.querySelector(':scope > span:last-child')?.textContent.trim() || '';

        const renderState = () => {
            items.forEach((item, index) => item.classList.toggle('is-active', index === activeIndex));

            dots.forEach((dot, index) => {
                const isActive = index === activeIndex;
                dot.classList.toggle('is-active', isActive);
                dot.setAttribute('aria-pressed', String(isActive));
            });
            const label = itemLabel(items[activeIndex]);
            if (current) current.textContent = String(activeIndex + 1);
            if (currentLabel) {
                currentLabel.textContent = label;
                currentLabel.setAttribute('aria-label', `${label}, acceso ${activeIndex + 1} de ${items.length}`);
            }
            if (previous) previous.disabled = activeIndex === 0;
            if (next) next.disabled = activeIndex === items.length - 1;
            previous?.setAttribute('aria-label', activeIndex > 0 ? `Ver ${itemLabel(items[activeIndex - 1])}` : 'No hay accesos anteriores');
            next?.setAttribute('aria-label', activeIndex < items.length - 1 ? `Ver ${itemLabel(items[activeIndex + 1])}` : 'No hay más accesos');
        };

        const updateState = () => {
            const hasOverflow = rail.scrollWidth > rail.clientWidth + 2;
            if (hasOverflow) {
                const center = rail.scrollLeft + (rail.clientWidth / 2);
                activeIndex = items.reduce((closest, item, index) => {
                    const itemCenter = item.offsetLeft + (item.offsetWidth / 2);
                    const closestCenter = items[closest].offsetLeft + (items[closest].offsetWidth / 2);
                    return Math.abs(itemCenter - center) < Math.abs(closestCenter - center) ? index : closest;
                }, 0);
            }
            renderState();
        };

        const settleProgrammaticScroll = (delay = 120) => {
            window.clearTimeout(settleTimer);
            settleTimer = window.setTimeout(() => {
                programmaticScroll = false;
                renderState();
            }, delay);
        };

        const goTo = (requestedIndex) => {
            activeIndex = Math.max(0, Math.min(requestedIndex, items.length - 1));
            const maximumLeft = Math.max(0, rail.scrollWidth - rail.clientWidth);
            const targetLeft = items[activeIndex].offsetLeft - ((rail.clientWidth - items[activeIndex].offsetWidth) / 2);
            window.clearTimeout(settleTimer);
            programmaticScroll = true;
            rail.scrollTo({
                left: Math.max(0, Math.min(targetLeft, maximumLeft)),
                behavior: reduceMotion ? 'auto' : 'smooth',
            });
            renderState();
            settleProgrammaticScroll(reduceMotion ? 0 : 500);
        };

        const requestStateUpdate = () => {
            if (programmaticScroll) {
                settleProgrammaticScroll();
                return;
            }
            if (scrollFrame !== null) return;
            scrollFrame = window.requestAnimationFrame(() => {
                scrollFrame = null;
                updateState();
            });
        };

        previous?.addEventListener('click', () => goTo(activeIndex - 1));
        next?.addEventListener('click', () => goTo(activeIndex + 1));
        dots.forEach((dot, index) => dot.addEventListener('click', () => goTo(index)));
        rail.addEventListener('scroll', requestStateUpdate, { passive: true });
        rail.addEventListener('pointerdown', () => {
            window.clearTimeout(settleTimer);
            programmaticScroll = false;
        }, { passive: true });
        rail.addEventListener('wheel', () => {
            window.clearTimeout(settleTimer);
            programmaticScroll = false;
        }, { passive: true });
        rail.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                event.preventDefault();
                goTo(activeIndex + (event.key === 'ArrowRight' ? 1 : -1));
            }
        });
        window.addEventListener('resize', requestStateUpdate, { passive: true });
        updateState();
    });

    const input = document.getElementById('menu-search-input');
    const clear = document.getElementById('menu-search-clear');
    const status = document.getElementById('menu-search-status');
    const empty = document.getElementById('menu-no-results');
    const form = document.getElementById('menu-search-form');
    const cards = [...document.querySelectorAll('.menu-catalog [data-menu-product]')];
    const catalogSections = [...document.querySelectorAll('[data-menu-section]')];
    const sections = [...document.querySelectorAll('[data-menu-section], [data-category-section]')];
    const categoryNav = document.querySelector('.category-nav');
    const categoryRail = document.querySelector('[data-category-rail]');
    const categoryScroll = document.querySelector('[data-category-scroll]');
    const categoryLinks = [...document.querySelectorAll('[data-category-link]')];
    const allCategoriesLink = document.querySelector('[data-category-all]');
    const previousCategories = document.querySelector('[data-category-previous]');
    const nextCategories = document.querySelector('[data-category-next]');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let activeCategoryId = null;
    let categoryFrame = null;

    const updateCategoryControls = () => {
        if (!categoryScroll || !categoryRail) return;

        const hasOverflow = categoryScroll.scrollWidth > categoryScroll.clientWidth + 2;
        const atStart = categoryScroll.scrollLeft <= 2;
        const atEnd = categoryScroll.scrollLeft + categoryScroll.clientWidth >= categoryScroll.scrollWidth - 2;

        categoryRail.classList.toggle('has-overflow', hasOverflow);
        previousCategories?.toggleAttribute('hidden', !hasOverflow);
        nextCategories?.toggleAttribute('hidden', !hasOverflow);
        if (previousCategories) previousCategories.disabled = atStart;
        if (nextCategories) nextCategories.disabled = atEnd;
    };

    const keepCategoryVisible = (link) => {
        if (!categoryScroll || !link) return;

        const targetLeft = link.offsetLeft - ((categoryScroll.clientWidth - link.offsetWidth) / 2);
        const maximumLeft = Math.max(0, categoryScroll.scrollWidth - categoryScroll.clientWidth);
        categoryScroll.scrollTo({
            left: Math.max(0, Math.min(targetLeft, maximumLeft)),
            behavior: prefersReducedMotion ? 'auto' : 'smooth',
        });
    };

    const setActiveCategory = (categoryId, keepVisible = true) => {
        if (activeCategoryId === categoryId) return;
        activeCategoryId = categoryId;

        const activeLink = categoryId
            ? categoryLinks.find((link) => link.dataset.categoryLink === categoryId)
            : allCategoriesLink;

        [...categoryLinks, allCategoriesLink].filter(Boolean).forEach((link) => {
            const isActive = link === activeLink;
            link.classList.toggle('is-active', isActive);
            link.toggleAttribute('aria-current', isActive);
            if (isActive) link.setAttribute('aria-current', 'location');
        });

        if (keepVisible) keepCategoryVisible(activeLink);
    };

    const syncActiveCategory = () => {
        if (!categoryNav || sections.length === 0) return;

        const marker = categoryNav.getBoundingClientRect().bottom + 24;
        let currentCategory = null;

        sections.forEach((section) => {
            if (!section.hidden && section.getBoundingClientRect().top <= marker) {
                currentCategory = section.id;
            }
        });

        setActiveCategory(currentCategory);
    };

    const requestCategorySync = () => {
        if (categoryFrame !== null) return;
        categoryFrame = window.requestAnimationFrame(() => {
            categoryFrame = null;
            syncActiveCategory();
            updateCategoryControls();
        });
    };

    const moveCategories = (direction) => {
        if (!categoryScroll) return;
        categoryScroll.scrollBy({
            left: direction * Math.max(240, categoryScroll.clientWidth * 0.72),
            behavior: prefersReducedMotion ? 'auto' : 'smooth',
        });
    };

    previousCategories?.addEventListener('click', () => moveCategories(-1));
    nextCategories?.addEventListener('click', () => moveCategories(1));
    categoryScroll?.addEventListener('scroll', requestCategorySync, { passive: true });
    window.addEventListener('scroll', requestCategorySync, { passive: true });
    window.addEventListener('resize', requestCategorySync);
    allCategoriesLink?.addEventListener('click', () => setActiveCategory(null));
    categoryLinks.forEach((link) => {
        link.addEventListener('click', () => setActiveCategory(link.dataset.categoryLink));
    });

    updateCategoryControls();
    syncActiveCategory();

    if (input) {
        const normalizeSearch = (value) => value
            .toLocaleLowerCase('es')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');

        const filterMenu = () => {
            const query = normalizeSearch(input.value.trim());
            const terms = query.split(/\s+/).filter(Boolean);
            let visible = 0;

            cards.forEach((card) => {
                const searchableText = normalizeSearch(card.dataset.search || '');
                const matches = terms.length === 0 || terms.every((term) => searchableText.includes(term));
                card.hidden = !matches;
                if (matches) visible += 1;
            });

            catalogSections.forEach((section) => {
                section.hidden = ![...section.querySelectorAll('[data-menu-product]')].some((card) => !card.hidden);
            });

            clear.hidden = !query;
            empty.hidden = visible > 0;
            status.textContent = query ? `${visible} ${visible === 1 ? 'resultado' : 'resultados'} para “${input.value.trim()}”` : '';
            requestCategorySync();
        };

        input.addEventListener('input', filterMenu);
        form?.addEventListener('submit', (event) => {
            event.preventDefault();
            filterMenu();
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            document.getElementById('catalog-title')?.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
        });
        clear.addEventListener('click', () => {
            input.value = '';
            filterMenu();
            input.focus();
        });
    }

    document.querySelectorAll('[data-promotion-carousel]').forEach((carousel) => {
        const rail = carousel.querySelector('[data-promotion-rail]');
        const dots = [...carousel.querySelectorAll('[data-promotion-dot]')];
        if (!rail) return;

        const slides = [...rail.children];
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const interval = Math.max(3000, Number(carousel.dataset.autoplayInterval) || 4500);
        let activeIndex = 0;
        let autoplayTimer = null;

        const updateIndicators = () => {
            const railCenter = rail.scrollLeft + (rail.clientWidth / 2);
            activeIndex = slides.reduce((closest, item, index, items) => {
                const itemCenter = item.offsetLeft + (item.offsetWidth / 2);
                const closestCenter = items[closest].offsetLeft + (items[closest].offsetWidth / 2);
                return Math.abs(itemCenter - railCenter) < Math.abs(closestCenter - railCenter) ? index : closest;
            }, 0);
            dots.forEach((dot, index) => dot.classList.toggle('is-active', index === activeIndex));
        };

        const showSlide = (index) => {
            const slide = slides[index];
            if (!slide) return;
            rail.scrollTo({ left: slide.offsetLeft, behavior: reduceMotion ? 'auto' : 'smooth' });
        };
        const stopAutoplay = () => {
            if (autoplayTimer) window.clearInterval(autoplayTimer);
            autoplayTimer = null;
        };
        const startAutoplay = () => {
            stopAutoplay();
            if (reduceMotion || slides.length < 2 || document.hidden) return;
            autoplayTimer = window.setInterval(() => showSlide((activeIndex + 1) % slides.length), interval);
        };

        rail.addEventListener('scroll', () => window.requestAnimationFrame(updateIndicators), { passive: true });
        carousel.addEventListener('mouseenter', stopAutoplay);
        carousel.addEventListener('mouseleave', startAutoplay);
        rail.addEventListener('pointerdown', stopAutoplay);
        rail.addEventListener('pointerup', startAutoplay);
        rail.addEventListener('pointercancel', startAutoplay);
        carousel.addEventListener('focusin', stopAutoplay);
        carousel.addEventListener('focusout', startAutoplay);
        document.addEventListener('visibilitychange', startAutoplay);
        window.addEventListener('resize', updateIndicators, { passive: true });
        updateIndicators();
        startAutoplay();
    });

    const promotionModal = document.getElementById('promotion-detail-modal');
    if (promotionModal) {
        const image = promotionModal.querySelector('[data-promotion-modal-image]');
        const placeholder = promotionModal.querySelector('[data-promotion-modal-placeholder]');
        const badge = promotionModal.querySelector('[data-promotion-modal-badge]');
        const name = promotionModal.querySelector('[data-promotion-modal-name]');
        const price = promotionModal.querySelector('[data-promotion-modal-price]');
        const summary = promotionModal.querySelector('[data-promotion-modal-summary]');
        const description = promotionModal.querySelector('[data-promotion-modal-description]');
        const days = promotionModal.querySelector('[data-promotion-modal-days]');
        const validity = promotionModal.querySelector('[data-promotion-modal-validity]');
        const fulfillment = promotionModal.querySelector('[data-promotion-modal-fulfillment]');
        const terms = promotionModal.querySelector('[data-promotion-modal-terms]');
        const termsWrap = promotionModal.querySelector('[data-promotion-modal-terms-wrap]');
        const groups = promotionModal.querySelector('[data-promotion-modal-groups]');
        let lastTrigger = null;

        const element = (tag, className, text) => {
            const node = document.createElement(tag);
            if (className) node.className = className;
            if (text) node.textContent = text;
            return node;
        };

        document.querySelectorAll('[data-promotion-detail]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                let promotion;
                try {
                    promotion = JSON.parse(trigger.dataset.promotionDetail);
                } catch {
                    return;
                }
                lastTrigger = trigger;
                name.textContent = promotion.name;
                price.textContent = promotion.price;
                summary.textContent = promotion.summary;
                description.textContent = promotion.description || '';
                description.hidden = !promotion.description;
                days.textContent = promotion.days;
                validity.textContent = promotion.validity;
                fulfillment.textContent = promotion.fulfillmentSummary || (promotion.fulfillment || []).join(', ');
                terms.textContent = promotion.terms || '';
                termsWrap.hidden = !promotion.terms;
                badge.textContent = promotion.badge;
                image.hidden = !promotion.image;
                placeholder.hidden = Boolean(promotion.image);
                if (promotion.image) {
                    image.src = promotion.image;
                    image.alt = promotion.name;
                } else {
                    image.removeAttribute('src');
                    image.alt = '';
                }

                groups.replaceChildren();
                promotion.groups.forEach((group) => {
                    const section = element('section', 'product-modal__section promotion-detail-group');
                    const heading = element('div');
                    const icon = element('i', 'bx bx-list-check');
                    icon.setAttribute('aria-hidden', 'true');
                    const copy = element('span');
                    copy.append(element('h3', '', group.name), element('p', '', group.rule));
                    heading.append(icon, copy);
                    const products = element('ul', 'promotion-detail-products');
                    group.products.forEach((product) => {
                        const item = element('li');
                        const media = element('span', 'promotion-detail-products__media');
                        if (product.image) {
                            const productImage = element('img');
                            productImage.src = product.image;
                            productImage.alt = product.name;
                            productImage.width = 52;
                            productImage.height = 52;
                            productImage.loading = 'lazy';
                            media.append(productImage);
                        } else {
                            const productIcon = element('i', 'bx bx-dish');
                            productIcon.setAttribute('aria-hidden', 'true');
                            media.append(productIcon);
                        }
                        const productCopy = element('span');
                        productCopy.append(element('strong', '', product.name));
                        if (product.description) productCopy.append(element('small', '', product.description));
                        item.append(media, productCopy);
                        products.append(item);
                    });
                    section.append(heading, products);
                    groups.append(section);
                });

                document.body.classList.add('has-product-modal');
                promotionModal.showModal();
            });
        });
        promotionModal.querySelectorAll('[data-promotion-modal-close]').forEach((button) => button.addEventListener('click', () => promotionModal.close()));
        promotionModal.addEventListener('click', (event) => { if (event.target === promotionModal) promotionModal.close(); });
        promotionModal.addEventListener('close', () => {
            document.body.classList.remove('has-product-modal');
            lastTrigger?.focus();
        });
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
