@props([
    'panel',
    'title',
    'titleId',
    'eyebrow',
    'description' => null,
    'icon' => 'bx-window',
    'tone' => 'default',
    'closeLabel' => 'Cerrar panel',
    'closeAction' => null,
    'panelClass' => '',
    'bodyClass' => '',
])

@php
    $dismiss = $closeAction ?: "panels.{$panel} = false";
@endphp

<div class="pos-overlay-panel" :class="panels.{{ $panel }} ? 'show' : ''">
    <div class="pos-overlay-backdrop" x-on:click="{{ $dismiss }}"></div>
    <section
        {{ $attributes->class(['pos-panel', 'pos-area-panel', 'pos-floating-panel', $panelClass]) }}
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $titleId }}">
        <header class="panel-header pos-area-panel__header">
            <span class="pos-area-panel__mark is-{{ $tone }}" aria-hidden="true">
                <i class="bx {{ $icon }}"></i>
            </span>
            <div>
                <span class="pos-area-panel__eyebrow">{{ $eyebrow }}</span>
                <h2 id="{{ $titleId }}">{{ $title }}</h2>
                @if ($description)
                    <p>{{ $description }}</p>
                @endif
            </div>
            <button type="button" class="btn-panel-close" x-on:click="{{ $dismiss }}"
                aria-label="{{ $closeLabel }}">
                <i class="bx bx-x" aria-hidden="true"></i>
            </button>
        </header>

        @isset($navigation)
            {{ $navigation }}
        @endisset

        @isset($tools)
            <div class="pos-area-panel__tools">
                {{ $tools }}
            </div>
        @endisset

        @isset($beforeBody)
            {{ $beforeBody }}
        @endisset

        @isset($body)
            {{ $body }}
        @else
            <div class="panel-body pos-area-panel__body {{ $bodyClass }}">
                {{ $slot }}
            </div>
        @endisset

        @isset($footer)
            {{ $footer }}
        @endisset
    </section>
</div>
