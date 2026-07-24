<section class="ticket-purchase-items">
    <strong class="ticket-section-title">INSUMOS SOLICITADOS</strong>
    @foreach(($payload['items'] ?? []) as $index => $item)
        <article class="ticket-purchase-item">
            <span class="ticket-purchase-number">{{ $index + 1 }}</span>
            <div>
                <strong>{{ $item['name'] ?? 'Insumo' }}</strong>
                <b>{{ rtrim(rtrim(number_format((float) ($item['quantity'] ?? 0), 3, '.', ''), '0'), '.') }} {{ $item['unit'] ?? '' }}</b>
                @if(!empty($item['notes']))<small>{{ $item['notes'] }}</small>@endif
                <span class="ticket-purchase-check"><i></i> Comprado</span>
            </div>
        </article>
    @endforeach
</section>
