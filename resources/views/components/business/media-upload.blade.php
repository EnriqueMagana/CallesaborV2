@props(['title', 'description', 'model', 'path' => null, 'upload' => null, 'accept' => 'image/*'])
@php
    $temporaryPreview = $upload && method_exists($upload, 'temporaryUrl') ? $upload->temporaryUrl() : null;
    $previewUrl = $temporaryPreview ?: ($path ? Storage::url($path) : null);
@endphp
<section class="biz-media-card">
    <div class="biz-media-card__preview">
        <div class="biz-media-skeleton" wire:loading.flex wire:target="{{ $model }}" role="status" aria-label="Procesando {{ strtolower($title) }}"><span></span></div>
        <div wire:loading.remove wire:target="{{ $model }}" class="biz-media-preview-content">
            @if($previewUrl)
                <img src="{{ $previewUrl }}" alt="Vista previa de {{ strtolower($title) }}">
            @else
                <i class="bx bx-image"></i><small>Sin imagen</small>
            @endif
        </div>
    </div>
    <div class="biz-media-card__copy">
        <strong>{{ $title }}</strong><small>{{ $description }}</small>
        <label class="biz-upload-button"><i class="bx bx-upload"></i><span>Seleccionar imagen</span><input type="file" wire:model="{{ $model }}" accept="{{ $accept }}"></label>
        @if($temporaryPreview)<span class="biz-upload-ready"><i class="bx bx-check-circle"></i>Vista previa lista para guardar</span>@endif
        <span wire:loading wire:target="{{ $model }}" class="biz-upload-status" role="status"><i class="bx bx-loader-alt bx-spin"></i>Preparando vista previa…</span>
    </div>
</section>
