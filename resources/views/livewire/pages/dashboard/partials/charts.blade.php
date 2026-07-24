<div class="dashboard-main-grid">
    <x-dashboard.chart-card id="dashboard-trend" label="Rendimiento" title="Actividad del periodo" description="Evolución diaria con información real del sistema.">
        <x-slot:actions><span class="dashboard-chart-chip">{{ $dashboard['period_label'] }}</span></x-slot:actions>
        <x-slot:fallback>
            <details>
                <summary>Ver datos de la gráfica</summary>
                <ul>
                    @foreach($dashboard['chart_data']['trend']['labels'] as $index => $label)
                        <li>
                            <span>{{ $label }}</span>
                            <strong>{{ $dashboard['chart_data']['trend']['money'] ? '$'.number_format($dashboard['chart_data']['trend']['values'][$index], 2) : $dashboard['chart_data']['trend']['values'][$index] }}</strong>
                        </li>
                    @endforeach
                </ul>
            </details>
        </x-slot:fallback>
    </x-dashboard.chart-card>

    <x-dashboard.chart-card id="dashboard-status" label="Flujo operativo" title="Estado de pedidos" description="Distribución para detectar cuellos de botella.">
        <x-slot:fallback>
            <ul class="dashboard-status-list">
                @foreach($dashboard['chart_data']['status']['labels'] as $index => $label)
                    <li><span>{{ $label }}</span><strong>{{ $dashboard['chart_data']['status']['values'][$index] }}</strong></li>
                @endforeach
            </ul>
        </x-slot:fallback>
    </x-dashboard.chart-card>
</div>
