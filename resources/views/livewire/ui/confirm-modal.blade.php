<div>
@if($show)
@php
    $cfg = match($type) {
        'warning' => ['bg'=>'bg-warning',   'text'=>'text-warning',   'btn'=>'btn-warning',   'icon'=>'bx-error-circle'],
        'info'    => ['bg'=>'bg-info',      'text'=>'text-info',      'btn'=>'btn-info',       'icon'=>'bx-info-circle'],
        'success' => ['bg'=>'bg-success',   'text'=>'text-success',   'btn'=>'btn-success',    'icon'=>'bx-check-circle'],
        default   => ['bg'=>'bg-danger',    'text'=>'text-danger',    'btn'=>'btn-danger',     'icon'=>'bx-trash-alt'],
    };
@endphp

{{-- Backdrop --}}
<div class="modal-backdrop fade show" style="z-index:1140;" wire:click="cancel"></div>

{{-- Modal --}}
<div class="modal fade show d-block" tabindex="-1" style="z-index:1145;" role="dialog">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content border-0 shadow-lg overflow-hidden">

            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <div class="d-flex align-items-center gap-3 w-100">
                    <div class="flex-shrink-0 rounded-circle d-flex align-items-center justify-content-center
                                bg-label-{{ $type }}"
                         style="width:52px;height:52px;">
                        <i class="bx {{ $cfg['icon'] }} fs-3 {{ $cfg['text'] }}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="modal-title fw-bold mb-0">{{ $title }}</h5>
                    </div>
                    <button type="button" class="btn-close ms-auto" wire:click="cancel"
                            aria-label="Cerrar"></button>
                </div>
            </div>

            <div class="modal-body px-4 py-3">
                <p class="text-muted mb-0" style="font-size:.95rem;line-height:1.5;">
                    {!! $message !!}
                </p>
            </div>

            <div class="modal-footer border-0 px-4 pb-4 pt-2 gap-2 justify-content-end">
                <button type="button"
                        class="btn btn-outline-secondary"
                        wire:click="cancel"
                        wire:loading.attr="disabled">
                    {{ $cancelText }}
                </button>

                <button type="button"
                        class="btn {{ $cfg['btn'] }} d-inline-flex align-items-center gap-2"
                        wire:click="confirm"
                        wire:loading.attr="disabled"
                        wire:target="confirm">
                    <span wire:loading.remove wire:target="confirm">
                        <i class="bx {{ $cfg['icon'] }} me-1"></i>{{ $confirmText }}
                    </span>
                    <span wire:loading wire:target="confirm"
                          style="gap:.4rem;">
                        <span class="spinner-border spinner-border-sm"
                              style="width:.9rem;height:.9rem;border-width:2px;"></span>
                        Procesando...
                    </span>
                </button>
            </div>

        </div>
    </div>
</div>
@endif
</div>
