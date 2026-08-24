@props(['settings' => null, 'fallbackIcon' => 'bx-restaurant'])
<span {{ $attributes }}>
    @if($settings?->logo_path)
        <img src="{{ Storage::url($settings->logo_path) }}" alt="Logo de {{ $settings->business_name }}">
    @else
        <i class="bx {{ $fallbackIcon }}" aria-hidden="true"></i>
    @endif
</span>
