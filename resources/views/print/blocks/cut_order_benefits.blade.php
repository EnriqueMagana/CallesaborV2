@if(!empty($payload['cut']['benefit_orders']))
<section class="ticket-cut ticket-cut-benefits">
    <strong class="ticket-section-title">PROMOCIONES Y DESCUENTOS POR ORDEN</strong>
    <div class="ticket-cut-caption">Detalle informativo · no modifica el corte</div>
    @foreach($payload['cut']['benefit_orders'] as $order)
        <article class="ticket-cut-benefit-order">
            <div class="ticket-row ticket-cut-benefit-order-title">
                <strong>{{ $order['folio'] }}</strong>
                @if(($order['total_discount'] ?? 0) > 0)
                    <strong>-${{ number_format($order['total_discount'], 2) }}</strong>
                @endif
            </div>
            @if(!empty($order['customer']))
                <div class="ticket-cut-caption">{{ $order['customer'] }}</div>
            @endif
            @foreach($order['benefits'] ?? [] as $benefit)
                <div class="ticket-cut-benefit-line">
                    <strong>{{ $benefit['type_label'] }}</strong>: {{ $benefit['name'] }}
                    @if(!empty($benefit['product']))
                        <span> · {{ $benefit['product'] }}</span>
                    @endif
                    @if(($benefit['amount'] ?? 0) > 0)
                        <span> (-${{ number_format($benefit['amount'], 2) }})</span>
                    @endif
                </div>
            @endforeach
        </article>
    @endforeach
</section>
<hr>
@endif
