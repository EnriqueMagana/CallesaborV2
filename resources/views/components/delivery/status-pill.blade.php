@props(['status'])

@php
    [$icon, $label, $class] = match ($status) {
        'pendiente' => ['bx-time-five', 'Recibido', 'is-pending'],
        'en_preparacion' => ['bx-bowl-hot', 'En preparación', 'is-preparing'],
        'lista' => ['bx-check-circle', 'Listo para salir', 'is-ready'],
        'pagada' => ['bx-check-shield', 'Pagado · listo para salir', 'is-ready'],
        'en_reparto' => ['bx-cycling', 'Delivery asignado', 'is-route'],
        'entregada' => ['bx-home-heart', 'Entregado', 'is-delivered'],
        default => ['bx-receipt', ucfirst(str_replace('_', ' ', $status)), 'is-neutral'],
    };
@endphp

<span {{ $attributes->class(['delivery-status', $class]) }}>
    <i class="bx {{ $icon }}" aria-hidden="true"></i>
    <span>{{ $label }}</span>
</span>
