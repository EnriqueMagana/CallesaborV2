@props(['business', 'openingStatus', 'title', 'subtitle', 'icon'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $business->primary_color ?? '#15803d' }}">
    <meta name="description" content="{{ $subtitle }} — {{ $business->business_name }}">
    <title>{{ $title }} | {{ $business->business_name }}</title>
    <link rel="icon" href="{{ $business->logo_path ? Storage::url($business->logo_path) : asset('assets/img/favicon/favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/boxicons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/public-menu.css') }}?v={{ filemtime(public_path('assets/css/public-menu.css')) }}">
</head>
<body style="--menu-primary: {{ $business->primary_color ?? '#15803d' }}">
    <a class="menu-skip-link skip-link" href="#contenido">Saltar al contenido</a>
    <x-public-menu.brand-header
        :business="$business"
        :opening-status="$openingStatus"
        eyebrow="Información del restaurante"
        :message="$title.' — '.$subtitle"
        action-label="Volver al menú"
        :action-href="route('public.menu').'#menu'"
        action-icon="bx-left-arrow-alt"
    />
    <main class="info-page__main" id="contenido" tabindex="-1">
        {{ $slot }}
    </main>
    <x-public-menu.footer :business="$business" />
</body>
</html>
