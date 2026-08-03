<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#6956e8">
    <title>Kiosco · {{ $businessSettings?->business_name ?? config('app.name') }}</title>
    <link rel="icon" href="{{ $businessSettings?->logo_path ? Storage::url($businessSettings->logo_path) : asset('assets/img/favicon/favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/boxicons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/kiosk.css') }}?v={{ filemtime(public_path('assets/css/kiosk.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/delivery-tracking.css') }}?v={{ filemtime(public_path('assets/css/delivery-tracking.css')) }}">
    @livewireStyles
</head>
<body class="kiosk-body kiosk-is-booting">
    <div class="kiosk-initial-skeleton" role="status" aria-label="Cargando kiosco">
        <div class="kiosk-initial-skeleton-header">
            <i></i><span></span><b></b>
        </div>
        <div class="kiosk-initial-skeleton-body">
            <section>
                <i></i><b></b><b></b>
                <article><span></span><div><i></i><i></i><b></b></div></article>
            </section>
            <aside>
                <article><i></i><div><b></b><span></span></div></article>
                <article><i></i><div><b></b><span></span></div></article>
            </aside>
        </div>
        <span class="visually-hidden">Preparando productos y opciones del kiosco.</span>
    </div>
    {{ $slot }}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('kioskImageLoader', () => ({
                loading: false,
                init() {
                    const image = this.$refs.image;

                    // Cached images are already complete here, so they never flash a skeleton.
                    if (!image || image.complete) return;

                    this.loading = true;
                    const finish = () => { this.loading = false; };
                    image.addEventListener('load', finish, { once: true });
                    image.addEventListener('error', finish, { once: true });
                },
            }));

            Alpine.data('kioskCustomizer', (config) => ({
                addonQuantities: { ...(config.addonQuantities || {}) },
                ingredients: { ...(config.ingredients || {}) },
                qty: Number(config.qty || 1),
                notes: config.notes || '',
                groups: config.groups || [],
                productAddonMaximum: Number(config.productAddonMaximum || 0),
                ingredientMinimum: Number(config.ingredientMinimum || 0),
                ingredientMaximum: Number(config.ingredientMaximum || 0),
                clientError: '',
                submitting: false,
                amount(map, id) {
                    return Math.max(0, Number(map[id] || 0));
                },
                total(map) {
                    return Object.values(map).reduce(
                        (sum, value) => sum + Math.max(0, Number(value || 0)),
                        0
                    );
                },
                groupTotal(ids) {
                    return ids.reduce(
                        (sum, id) => sum + this.amount(this.addonQuantities, id),
                        0
                    );
                },
                changeAddon(id, delta, groupIds, maximum, lockedRequired) {
                    const current = this.amount(this.addonQuantities, id);
                    const next = Math.max(0, current + delta);
                    if (lockedRequired && next === 0) {
                        this.clientError = 'Esta opción es obligatoria y debe conservar al menos una unidad.';
                        return;
                    }

                    const draft = { ...this.addonQuantities };
                    if (Number(maximum) === 1 && next > 0) {
                        groupIds.forEach((groupId) => delete draft[groupId]);
                        draft[id] = 1;
                    } else if (next === 0) {
                        delete draft[id];
                    } else {
                        draft[id] = next;
                    }

                    const groupTotal = groupIds.reduce(
                        (sum, groupId) => sum + this.amount(draft, groupId),
                        0
                    );
                    if (groupTotal > Number(maximum)) {
                        this.clientError = `Este grupo permite máximo ${maximum} complemento(s).`;
                        return;
                    }
                    if (this.productAddonMaximum > 0 && this.total(draft) > this.productAddonMaximum) {
                        this.clientError = `Este producto permite máximo ${this.productAddonMaximum} complementos en total.`;
                        return;
                    }

                    this.addonQuantities = draft;
                    this.clientError = '';
                },
                changeIngredient(id, delta) {
                    const current = this.amount(this.ingredients, id);
                    const next = Math.max(0, current + delta);
                    const draft = { ...this.ingredients };
                    if (next === 0) delete draft[id];
                    else draft[id] = next;

                    if (this.ingredientMaximum > 0 && this.total(draft) > this.ingredientMaximum) {
                        this.clientError = `Puedes agregar hasta ${this.ingredientMaximum} ingredientes.`;
                        return;
                    }

                    this.ingredients = draft;
                    this.clientError = '';
                },
                isValid() {
                    const groupsValid = this.groups.every((group) => {
                        const count = this.groupTotal(group.ids);
                        return count >= group.minimum && count <= group.maximum;
                    });
                    const ingredientTotal = this.total(this.ingredients);

                    return groupsValid
                        && ingredientTotal >= this.ingredientMinimum
                        && (this.ingredientMaximum === 0 || ingredientTotal <= this.ingredientMaximum);
                },
                submit(wire) {
                    if (!this.isValid() || this.submitting) {
                        this.clientError = 'Completa las opciones obligatorias antes de agregar el producto.';
                        return;
                    }

                    this.submitting = true;
                    this.clientError = '';
                    wire.addCustomizedProduct(this.addonQuantities, this.ingredients, this.qty, this.notes)
                        .finally(() => { this.submitting = false; });
                },
            }));
        });
    </script>
    @livewireScripts
    <script>
        (() => {
            const revealKiosk = () => requestAnimationFrame(() => document.body.classList.remove('kiosk-is-booting'));
            if (document.readyState === 'complete') revealKiosk();
            else window.addEventListener('load', revealKiosk, { once: true });
        })();
    </script>
</body>
</html>
