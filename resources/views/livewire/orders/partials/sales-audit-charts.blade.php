<section class="sales-audit-charts" aria-label="Gráficas de la auditoría">
    <article class="app-card sales-audit-chart-card sales-audit-chart-card--wide">
        <div class="sales-audit-chart-card__header"><div><span>Rendimiento</span><h2>Ventas por día</h2></div><i class="bx bx-line-chart" aria-hidden="true"></i></div>
        <div class="sales-audit-chart" data-sales-audit-chart="trend" role="img" aria-label="Tendencia diaria de ventas y órdenes"></div>
        <div class="sales-audit-chart-summary">
            @forelse($this->analytics['trend']['labels'] ?? [] as $index => $label)
                <span>
                    {{ $label }}: {{ $this->analytics['trend']['orders'][$index] }} órdenes
                    @if($this->canViewFinancials), &#36;{{ number_format($this->analytics['trend']['sales'][$index], 2) }}@endif
                </span>
            @empty
                <span>Sin ventas contabilizadas para graficar.</span>
            @endforelse
        </div>
    </article>
    <article class="app-card sales-audit-chart-card">
        <div class="sales-audit-chart-card__header"><div><span>Top 7</span><h2>Productos vendidos</h2></div><i class="bx bx-bowl-hot" aria-hidden="true"></i></div>
        <div class="sales-audit-chart" data-sales-audit-chart="products" role="img" aria-label="Productos más vendidos por unidades"></div>
        <ol class="sales-audit-ranking" aria-label="Lista de productos más vendidos">
            @forelse($this->analytics['products']['labels'] ?? [] as $index => $label)
                <li><span><b>{{ $index + 1 }}</b>{{ $label }}</span><strong>{{ $this->analytics['products']['units'][$index] }} uds.</strong></li>
            @empty
                <li class="is-empty">Sin productos vendidos para este filtro.</li>
            @endforelse
        </ol>
    </article>
    <article class="app-card sales-audit-chart-card">
        <div class="sales-audit-chart-card__header"><div><span>Top 5</span><h2>Mejores clientes</h2></div><i class="bx bx-group" aria-hidden="true"></i></div>
        <div class="sales-audit-chart" data-sales-audit-chart="customers" role="img" aria-label="Cinco mejores clientes del periodo"></div>
        <ol class="sales-audit-ranking" aria-label="Lista de mejores clientes">
            @forelse($this->analytics['customers']['labels'] ?? [] as $index => $label)
                <li>
                    <span><b>{{ $index + 1 }}</b>{{ $label }}</span>
                    <strong>
                        @if($this->canViewFinancials)
                            &#36;{{ number_format($this->analytics['customers']['sales'][$index], 2) }}
                        @else
                            {{ $this->analytics['customers']['orders'][$index] }} órdenes
                        @endif
                    </strong>
                </li>
            @empty
                <li class="is-empty">Sin clientes para este filtro.</li>
            @endforelse
        </ol>
    </article>
</section>
