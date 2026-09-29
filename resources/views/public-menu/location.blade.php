<x-public-menu.info-layout
    :business="$business"
    :menu-settings="$menuSettings"
    :opening-status="$openingStatus"
    title="Ubicación"
    subtitle="Consulta el mapa y abre una ruta directa para visitarnos."
    icon="bx-map-alt"
>
    <div class="menu-container info-standalone info-standalone--location">
        <x-public-menu.location-section :business="$business" :location-map="$locationMap" />
    </div>
</x-public-menu.info-layout>
