<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#6956e8">
    <title>Kiosco · {{ $businessSettings?->business_name ?? config('app.name') }}</title>
    <link rel="icon" href="{{ $businessSettings?->logo_path ? Storage::url($businessSettings->logo_path) : asset('assets/img/favicon/favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/boxicons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/kiosk.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/delivery-tracking.css') }}?v={{ filemtime(public_path('assets/css/delivery-tracking.css')) }}">
    @livewireStyles
</head>
<body class="kiosk-body">
    {{ $slot }}
    @livewireScripts
</body>
</html>
