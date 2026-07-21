@php
    $user = auth()->user();
    $avatar = $user?->avatar ? Storage::url($user->avatar) : null;
    $initials = collect(explode(' ', trim($user?->name ?? 'U')))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->join('');
@endphp

<div class="dashboard-page" data-dashboard-root wire:loading.class="is-loading">
    <header class="dashboard-welcome">
        <div class="dashboard-identity">
            <a href="{{ route('profile') }}" class="dashboard-avatar" aria-label="Ver mi perfil" wire:navigate>
                @if($avatar)
                    <img src="{{ $avatar }}" alt="Foto de perfil de {{ $user->name }}">
                @else
                    <span aria-hidden="true">{{ $initials }}</span>
                @endif
                <i class="bx bx-check" aria-hidden="true"></i>
            </a>
            <div>
                <div class="dashboard-context">
                    <span><i class="bx {{ $dashboard['profile']['icon'] }}" aria-hidden="true"></i>{{ $dashboard['profile']['label'] }}</span>
                    <span>{{ now()->translatedFormat('l d \d\e F') }}</span>
                </div>
                <h1>Hola, {{ str($user->name)->before(' ') }}</h1>
                <p><strong>{{ $dashboard['profile']['title'] }}.</strong> {{ $dashboard['profile']['subtitle'] }}</p>
            </div>
        </div>

        <div class="dashboard-welcome__aside">
            <span class="dashboard-role"><i class="bx bx-id-card" aria-hidden="true"></i>{{ $dashboard['role_label'] }}</span>
            <button type="button" class="dashboard-refresh" wire:click="refreshDashboard" wire:loading.attr="disabled" wire:target="refreshDashboard" aria-label="Actualizar dashboard">
                <i class="bx bx-refresh" aria-hidden="true"></i><span>Actualizar</span>
            </button>
        </div>
    </header>

    <nav class="dashboard-periods" aria-label="Periodo de indicadores">
        <span>Mostrar</span>
        @foreach(['today' => 'Hoy', '7' => '7 días', '30' => '30 días'] as $value => $label)
            <button type="button" class="{{ $period === $value ? 'is-active' : '' }}" wire:click="setPeriod('{{ $value }}')" aria-pressed="{{ $period === $value ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
        <small>{{ $dashboard['period_label'] }}</small>
    </nav>

    <section class="dashboard-stats" aria-label="Indicadores principales">
        @foreach($dashboard['kpis'] as $stat)
            <x-dashboard.stat-card :stat="$stat" />
        @endforeach
    </section>

    <div class="dashboard-main-grid">
        <x-dashboard.chart-card id="dashboard-trend" label="Rendimiento" title="Actividad del periodo" description="Evolución diaria con información real del sistema.">
            <x-slot:actions><span class="dashboard-chart-chip">{{ $dashboard['period_label'] }}</span></x-slot:actions>
            <x-slot:fallback>
                <details>
                    <summary>Ver datos de la gráfica</summary>
                    <ul>
                        @foreach($dashboard['chart_data']['trend']['labels'] as $index => $label)
                            <li><span>{{ $label }}</span><strong>{{ $dashboard['chart_data']['trend']['money'] ? '$'.number_format($dashboard['chart_data']['trend']['values'][$index], 2) : $dashboard['chart_data']['trend']['values'][$index] }}</strong></li>
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

    <div class="dashboard-secondary-grid">
        <section class="dashboard-panel" aria-labelledby="recent-orders-title">
            <div class="dashboard-panel__header">
                <div><span class="dashboard-panel__eyebrow">Actividad reciente</span><h2 id="recent-orders-title">Últimos pedidos</h2><p>Información visible según tu función.</p></div>
                <a href="{{ route('app.ordenes') }}" class="dashboard-text-link" wire:navigate>Ver todos <i class="bx bx-right-arrow-alt" aria-hidden="true"></i></a>
            </div>

            @if($dashboard['recent_orders']->isEmpty())
                <div class="dashboard-empty"><i class="bx bx-receipt" aria-hidden="true"></i><strong>Aún no hay pedidos</strong><span>La actividad del periodo aparecerá aquí.</span></div>
            @else
                <div class="dashboard-order-list">
                    @foreach($dashboard['recent_orders'] as $order)
                        <a href="{{ route('app.ordenes.show', $order) }}" class="dashboard-order" wire:navigate>
                            <span class="dashboard-order__icon"><i class="bx {{ $order->type_icon }}" aria-hidden="true"></i></span>
                            <span class="dashboard-order__copy"><strong>#{{ $order->display_folio }} · {{ $order->display_name }}</strong><small>{{ $order->type_label }}{{ $order->mesa ? ' · '.$order->mesa->display_name : '' }} · {{ $order->created_at->diffForHumans() }}</small></span>
                            <span class="dashboard-order__status dashboard-order__status--{{ $order->status_color }}">{{ $order->status_label }}</span>
                            <i class="bx bx-chevron-right dashboard-order__arrow" aria-hidden="true"></i>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <aside class="dashboard-panel dashboard-actions" aria-labelledby="quick-actions-title">
            <div class="dashboard-panel__header"><div><span class="dashboard-panel__eyebrow">Accesos directos</span><h2 id="quick-actions-title">¿Qué necesitas hacer?</h2><p>Acciones habilitadas para tu perfil.</p></div></div>
            <div class="dashboard-action-list">
                @forelse($dashboard['quick_actions'] as $action)
                    <a href="{{ $action['route'] }}" wire:navigate><i class="bx {{ $action['icon'] }}" aria-hidden="true"></i><span><strong>{{ $action['label'] }}</strong><small>{{ $action['description'] }}</small></span><i class="bx bx-chevron-right" aria-hidden="true"></i></a>
                @empty
                    <div class="dashboard-empty dashboard-empty--compact"><i class="bx bx-lock-alt" aria-hidden="true"></i><strong>Sin accesos asignados</strong><span>Solicita los permisos necesarios al owner.</span></div>
                @endforelse
            </div>
            <a href="{{ route('profile') }}" class="dashboard-profile-link" wire:navigate><span class="dashboard-avatar dashboard-avatar--small">@if($avatar)<img src="{{ $avatar }}" alt="">@else<span>{{ $initials }}</span>@endif</span><span><strong>Mi perfil</strong><small>Cuenta, foto y seguridad</small></span><i class="bx bx-cog" aria-hidden="true"></i></a>
        </aside>
    </div>

    <script type="application/json" data-dashboard-data>@json($dashboard['chart_data'])</script>

    <div class="dashboard-loader" wire:loading.flex wire:target="setPeriod,refreshDashboard" aria-live="polite" aria-label="Actualizando dashboard">
        <span></span><span></span><span></span>
    </div>
</div>

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}?v={{ filemtime(public_path('assets/css/dashboard.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/dashboard.js') }}?v={{ filemtime(public_path('assets/js/dashboard.js')) }}" data-navigate-once></script>
@endpush
