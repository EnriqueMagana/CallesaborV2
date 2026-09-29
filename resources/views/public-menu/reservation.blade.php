<x-public-menu.info-layout
    :business="$business"
    :menu-settings="$menuSettings"
    :opening-status="$openingStatus"
    title="Reservar una mesa"
    subtitle="Elige fecha, horario y número de personas en un flujo dedicado."
    icon="bx-calendar-heart"
    :livewire="true"
>
    <div class="menu-container info-standalone info-reservation-page">
        <section class="info-reservation-card" aria-labelledby="reservation-page-title">
            <div class="info-section-heading">
                <span><i class="bx bx-calendar-check" aria-hidden="true"></i></span>
                <div>
                    <small>Reservación en línea</small>
                    <h2 id="reservation-page-title">Tu mesa, en pocos pasos</h2>
                    <p>Consulta la disponibilidad y envía tu solicitud desde cualquier dispositivo.</p>
                </div>
            </div>
            <livewire:public-reservation :auto-open="true" />
        </section>
    </div>
</x-public-menu.info-layout>
