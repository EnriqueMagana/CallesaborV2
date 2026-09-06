@props([
    'status',
    'variant' => 'menu',
    'href' => null,
])

@php
    $target = $href ?: route('public.hours');
    $baseClass = $variant === 'home' ? 'home-hero__status' : 'menu-status';
    $stateClass = $baseClass.'--'.($status['is_open'] ? 'open' : 'closed');
@endphp

<a {{ $attributes->class([$baseClass, $stateClass])->merge([
    'href' => $target,
    'aria-label' => $status['label'].': '.$status['detail'].'. Ver horarios',
]) }}>
    <span aria-hidden="true"></span>
    <span><strong>{{ $status['label'] }}</strong><small>{{ $status['detail'] }}</small></span>
</a>
