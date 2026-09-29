@props(['summary'])

<section class="pos-balances-summary" aria-label="Resumen de saldos pendientes en caja">
    <div class="pos-balances-kpis is-register-only">
        <div class="pos-balances-kpi is-total">
            <span><i class="bx bx-store-alt" aria-hidden="true"></i> Por cobrar en caja</span>
            <strong>${{ number_format($summary['due'], 2) }}</strong>
            <small>{{ $summary['count'] }} {{ $summary['count'] === 1 ? 'orden' : 'órdenes' }} de ventanilla o recoger</small>
        </div>
    </div>
</section>
