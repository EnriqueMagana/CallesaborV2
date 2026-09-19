{{--
    Resumen de saldos pendientes y filtro por cobrador.
    El filtro es un grupo de botones con aria-pressed: cada uno dice cuántas
    órdenes muestra, así el número no depende sólo del color.
--}}
@props(['summary', 'filter' => 'all'])

@php
    $segments = [
        'all' => ['Todas', 'bx-list-ul', $summary['count']],
        'register' => ['En caja', 'bx-store-alt', $summary['register']['count']],
        'driver' => ['Repartidor', 'bx-cycling', $summary['driver']['count']],
    ];
@endphp

<section class="pos-balances-summary" aria-label="Resumen de saldos pendientes">
    <div class="pos-balances-kpis">
        <div class="pos-balances-kpi is-total">
            <span>Por cobrar</span>
            <strong>${{ number_format($summary['due'], 2) }}</strong>
            <small>{{ $summary['count'] }} {{ $summary['count'] === 1 ? 'orden' : 'órdenes' }}</small>
        </div>
        <div class="pos-balances-kpi is-register">
            <span><i class="bx bx-store-alt" aria-hidden="true"></i> En caja</span>
            <strong>${{ number_format($summary['register']['due'], 2) }}</strong>
            <small>Lo cobra el cajero</small>
        </div>
        <div class="pos-balances-kpi is-driver">
            <span><i class="bx bx-cycling" aria-hidden="true"></i> Con repartidores</span>
            <strong>${{ number_format($summary['driver']['due'], 2) }}</strong>
            <small>Llega en efectivo</small>
        </div>
    </div>

    <div class="pos-balances-filter" role="group" aria-label="Filtrar por quién cobra">
        @foreach ($segments as $value => [$label, $icon, $count])
            <button type="button" wire:click="setFilter('{{ $value }}')"
                class="{{ $filter === $value ? 'is-active' : '' }}"
                aria-pressed="{{ $filter === $value ? 'true' : 'false' }}"
                aria-label="{{ $label }}: {{ $count }}">
                <i class="bx {{ $icon }}" aria-hidden="true"></i>
                <span>{{ $label }}</span>
                <b>{{ $count }}</b>
            </button>
        @endforeach
    </div>
</section>
