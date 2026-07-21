@props([
    'model',
    'checked' => false,
    'title',
    'description',
    'icon' => 'bx-lock-alt',
    'disabled' => false,
    'compact' => false,
])

<article
    class="sidebar-risk-toggle {{ $compact ? 'is-compact' : '' }} {{ $disabled ? 'is-unavailable' : '' }}"
    wire:key="policy-toggle-{{ md5($model.'|'.$title.'|'.((int) $checked)) }}"
    x-data="{ enabled: $wire.entangle(@js($model)) }"
    x-bind:class="{ 'is-enabled': enabled }"
>
    <span class="sidebar-risk-toggle__icon"><i class="bx {{ $icon }}" aria-hidden="true"></i></span>
    <span class="sidebar-risk-toggle__copy"><strong>{{ $title }}</strong><small>{{ $description }}</small></span>
    <button
        type="button"
        class="sidebar-risk-toggle__switch"
        role="switch"
        x-bind:aria-checked="enabled ? 'true' : 'false'"
        x-bind:aria-label="(enabled ? 'Desactivar: ' : 'Activar: ') + @js($title)"
        x-on:click="enabled = !enabled"
        @disabled($disabled)
    ><span></span></button>
</article>
