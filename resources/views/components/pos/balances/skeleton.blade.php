{{--
    Esqueleto del panel de saldos con la forma real del contenido (resumen,
    filtro y tarjetas) para que no brinque el layout cuando llegan los datos.
--}}
<div {{ $attributes->class('pos-balances-skeleton') }} role="status" aria-live="polite">
    <span class="visually-hidden">Cargando saldos pendientes…</span>
    <div class="pos-balances-skeleton__kpis" aria-hidden="true">
        <span></span><span></span><span></span>
    </div>
    <span class="pos-balances-skeleton__filter" aria-hidden="true"></span>
    @for ($i = 0; $i < 2; $i++)
        <div class="pos-balances-skeleton__card" aria-hidden="true">
            <div class="pos-balances-skeleton__head"><span class="is-mark"></span><span class="is-title"></span><span class="is-chip"></span></div>
            <span class="pos-balances-skeleton__amount"></span>
            <div class="pos-balances-skeleton__actions"><span></span><span></span></div>
        </div>
    @endfor
</div>
