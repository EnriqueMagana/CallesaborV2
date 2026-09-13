<div class="app-page sales-audit-page sales-audit-record"
    x-on:sales-history-ticket-show.window="window.TicketPreviewModal?.open('salesHistoryTicketModal', { title: 'Reimpresión · {{ $order->display_folio }}', activeTab: 'ticket', frames: { ticket: $event.detail.html || '' } })">
    <header class="app-page-header sales-audit-record__hero">
        <div class="app-page-heading">
            <span class="app-page-icon sales-audit-hero__icon" aria-hidden="true"><i class="bx bx-receipt"></i></span>
            <div>
                <div class="app-eyebrow">Auditoría de venta · Solo lectura</div>
                <h1 class="app-page-title">{{ $order->display_folio }}</h1>
                <p class="app-page-subtitle">Expediente completo de la orden, independiente del estado de la caja.</p>
            </div>
        </div>
        <div class="sales-audit-record__actions">
            <a href="{{ route('app.historial-ventas') }}" class="btn btn-outline-secondary"><i class="bx bx-arrow-back" aria-hidden="true"></i> Volver al historial</a>
            @can('reimprimir tickets')
                <button type="button" wire:click="previewTicket" wire:loading.attr="disabled" wire:target="previewTicket" class="btn btn-primary">
                    <span class="sales-audit-record__button-idle" wire:loading.remove wire:target="previewTicket"><i class="bx bx-printer" aria-hidden="true"></i> Reimprimir ticket</span>
                    <span class="sales-audit-record__button-loading" wire:loading.inline-flex wire:target="previewTicket"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Preparando…</span>
                </button>
            @endcan
        </div>
    </header>

    <div class="sales-audit-record__notice" role="note">
        <i class="bx bx-time-five" aria-hidden="true"></i>
        <span><strong>Horario del panel:</strong> todas las fechas se muestran en {{ \App\Support\BusinessTime::timezone() }}.</span>
    </div>

    <section class="sales-audit-record__summary" aria-label="Resumen de la orden">
        <article><span class="sales-audit-record__summary-icon"><i class="bx {{ $order->type_icon }}" aria-hidden="true"></i></span><div><small>Canal</small><strong>{{ $order->type_label }}</strong><span>{{ $order->source === 'kiosk' ? 'Kiosco' : 'Atención interna' }}</span></div></article>
        <article><span class="sales-audit-record__summary-icon"><i class="bx bx-user" aria-hidden="true"></i></span><div><small>Atendió</small><strong>{{ $order->seller?->name ?? 'Usuario no disponible' }}</strong><span>{{ \App\Support\BusinessTime::format($order->created_at, 'd/m/Y · g:i:s A') }}</span></div></article>
        <article><span class="sales-audit-record__summary-icon"><i class="bx bx-wallet" aria-hidden="true"></i></span><div><small>Caja / turno</small><strong>{{ $order->cashRegister?->name ?? 'Sin caja asociada' }}</strong><span>{{ $order->cashRegister?->is_open ? 'Turno abierto' : 'Turno cerrado' }}</span></div></article>
        <article class="is-{{ $order->status_color }}"><span class="sales-audit-record__summary-icon"><i class="bx {{ $order->status === 'cancelada' ? 'bx-x-circle' : 'bx-check-circle' }}" aria-hidden="true"></i></span><div><small>Estado actual</small><strong>{{ $order->status_label }}</strong><span>{{ \App\Support\BusinessTime::format($order->paid_at ?? $order->accounted_at ?? $order->cancelled_at, 'd/m/Y · g:i:s A', 'Sin cierre') }}</span></div></article>
    </section>

    <div class="sales-audit-record__layout">
        <main class="sales-audit-record__main">
            <section class="app-card sales-audit-record__section" aria-labelledby="audit-items-title">
                <header><span class="sales-audit-record__section-icon" aria-hidden="true"><i class="bx bx-list-ul"></i></span><div><span class="sales-audit-section-label">Contenido original y vigente</span><h2 id="audit-items-title">Partidas del pedido</h2></div></header>
                <div class="sales-audit-record__items">
                    @forelse($order->items as $item)
                        <article class="{{ $item->is_cancelled ? 'is-cancelled' : '' }}">
                            <span class="sales-audit-record__item-qty">{{ $item->quantity }}×</span>
                            <div class="sales-audit-record__item-copy">
                                <div class="sales-audit-record__item-name"><strong>{{ $item->product_name }}</strong>@if($item->is_cancelled)<span>Cancelada</span>@endif</div>
                                <small>${{ number_format($item->product_price, 2) }} c/u{{ $item->notes ? ' · '.$item->notes : '' }}</small>
                                @foreach($item->addons as $addon)<small>+ {{ $addon->addon_name }}{{ $addon->quantity > 1 ? ' ×'.$addon->quantity : '' }}</small>@endforeach
                                @foreach($item->ingredients as $ingredient)<small>• {{ $ingredient->ingredient_name }}{{ $ingredient->quantity > 1 ? ' ×'.$ingredient->quantity : '' }}</small>@endforeach
                                @if((float) $item->promotion_discount > 0)<span class="sales-audit-record__adjustment"><i class="bx bx-purchase-tag" aria-hidden="true"></i> Promoción: -${{ number_format($item->promotion_discount, 2) }} · {{ data_get($item->promotion_rule_snapshot, 'label', $item->promotion?->name ?? 'Promoción aplicada') }}</span>@endif
                                @if((float) $item->discount_amount > 0)<span class="sales-audit-record__adjustment"><i class="bx bx-purchase-tag-alt" aria-hidden="true"></i> Descuento: -${{ number_format($item->discount_amount, 2) }} · {{ data_get($item->discount_snapshot, 'name', $item->discount?->name ?? 'Descuento aplicado') }}</span>@endif
                                @if($item->is_cancelled)<span class="sales-audit-record__cancel-meta"><i class="bx bx-user-x" aria-hidden="true"></i> {{ $item->cancelledBy?->name ?? 'Usuario no disponible' }} · {{ \App\Support\BusinessTime::format($item->cancelled_at, 'd/m/Y g:i:s A') }}</span>@endif
                            </div>
                            @if(auth()->user()?->can('ver reportes financieros'))<strong class="sales-audit-record__item-total">${{ number_format((float) $item->subtotal - (float) $item->promotion_discount - (float) $item->discount_amount, 2) }}</strong>@endif
                        </article>
                    @empty
                        <div class="app-empty-state"><span class="app-empty-icon"><i class="bx bx-food-menu" aria-hidden="true"></i></span><h3>Sin partidas registradas</h3></div>
                    @endforelse
                </div>
            </section>

            <section class="app-card sales-audit-record__section" aria-labelledby="audit-timeline-title">
                <header><span class="sales-audit-record__section-icon" aria-hidden="true"><i class="bx bx-history"></i></span><div><span class="sales-audit-section-label">Trazabilidad</span><h2 id="audit-timeline-title">Movimientos de la orden</h2></div></header>
                <ol class="sales-audit-timeline">
                    @forelse($this->timeline as $event)
                        <li class="is-{{ $event['tone'] }}">
                            <span class="sales-audit-timeline__icon" aria-hidden="true"><i class="bx {{ $event['icon'] }}"></i></span>
                            <div class="sales-audit-timeline__content">
                                <div><strong>{{ $event['title'] }}</strong><time datetime="{{ $event['at']->toIso8601String() }}">{{ \App\Support\BusinessTime::format($event['at'], 'd/m/Y · g:i:s A') }}</time></div>
                                <p>{{ $event['detail'] }}</p>
                                @if($event['actor'])<small>Responsable: {{ $event['actor'] }}</small>@endif
                                @if(!empty($event['changes']))
                                    <dl class="sales-audit-timeline__changes">
                                        @foreach($event['changes'] as $change)<div><dt>{{ $change['field'] }}</dt><dd><span>{{ $change['before'] }}</span><i class="bx bx-right-arrow-alt" aria-hidden="true"></i><strong>{{ $change['after'] }}</strong></dd></div>@endforeach
                                    </dl>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li><div class="sales-audit-timeline__content"><strong>Sin movimientos disponibles</strong></div></li>
                    @endforelse
                </ol>
            </section>
        </main>

        <aside class="sales-audit-record__side">
            @if(auth()->user()?->can('ver reportes financieros'))
                <section class="app-card sales-audit-record__section" aria-labelledby="audit-financial-title">
                    <header><span class="sales-audit-record__section-icon" aria-hidden="true"><i class="bx bx-calculator"></i></span><div><span class="sales-audit-section-label">Conciliación</span><h2 id="audit-financial-title">Importes y pagos</h2></div></header>
                    <dl class="sales-audit-record__totals">
                        <div><dt>Subtotal vigente</dt><dd>${{ number_format($order->subtotal, 2) }}</dd></div>
                        <div><dt>Descuentos vigentes</dt><dd class="is-discount">−${{ number_format($this->discountTotal, 2) }}</dd></div>
                        <div class="is-total"><dt>Total vigente</dt><dd>${{ number_format($order->status === 'cancelada' ? 0 : $order->total, 2) }}</dd></div>
                        <div><dt>Total cobrado</dt><dd>${{ number_format(collect($this->financial['gross'])->sum(), 2) }}</dd></div>
                        <div><dt>Total reembolsado</dt><dd class="is-danger">−${{ number_format($this->refundTotal, 2) }}</dd></div>
                        <div class="is-net"><dt>Pago neto</dt><dd>${{ number_format(max(0, collect($this->financial['gross'])->sum() - $this->refundTotal), 2) }}</dd></div>
                    </dl>
                    <div class="sales-audit-record__payments">
                        @foreach($order->payments as $payment)<div><span><i class="bx {{ $payment->method_icon }}" aria-hidden="true"></i><strong>{{ $payment->method_label }}</strong><small>{{ \App\Support\BusinessTime::format($payment->created_at, 'd/m/Y g:i:s A') }}{{ $payment->card_last4 ? ' · •••• '.$payment->card_last4 : '' }}{{ $payment->transfer_reference ? ' · Ref. '.$payment->transfer_reference : '' }}</small></span><b>${{ number_format($payment->amount, 2) }}</b></div>@endforeach
                        @foreach($order->refunds as $refund)<div class="is-refund"><span><i class="bx bx-undo" aria-hidden="true"></i><strong>Reembolso {{ $refund->type === 'total' ? 'total' : 'parcial' }}</strong><small>{{ $refund->processor?->name ?? 'Usuario no disponible' }} · {{ \App\Support\BusinessTime::format($refund->processed_at, 'd/m/Y g:i:s A') }}{{ $refund->external_reference ? ' · Ref. '.$refund->external_reference : '' }}</small></span><b>−${{ number_format($refund->amount, 2) }}</b></div>@endforeach
                    </div>
                </section>
            @endif

            <section class="app-card sales-audit-record__section" aria-labelledby="audit-context-title">
                <header><span class="sales-audit-record__section-icon" aria-hidden="true"><i class="bx bx-info-circle"></i></span><div><span class="sales-audit-section-label">Contexto</span><h2 id="audit-context-title">Cliente y operación</h2></div></header>
                <dl class="sales-audit-record__facts">
                    <div><dt>Cliente</dt><dd>{{ $order->display_name }}</dd></div>
                    <div><dt>Teléfono</dt><dd>{{ $order->customer_phone ?: $order->customer?->phone ?: '—' }}</dd></div>
                    @if($order->type === 'delivery')<div><dt>Dirección</dt><dd>{{ $order->customer_address ?: $order->customer?->address ?: '—' }}</dd></div><div><dt>Referencias</dt><dd>{{ $order->customer_references ?: $order->customer?->references ?: '—' }}</dd></div><div><dt>Repartidor</dt><dd>{{ $order->deliveryAssignment?->driver?->name ?? '—' }}</dd></div>@endif
                    @if($order->type === 'mesa')<div><dt>Mesa / servicio</dt><dd>{{ $order->mesaService?->service_label ?? $order->mesa?->display_name ?? $order->table_identifier ?? '—' }}</dd></div><div><dt>Área</dt><dd>{{ $order->mesa?->area?->name ?? '—' }}</dd></div>@endif
                    <div><dt>Apertura de caja</dt><dd>{{ \App\Support\BusinessTime::format($order->cashRegister?->opened_at, 'd/m/Y g:i:s A') }} · {{ $order->cashRegister?->opener?->name ?? '—' }}</dd></div>
                    <div><dt>Cierre de caja</dt><dd>{{ \App\Support\BusinessTime::format($order->cashRegister?->closed_at, 'd/m/Y g:i:s A', 'Turno aún abierto') }}{{ $order->cashRegister?->closer ? ' · '.$order->cashRegister->closer->name : '' }}</dd></div>
                    @if($order->notes)<div><dt>Notas</dt><dd>{{ $order->notes }}</dd></div>@endif
                </dl>
            </section>

            @if($order->status === 'cancelada' || $order->items->contains('is_cancelled', true))
                <section class="app-card sales-audit-record__cancellation" aria-labelledby="audit-cancellation-title"><span class="sales-audit-record__section-icon" aria-hidden="true"><i class="bx bx-x-circle"></i></span><div><span class="sales-audit-section-label">Cancelaciones</span><h2 id="audit-cancellation-title">{{ $order->status === 'cancelada' ? 'Orden cancelada' : 'Cancelación parcial' }}</h2><p>{{ $order->cancellation_reason ?: 'Consulta las partidas tachadas y la trazabilidad para ver quién autorizó el movimiento.' }}</p>@if($order->cancelled_at)<small>{{ $order->cancelledBy?->name ?? 'Usuario no disponible' }} · {{ \App\Support\BusinessTime::format($order->cancelled_at, 'd/m/Y g:i:s A') }}</small>@endif</div></section>
            @endif
        </aside>
    </div>

    <x-ticket-preview-modal id="salesHistoryTicketModal" title="Reimpresión de ticket" eyebrow="Historial de ventas" initial-tab="ticket" :wire-ignore="true"
        :tabs="[['key' => 'ticket', 'label' => 'Ticket', 'icon' => 'bx-receipt', 'title' => 'Vista previa del ticket histórico']]" />
</div>
