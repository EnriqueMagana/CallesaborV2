@if(!empty($payload['delivery']))
<section class="ticket-delivery">
    <strong>DATOS DE ENTREGA</strong>
    @if(!empty($payload['customer']))<div>{{ $payload['customer'] }}</div>@endif
    @if(!empty($payload['delivery']['phone']))<div>Tel: {{ $payload['delivery']['phone'] }}</div>@endif
    @if(!empty($payload['delivery']['address']))<div>Dirección: {{ $payload['delivery']['address'] }}</div>@endif
    @if(!empty($payload['delivery']['references']))<div>Referencias: {{ $payload['delivery']['references'] }}</div>@endif
    @if(!empty($payload['delivery']['method']))<div>Cobro: {{ $payload['delivery']['method'] }}</div>@endif
</section>
<hr>
@endif
