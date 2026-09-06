@props([
    'id',
    'title' => 'Ticket',
    'eyebrow' => null,
    'tabs' => [],
    'initialTab' => null,
    'open' => false,
    'closeMethod' => null,
    'printLabel' => 'Imprimir',
    'wireIgnore' => false,
])

@php
    $normalizedTabs = collect($tabs)->map(function (array $tab, int|string $index): array {
        $key = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) ($tab['key'] ?? $index));

        return [
            'key' => $key,
            'label' => (string) ($tab['label'] ?? ucfirst($key)),
            'icon' => (string) ($tab['icon'] ?? 'bx-receipt'),
            'html' => (string) ($tab['html'] ?? ''),
            'title' => (string) ($tab['title'] ?? 'Vista previa del ticket'),
        ];
    })->values();
    $resolvedInitialTab = (string) ($initialTab ?: ($normalizedTabs->first()['key'] ?? 'ticket'));
    $titleId = $id.'-title';
@endphp

<div id="{{ $id }}" class="ticket-preview-modal{{ $open ? ' is-open' : '' }}"
    data-ticket-preview-modal data-initial-tab="{{ $resolvedInitialTab }}"
    role="dialog" aria-modal="true" aria-labelledby="{{ $titleId }}" aria-hidden="{{ $open ? 'false' : 'true' }}"
    @if ($wireIgnore) wire:ignore @endif>
    <section class="ticket-preview-dialog">
        <header class="ticket-preview-header">
            <span class="ticket-preview-header__icon" aria-hidden="true"><i class="bx bx-receipt"></i></span>
            <div class="ticket-preview-header__copy">
                @if ($eyebrow)<span>{{ $eyebrow }}</span>@endif
                <h4 id="{{ $titleId }}" data-ticket-preview-title>{{ $title }}</h4>
            </div>

            @if ($normalizedTabs->count() > 1)
                <div class="ticket-preview-tabs" role="tablist" aria-label="Tipo de ticket">
                    @foreach ($normalizedTabs as $tab)
                        <button type="button" data-ticket-tab="{{ $tab['key'] }}" role="tab"
                            aria-controls="{{ $id }}-pane-{{ $tab['key'] }}">
                            <i class="bx {{ $tab['icon'] }}" aria-hidden="true"></i> {{ $tab['label'] }}
                        </button>
                    @endforeach
                </div>
            @endif

            <button type="button" class="ticket-preview-close" data-ticket-preview-close
                @if ($closeMethod) wire:click="{{ $closeMethod }}" @endif aria-label="Cerrar vista previa">
                <i class="bx bx-x" aria-hidden="true"></i>
            </button>
        </header>

        <div class="ticket-preview-body">
            @foreach ($normalizedTabs as $tab)
                <div id="{{ $id }}-pane-{{ $tab['key'] }}" class="ticket-preview-pane"
                    data-ticket-pane="{{ $tab['key'] }}" role="tabpanel">
                    <div class="ticket-preview-frame" data-ticket-frame-shell="{{ $tab['key'] }}" aria-busy="true">
                        <div class="ticket-preview-loader" data-ticket-loader role="status" aria-live="polite">
                            <span class="ticket-preview-loader__spinner" aria-hidden="true"></span>
                            <strong>Preparando ticket</strong>
                            <small>Cargando dise&ntilde;o, tipograf&iacute;a e im&aacute;genes&hellip;</small>
                        </div>
                        <iframe id="{{ $id }}-frame-{{ $tab['key'] }}" data-ticket-frame="{{ $tab['key'] }}"
                            title="{{ $tab['title'] }}" srcdoc="{{ $tab['html'] }}"></iframe>
                    </div>
                </div>
            @endforeach
        </div>

        <footer class="ticket-preview-footer">
            <button type="button" class="ticket-preview-button is-secondary" data-ticket-preview-close
                @if ($closeMethod) wire:click="{{ $closeMethod }}" @endif>Cerrar</button>
            <button type="button" class="ticket-preview-button is-primary" data-ticket-preview-print disabled>
                <i class="bx bx-printer" aria-hidden="true"></i> {{ $printLabel }}
            </button>
        </footer>
    </section>
</div>
