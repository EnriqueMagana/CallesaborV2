<section class="ticket-business">
    @if(($template->options['show_rfc'] ?? true) && $business->rfc)<div>RFC: {{ $business->rfc }}</div>@endif
    @if(($template->options['show_address'] ?? true) && $business->full_address)<div>{{ $business->full_address }}</div>@endif
    @if(($template->options['show_phone'] ?? true) && $business->phone)<div>Tel: {{ $business->phone }}</div>@endif
</section>
<hr>
