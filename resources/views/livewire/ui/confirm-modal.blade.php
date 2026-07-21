<div>
    @if($show)
        @php
            $cfg = match($type) {
                'warning' => ['modifier'=>'is-warning', 'icon'=>'bx-error-circle'],
                'info' => ['modifier'=>'is-info', 'icon'=>'bx-info-circle'],
                'success' => ['modifier'=>'is-success', 'icon'=>'bx-check-circle'],
                default => ['modifier'=>'is-danger', 'icon'=>'bx-shield-x'],
            };
        @endphp
        <div class="confirm-dialog-backdrop" wire:click="cancel"></div>
        <div class="confirm-dialog-layer" role="dialog" aria-modal="true" aria-labelledby="confirm-dialog-title">
            <section class="confirm-dialog {{ $cfg['modifier'] }}">
                <header class="confirm-dialog-header">
                    <span class="confirm-dialog-icon"><i class="bx {{ $cfg['icon'] }}"></i></span>
                    <div><small>Confirmación requerida</small><h2 id="confirm-dialog-title">{{ $title }}</h2></div>
                    <button type="button" class="confirm-dialog-close" wire:click="cancel" aria-label="Cerrar"><i class="bx bx-x"></i></button>
                </header>
                <div class="confirm-dialog-body">
                    <div class="confirm-dialog-message">{!! $message !!}</div>
                    @if($type === 'warning')
                        <div class="confirm-dialog-notice"><i class="bx bx-info-circle"></i><span>Esta acción tendrá efecto inmediatamente.</span></div>
                    @endif
                </div>
                <footer class="confirm-dialog-actions">
                    <button type="button" class="confirm-dialog-secondary" wire:click="cancel" wire:loading.attr="disabled">{{ $cancelText }}</button>
                    <button type="button" class="confirm-dialog-primary" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm">
                        <span wire:loading.remove wire:target="confirm"><i class="bx {{ $cfg['icon'] }}"></i>{{ $confirmText }}</span>
                        <span wire:loading wire:target="confirm"><i class="bx bx-loader-alt bx-spin"></i>Procesando…</span>
                    </button>
                </footer>
            </section>
        </div>
    @endif
</div>
