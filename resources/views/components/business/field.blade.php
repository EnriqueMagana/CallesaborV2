@props(['label', 'for', 'hint' => null, 'error' => null, 'full' => false])
<label class="biz-field {{ $full ? 'biz-field--full' : '' }}" for="{{ $for }}">
    <span class="biz-field__label">{{ $label }}</span>
    {{ $slot }}
    @if($hint)<small class="biz-field__hint">{{ $hint }}</small>@endif
    @if($error)<small class="biz-field__error" role="alert">{{ $error }}</small>@endif
</label>
