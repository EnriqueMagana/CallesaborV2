<section class="dashboard-stats" aria-label="Indicadores principales">
    @foreach($dashboard['kpis'] as $stat)
        <x-dashboard.stat-card :stat="$stat" />
    @endforeach
</section>
