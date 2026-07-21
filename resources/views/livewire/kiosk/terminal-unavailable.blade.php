<main class="kiosk-shell kiosk-unavailable-shell">
    <header class="kiosk-header">
        <div class="kiosk-brand">
            <x-business.brand-mark :settings="$businessSettings" class="kiosk-brand-mark" />
            <span><strong>{{ $businessSettings?->business_name ?? config('app.name') }}</strong><small>Kiosco de autoservicio</small></span>
        </div>
        <span class="kiosk-unavailable-status"><i class="bx bx-time-five"></i> Servicio en pausa</span>
    </header>

    <section class="kiosk-unavailable">
        <div class="kiosk-unavailable-illustration">
            <span><i class="bx bx-desktop"></i></span>
            <i class="bx bx-pause"></i>
        </div>
        <span class="kiosk-eyebrow">Volveremos pronto</span>
        <h1>{{ $accessState === 'paused' ? 'Aún no hay terminales abiertas' : 'Este acceso ya no está disponible' }}</h1>
        <p>
            {{ $accessState === 'paused'
                ? 'El kiosco está pausado temporalmente. Puedes esperar aquí o pedir ayuda a uno de nuestros colaboradores.'
                : 'La dirección del kiosco cambió o dejó de estar activa. Solicita ayuda a uno de nuestros colaboradores.' }}
        </p>
        @if($unavailableTerminalName)
            <span class="kiosk-unavailable-terminal"><i class="bx bx-devices"></i>{{ $unavailableTerminalName }}</span>
        @endif
        <div class="kiosk-unavailable-actions">
            <button type="button" class="kiosk-primary-button" wire:click="refreshAvailability" wire:loading.attr="disabled" wire:target="refreshAvailability">
                <span wire:loading.remove wire:target="refreshAvailability"><i class="bx bx-refresh"></i> Comprobar nuevamente</span>
                <span wire:loading wire:target="refreshAvailability">Comprobando…</span>
            </button>
            <div class="kiosk-unavailable-help"><i class="bx bx-user-voice"></i><span><strong>¿Necesitas ordenar ahora?</strong><small>Un colaborador puede tomar tu pedido en caja.</small></span></div>
        </div>
        <small class="kiosk-auto-check"><i class="bx bx-pointer"></i> Usa “Comprobar nuevamente” cuando quieras verificar la disponibilidad.</small>
    </section>
</main>
