<section class="ticket-items">
    @foreach($payload['items'] ?? [] as $item)
        <article class="ticket-item">
            <div class="ticket-row ticket-item-main">
                <strong>{{ $item['quantity'] }}× {{ $item['name'] }}</strong>
                @unless($template->key === 'kitchen_area')<span>${{ number_format($item['subtotal'], 2) }}</span>@endunless
            </div>
            @foreach($item['modifiers'] ?? [] as $modifier)
                <div class="ticket-row ticket-sub"><span>{{ $modifier['name'] }}</span>@if(($modifier['price'] ?? 0) > 0)<span>+${{ number_format($modifier['price'], 2) }}</span>@endif</div>
            @endforeach
            @if(!empty($item['notes']))<div class="ticket-note">* {{ $item['notes'] }}</div>@endif
        </article>
    @endforeach
</section>
<hr>
