<x-public-menu.info-layout
    :business="$business"
    :menu-settings="$menuSettings"
    :opening-status="$openingStatus"
    title="Redes sociales"
    subtitle="Conecta con nuestros canales oficiales y descubre las novedades del restaurante."
    icon="bx-share-alt"
>
    <div class="menu-container info-standalone info-standalone--social">
        <x-public-menu.social-section :business="$business" />
    </div>
</x-public-menu.info-layout>
