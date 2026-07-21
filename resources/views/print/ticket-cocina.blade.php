{{-- Compatibilidad con enlaces antiguos; la plantilla activa se administra desde Ticket Maker. --}}
{!! app(\App\Services\ThermalTicketRenderer::class)->renderOrder($order, 'kitchen_area', $printArea ?? null) !!}
