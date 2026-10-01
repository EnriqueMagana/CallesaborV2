<div class="menu-analytics-page" data-menu-analytics-dashboard>
    @php($data = $this->analytics)

    <header class="menu-analytics-hero">
        <div class="menu-analytics-hero__copy">
            <span class="menu-analytics-eyebrow"><i class="bx bx-pulse" aria-hidden="true"></i> Menú digital · Comportamiento</span>
            <h1>Analítica del menú</h1>
            <p>Descubre cuántas personas consultan tu carta y cuáles productos despiertan más interés.</p>
        </div>
        <div class="menu-analytics-hero__actions">
            <a href="{{ route('public.menu') }}" target="_blank" rel="noopener noreferrer"><i class="bx bx-show" aria-hidden="true"></i> Ver menú</a>
            @can('gestionar menu digital')
                <a href="{{ route('app.menu-digital') }}" wire:navigate><i class="bx bx-slider-alt" aria-hidden="true"></i> Configurar</a>
            @endcan
        </div>
    </header>

    <nav class="menu-analytics-periods" aria-label="Periodo de la analítica">
        <span>Periodo</span>
        @foreach ([7 => '7 días', 30 => '30 días', 90 => '90 días'] as $value => $label)
            <button type="button" wire:click="setPeriod({{ $value }})" @class(['is-active' => $days === $value]) aria-pressed="{{ $days === $value ? 'true' : 'false' }}">
                {{ $label }}
            </button>
        @endforeach
        <small>{{ \Carbon\Carbon::parse($data['period']['from'])->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($data['period']['to'])->format('d/m/Y') }}</small>
    </nav>

    <section class="menu-analytics-kpis" aria-label="Resumen del periodo">
        @foreach ([
            ['bx-show', 'Vistas del menú', number_format($data['totals']['views']), 'Cada apertura registrada'],
            ['bx-user', 'Visitantes', number_format($data['totals']['visitors']), 'Dispositivos únicos estimados'],
            ['bx-pointer', 'Clics en productos', number_format($data['totals']['clicks']), 'Detalles de producto abiertos'],
            ['bx-trending-up', 'Interacción', number_format($data['totals']['click_rate'], 1).'%','Clics por cada 100 vistas'],
        ] as $kpi)
            <article>
                <span><i class="bx {{ $kpi[0] }}" aria-hidden="true"></i></span>
                <div><small>{{ $kpi[1] }}</small><strong>{{ $kpi[2] }}</strong><p>{{ $kpi[3] }}</p></div>
            </article>
        @endforeach
    </section>

    <section class="menu-analytics-grid">
        <article class="menu-analytics-card menu-analytics-card--trend">
            <header><div><span>Tendencia diaria</span><h2>Visualizaciones por día</h2><p>Compara aperturas, visitantes únicos y clics.</p></div><i class="bx bx-line-chart" aria-hidden="true"></i></header>
            <p class="menu-analytics-sr" id="menu-trend-summary">En este periodo hubo {{ number_format($data['totals']['views']) }} vistas de {{ number_format($data['totals']['visitors']) }} visitantes y {{ number_format($data['totals']['clicks']) }} clics en productos.</p>
            <div class="menu-analytics-chart" data-menu-analytics-chart="trend" role="img" aria-describedby="menu-trend-summary"></div>
            <details class="menu-analytics-table-fallback">
                <summary>Ver datos diarios</summary>
                <div><table><thead><tr><th>Fecha</th><th>Vistas</th><th>Visitantes</th><th>Clics</th></tr></thead><tbody>
                    @foreach ($data['trend']['labels'] as $index => $label)
                        <tr><th>{{ $label }}</th><td>{{ $data['trend']['views'][$index] }}</td><td>{{ $data['trend']['visitors'][$index] }}</td><td>{{ $data['trend']['clicks'][$index] }}</td></tr>
                    @endforeach
                </tbody></table></div>
            </details>
        </article>

        <article class="menu-analytics-card menu-analytics-card--products">
            <header><div><span>Interés de clientes</span><h2>Productos más vistos</h2><p>Esta gráfica respeta el periodo; “Favoritos de la casa” usa el ranking histórico.</p></div><i class="bx bx-trophy" aria-hidden="true"></i></header>
            <p class="menu-analytics-sr" id="menu-products-summary">Ranking de productos ordenado por aperturas de detalle durante el periodo seleccionado.</p>
            <div class="menu-analytics-chart" data-menu-analytics-chart="products" role="img" aria-describedby="menu-products-summary"></div>
            <ol class="menu-analytics-ranking">
                @forelse ($data['products']['items']->take(5) as $product)
                    <li><b>{{ $loop->iteration }}</b><span><strong>{{ $product->name }}</strong><small>{{ number_format($product->unique_visitors) }} visitantes interesados</small></span><em>{{ number_format($product->clicks_count) }} clics</em></li>
                @empty
                    <li class="is-empty"><i class="bx bx-bar-chart-alt-2" aria-hidden="true"></i><span><strong>Aún no hay clics</strong><small>El ranking se formará automáticamente con las visitas.</small></span></li>
                @endforelse
            </ol>
        </article>
    </section>

    <script type="application/json" data-menu-analytics-data wire:key="menu-analytics-data-{{ $days }}">{!! json_encode([
        'trend' => $data['trend'],
        'products' => ['labels' => $data['products']['labels'], 'clicks' => $data['products']['clicks']],
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</div>
