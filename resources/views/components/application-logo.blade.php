@if($businessSettings?->logo_path)
    <img
        src="{{ Storage::url($businessSettings->logo_path) }}"
        alt="Logo de {{ $businessSettings->business_name }}"
        {{ $attributes->class('object-contain') }}
    >
@else
    <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ $businessSettings?->business_name ?? config('app.name') }}" {{ $attributes }}>
        <path d="M12 23h40v7c0 10.7-8.4 19.5-19 20v4h11v4H20v-4h9v-4C19.4 48.5 12 40.1 12 30v-7Zm5 4v3c0 8.8 6.7 16 15 16s15-7.2 15-16v-3H17Zm7-20c4 3.2 4 6.8 0 10-2.1-2.7-2.1-7.3 0-10Zm9 0c4 3.2 4 6.8 0 10-2.1-2.7-2.1-7.3 0-10Zm9 0c4 3.2 4 6.8 0 10-2.1-2.7-2.1-7.3 0-10Z"/>
    </svg>
@endif
