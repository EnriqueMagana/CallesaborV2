<section class="app-card sales-audit-results" aria-labelledby="sales-audit-results-title">
    <div class="sales-audit-results__header">
        <div><span class="sales-audit-section-label">Detalle verificable</span><h2 id="sales-audit-results-title" class="app-card-title">Órdenes auditadas</h2><p class="app-card-description">Expande una orden para revisar sus partidas, responsables, pagos y trazabilidad.</p></div>
        <span class="app-count-pill">{{ number_format($this->orders->total()) }} registros</span>
    </div>

    @if($this->orders->isEmpty())
        <div class="app-empty-state"><span class="app-empty-icon"><i class="bx bx-search-alt" aria-hidden="true"></i></span><h3>Sin coincidencias</h3><p>Ajusta el periodo o elimina algún filtro para ampliar la consulta.</p></div>
    @else
        <div class="table-responsive sales-audit-desktop-table">
            <table class="table sales-audit-table">
                <thead><tr>
                    <th scope="col">Folio / fecha</th><th scope="col">Canal</th><th scope="col">Cliente</th><th scope="col">Tiempo</th>
                    @if($this->canViewFinancials)<th scope="col" class="text-end">Subtotal</th><th scope="col" class="text-end">Descuento</th><th scope="col" class="text-end">Total</th>@endif
                    <th scope="col">Estado</th><th scope="col"><span class="visually-hidden">Detalle</span></th>
                </tr></thead>
                <tbody>
                @foreach($this->orders as $order)
                    @php
                        $discountTotal = $this->canViewFinancials ? $order->items->where('is_cancelled', false)->sum(fn($item) => (float) $item->promotion_discount + (float) $item->discount_amount) : null;
                        $channelDetail = $order->type === 'mesa' ? ($order->mesaService?->service_label ?? $order->mesa?->display_name ?? $order->table_identifier ?? 'Mesa sin identificar') : ($order->type === 'delivery' ? ($order->deliveryAssignment?->driver?->name ?? $order->delivery_method_label) : ($order->cashRegister?->name ?? 'Sin caja'));
                    @endphp
                    <tr wire:key="audit-row-{{ $order->id }}" class="{{ $expandedOrderId === $order->id ? 'is-expanded' : '' }}">
                        <td><strong class="sales-audit-folio">{{ $order->display_folio }}</strong><small>{{ \App\Support\BusinessTime::format($order->created_at, 'd/m/Y · g:i A') }}</small></td>
                        <td><span class="sales-audit-channel"><i class="bx {{ $order->type_icon }}" aria-hidden="true"></i>{{ $order->type_label }}</span><small>{{ $channelDetail }}</small></td>
                        <td><strong>{{ $order->display_name }}</strong><small>{{ $order->customer_phone ?: 'Sin teléfono' }}</small></td>
                        <td><strong>{{ $this->durationLabel($order) }}</strong><small>{{ $order->seller?->name ?? 'Sin responsable' }}</small></td>
                        @if($this->canViewFinancials)
                            <td class="text-end sales-audit-number">&#36;{{ number_format($order->subtotal, 2) }}</td>
                            <td class="text-end sales-audit-number sales-audit-number--discount">-&#36;{{ number_format($discountTotal, 2) }}</td>
                            <td class="text-end sales-audit-number"><strong>&#36;{{ number_format($order->total, 2) }}</strong></td>
                        @endif
                        <td><span class="app-status app-status--{{ $order->status_color }}">{{ $order->status_label }}</span></td>
                        <td><button type="button" wire:click="toggleOrder({{ $order->id }})" class="sales-audit-expand" aria-label="{{ $expandedOrderId === $order->id ? 'Ocultar' : 'Ver' }} detalle de {{ $order->display_folio }}" aria-expanded="{{ $expandedOrderId === $order->id ? 'true' : 'false' }}"><i class="bx {{ $expandedOrderId === $order->id ? 'bx-chevron-up' : 'bx-chevron-down' }}" aria-hidden="true"></i></button></td>
                    </tr>
                    @if($expandedOrderId === $order->id)
                        <tr class="sales-audit-detail-row"><td colspan="{{ $this->canViewFinancials ? 9 : 6 }}">@include('livewire.orders.partials.sales-audit-order-detail', ['order' => $order, 'discountTotal' => $discountTotal])</td></tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="sales-audit-mobile-list">
            @foreach($this->orders as $order)
                @php $discountTotal = $this->canViewFinancials ? $order->items->where('is_cancelled', false)->sum(fn($item) => (float) $item->promotion_discount + (float) $item->discount_amount) : null; @endphp
                <article class="sales-audit-mobile-card" wire:key="audit-mobile-{{ $order->id }}">
                    <button type="button" wire:click="toggleOrder({{ $order->id }})" class="sales-audit-mobile-card__main" aria-expanded="{{ $expandedOrderId === $order->id ? 'true' : 'false' }}">
                        <span class="sales-audit-mobile-card__top"><span class="sales-audit-channel"><i class="bx {{ $order->type_icon }}" aria-hidden="true"></i>{{ $order->type_label }}</span><span class="app-status app-status--{{ $order->status_color }}">{{ $order->status_label }}</span></span>
                        <span class="sales-audit-mobile-card__identity"><span><strong>{{ $order->display_folio }}</strong><small>{{ \App\Support\BusinessTime::format($order->created_at, 'd/m/Y · g:i A') }}</small></span>@if($this->canViewFinancials)<b>&#36;{{ number_format($order->total, 2) }}</b>@endif</span>
                        <span class="sales-audit-mobile-card__meta"><span><small>Cliente</small><strong>{{ $order->display_name }}</strong></span><span><small>Tiempo</small><strong>{{ $this->durationLabel($order) }}</strong></span></span>
                        <span class="sales-audit-mobile-card__toggle">{{ $expandedOrderId === $order->id ? 'Ocultar detalle' : 'Ver auditoría completa' }} <i class="bx {{ $expandedOrderId === $order->id ? 'bx-chevron-up' : 'bx-chevron-down' }}" aria-hidden="true"></i></span>
                    </button>
                    @if($expandedOrderId === $order->id)@include('livewire.orders.partials.sales-audit-order-detail', ['order' => $order, 'discountTotal' => $discountTotal])@endif
                </article>
            @endforeach
        </div>

        @if($this->orders->hasPages())<div class="sales-audit-pagination">{{ $this->orders->links('pagination::bootstrap-5') }}</div>@endif
    @endif
</section>
