<div class="dashboard-secondary-grid">
    <section class="dashboard-panel" aria-labelledby="recent-orders-title">
        <div class="dashboard-panel__header">
            <div>
                <span class="dashboard-panel__eyebrow">Actividad reciente</span>
                <h2 id="recent-orders-title">Últimos pedidos</h2>
                <p>Información visible según tu función.</p>
            </div>
            @if($dashboard['can_view_orders'])
                <a href="{{ route('app.ordenes') }}" class="dashboard-text-link" wire:navigate>Ver todos <i class="bx bx-right-arrow-alt" aria-hidden="true"></i></a>
            @endif
        </div>

        @if($dashboard['recent_orders']->isEmpty())
            <div class="dashboard-empty">
                <i class="bx bx-receipt" aria-hidden="true"></i>
                <strong>Aún no hay pedidos</strong>
                <span>La actividad del periodo aparecerá aquí.</span>
            </div>
        @else
            <div class="dashboard-order-list">
                @foreach($dashboard['recent_orders'] as $order)
                    <a href="{{ route('app.ordenes.show', $order) }}" class="dashboard-order" wire:navigate>
                        <span class="dashboard-order__icon"><i class="bx {{ $order->type_icon }}" aria-hidden="true"></i></span>
                        <span class="dashboard-order__copy">
                            <strong>#{{ $order->display_folio }} · {{ $order->display_name }}</strong>
                            <small>{{ $order->type_label }}{{ $order->mesa ? ' · '.$order->mesa->display_name : '' }} · {{ $order->created_at->diffForHumans() }}</small>
                        </span>
                        <span class="dashboard-order__status dashboard-order__status--{{ $order->status_color }}">{{ $order->status_label }}</span>
                        <i class="bx bx-chevron-right dashboard-order__arrow" aria-hidden="true"></i>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <aside class="dashboard-panel dashboard-actions" aria-labelledby="quick-actions-title">
        <div class="dashboard-panel__header">
            <div>
                <span class="dashboard-panel__eyebrow">Accesos directos</span>
                <h2 id="quick-actions-title">¿Qué necesitas hacer?</h2>
                <p>Acciones habilitadas para tu perfil y menú.</p>
            </div>
        </div>
        <div class="dashboard-action-list">
            @forelse($dashboard['quick_actions'] as $action)
                @php($usesStandaloneLayout = rtrim($action['route'], '/') === rtrim(route('app.pos'), '/'))
                <a href="{{ $action['route'] }}" @unless($usesStandaloneLayout) wire:navigate @endunless>
                    <i class="bx {{ $action['icon'] }}" aria-hidden="true"></i>
                    <span><strong>{{ $action['label'] }}</strong><small>{{ $action['description'] }}</small></span>
                    <i class="bx bx-chevron-right" aria-hidden="true"></i>
                </a>
            @empty
                <div class="dashboard-empty dashboard-empty--compact">
                    <i class="bx bx-lock-alt" aria-hidden="true"></i>
                    <strong>Sin accesos asignados</strong>
                    <span>Solicita los permisos necesarios al owner.</span>
                </div>
            @endforelse
        </div>
        <a href="{{ route('profile') }}" class="dashboard-profile-link" wire:navigate>
            <span class="dashboard-avatar dashboard-avatar--small">
                @if($avatar)<img src="{{ $avatar }}" alt="">@else<span>{{ $initials }}</span>@endif
            </span>
            <span><strong>Mi perfil</strong><small>Cuenta, foto y seguridad</small></span>
            <i class="bx bx-cog" aria-hidden="true"></i>
        </a>
    </aside>
</div>
