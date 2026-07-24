<nav class="dashboard-periods" aria-label="Periodo de indicadores">
    <span>Mostrar</span>
    @foreach(['today' => 'Hoy', '7' => '7 días', '30' => '30 días'] as $value => $label)
        <button
            type="button"
            class="{{ $period === $value ? 'is-active' : '' }}"
            wire:click="setPeriod('{{ $value }}')"
            aria-pressed="{{ $period === $value ? 'true' : 'false' }}"
        >{{ $label }}</button>
    @endforeach
    <small>{{ $dashboard['period_label'] }}</small>
</nav>
