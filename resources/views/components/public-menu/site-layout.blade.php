@props([
    'business',
    'menuSettings',
    'title',
    'description',
    'bodyClass' => '',
    'styles' => [],
    'fontUrl' => null,
    'livewire' => false,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $menuSettings->primary_color ?? '#15803d' }}">
    <meta name="description" content="{{ $description }}">
    <title>{{ $title }}</title>
    @include('partials.favicon')
    @if ($fontUrl)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="preload" href="{{ $fontUrl }}" as="style">
        <link href="{{ $fontUrl }}" rel="stylesheet" media="print" onload="this.media='all'">
        <noscript><link href="{{ $fontUrl }}" rel="stylesheet"></noscript>
    @endif
    <link rel="stylesheet"
        href="{{ asset('assets/vendor/fonts/boxicons.css') }}?v={{ filemtime(public_path('assets/vendor/fonts/boxicons.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('assets/css/public-menu.css') }}?v={{ filemtime(public_path('assets/css/public-menu.css')) }}">
    @foreach ($styles as $stylesheet)
        <link rel="stylesheet" href="{{ asset($stylesheet) }}?v={{ filemtime(public_path($stylesheet)) }}">
    @endforeach
    @if ($livewire)
        @livewireStyles
    @endif
</head>
<body @if (filled($bodyClass)) class="{{ $bodyClass }}" @endif style="--menu-primary: {{ $menuSettings->primary_color ?? '#15803d' }}">
    {{ $slot }}
    @if ($livewire)
        @livewireScripts
    @endif
    {{ $scripts ?? '' }}
</body>
</html>
