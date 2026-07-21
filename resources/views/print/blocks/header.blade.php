<header class="ticket-header">
    @if($template->show_logo && ($business->ticket_logo_path || $business->logo_path))
        <img class="ticket-logo" src="{{ Storage::url($business->ticket_logo_path ?: $business->logo_path) }}" alt="Logo de {{ $business->business_name }}">
    @endif
    <h1>{{ $business->business_name }}</h1>
    <strong>{{ $payload['title'] ?? $template->name }}</strong>
</header>
<hr>
