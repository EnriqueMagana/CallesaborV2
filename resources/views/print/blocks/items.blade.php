<section class="ticket-items">
    @foreach ($payload['items'] ?? [] as $item)
        @php($isCancelled = (bool) ($item['is_cancelled'] ?? false))
        <article class="ticket-item {{ $isCancelled ? 'ticket-item--cancelled' : '' }}">
            <div class="ticket-row ticket-item-main">
                <strong>{{ $item['quantity'] }}× {{ $item['name'] }}</strong>
                @unless ($template->key === 'kitchen_area')
                    <span>${{ number_format($item['subtotal'], 2) }}</span>
                @endunless
            </div>
            @if ($isCancelled)
                <div class="ticket-note ticket-item-cancelled-note">CANCELADO / RETIRADO · NO SE COBRARÁ</div>
            @endif
            @foreach ($item['benefits'] ?? [] as $benefit)
                <div class="ticket-item-benefit">
                    <strong>{{ $benefit['type_label'] }}: {{ $benefit['name'] }}@if (($benefit['amount'] ?? 0) > 0) (-${{ number_format($benefit['amount'], 2) }})@endif</strong>
                </div>
            @endforeach
            @foreach ($item['modifiers'] ?? [] as $modifier)
                <div class="ticket-row ticket-sub"><span>{{ $modifier['name'] }}</span>
                    @if (($modifier['price'] ?? 0) > 0)
                        <span>+${{ number_format($modifier['price'], 2) }}</span>
                    @endif
                </div>
            @endforeach
            @if (!empty($item['notes']))
                <div class="ticket-note">* {{ $item['notes'] }}</div>
            @endif
        </article>
    @endforeach
</section>
<hr>
