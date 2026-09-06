@props(['business', 'menuSettings', 'openingStatus', 'title', 'subtitle', 'icon'])

<x-public-menu.site-layout
    :business="$business"
    :menu-settings="$menuSettings"
    :title="$title.' | '.$business->business_name"
    :description="$subtitle.' — '.$business->business_name"
    font-url="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap">
    <a class="menu-skip-link skip-link" href="#contenido">Saltar al contenido</a>
    <x-public-menu.brand-header
        :business="$business"
        :menu-settings="$menuSettings"
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
    <x-public-menu.footer :business="$business" :menu-settings="$menuSettings" />
    <x-slot:scripts>
        <script src="{{ asset('assets/js/public-menu.js') }}?v={{ filemtime(public_path('assets/js/public-menu.js')) }}" defer></script>
    </x-slot:scripts>
</x-public-menu.site-layout>
