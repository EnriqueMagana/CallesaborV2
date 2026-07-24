@php($performance = $dashboard['team_performance'])

<section class="dashboard-team dashboard-panel" aria-labelledby="dashboard-team-title">
    <div class="dashboard-panel__header">
        <div>
            <span class="dashboard-panel__eyebrow">Pulso del equipo · Hoy</span>
            <h2 id="dashboard-team-title">Quién está moviendo la operación</h2>
            <p>Pedidos atendidos y productos con mayor salida durante el día.</p>
        </div>
        <span class="dashboard-chart-chip">Actualizado {{ now()->format('H:i') }}</span>
    </div>

    <div class="dashboard-team__summary" aria-label="Resumen operativo de hoy">
        <div><span>Pedidos</span><strong>{{ $performance['summary']['orders'] }}</strong></div>
        <div><span>Completados</span><strong>{{ $performance['summary']['completed'] }}</strong></div>
        <div><span>Hora más activa</span><strong>{{ $performance['summary']['peak_hour'] }}</strong></div>
        @if($dashboard['financial_access'])
            <div><span>Ticket promedio</span><strong>${{ number_format($performance['summary']['average_ticket'] ?? 0, 2) }}</strong></div>
        @endif
    </div>

    <div class="dashboard-team__grid">
        <div class="dashboard-ranking">
            <div class="dashboard-ranking__heading">
                <span><i class="bx bx-trophy" aria-hidden="true"></i> Meseros con más pedidos</span>
                <small>Top {{ $performance['leaders']->count() }}</small>
            </div>
            @forelse($performance['leaders'] as $leader)
                <div class="dashboard-ranking__row {{ $leader['rank'] === 1 ? 'is-leader' : '' }}">
                    <span class="dashboard-ranking__position">{{ $leader['rank'] }}</span>
                    <span class="dashboard-ranking__person">
                        <strong>{{ $leader['name'] }}</strong>
                        <small>{{ $leader['orders'] }} {{ $leader['orders'] === 1 ? 'pedido' : 'pedidos' }}</small>
                    </span>
                    @if($dashboard['financial_access'])
                        <strong class="dashboard-ranking__value">${{ number_format($leader['sales'], 2) }}</strong>
                    @else
                        <strong class="dashboard-ranking__value">{{ $leader['orders'] }}</strong>
                    @endif
                </div>
            @empty
                <div class="dashboard-empty dashboard-empty--compact">
                    <i class="bx bx-user-voice" aria-hidden="true"></i>
                    <strong>Aún no hay actividad</strong>
                    <span>El ranking aparecerá con el primer pedido del día.</span>
                </div>
            @endforelse
        </div>

        <div class="dashboard-ranking">
            <div class="dashboard-ranking__heading">
                <span><i class="bx bx-dish" aria-hidden="true"></i> Productos más pedidos</span>
                <small>Unidades</small>
            </div>
            @forelse($performance['top_products'] as $product)
                <div class="dashboard-product-row">
                    <span><strong>{{ $product['name'] }}</strong><small>Preferencia del día</small></span>
                    <strong>{{ $product['units'] }}</strong>
                </div>
            @empty
                <div class="dashboard-empty dashboard-empty--compact">
                    <i class="bx bx-food-menu" aria-hidden="true"></i>
                    <strong>Sin productos registrados</strong>
                    <span>La demanda del día aparecerá aquí.</span>
                </div>
            @endforelse
        </div>
    </div>
</section>
