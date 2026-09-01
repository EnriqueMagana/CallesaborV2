<form wire:submit="save" class="profile-notification-settings">
    <div class="profile-notification-settings__primary">
        <label class="profile-setting-switch">
            <span><strong>Recibir notificaciones</strong><small>Avisos relacionados únicamente con tus responsabilidades.</small></span>
            <input type="checkbox" wire:model="notificationsEnabled"><i aria-hidden="true"></i>
        </label>
        <label class="profile-setting-switch">
            <span><strong>Reproducir sonidos</strong><small>El navegador requiere una interacción antes de permitir audio.</small></span>
            <input type="checkbox" wire:model="soundEnabled"><i aria-hidden="true"></i>
        </label>
    </div>

    <div class="profile-notification-settings__grid">
        <section>
            <div class="profile-notification-settings__subheading"><i class="bx bx-volume-full"></i><span><strong>Volumen</strong><small>Nivel del sonido en este perfil.</small></span></div>
            <label class="profile-notification-volume" x-data="{ previewVolume: @js($volume) }">
                <span>Volumen <strong x-text="`${previewVolume}%`">{{ $volume }}%</strong></span>
                <input type="range" min="0" max="100" step="5" x-model.number="previewVolume" x-on:change="$wire.set('volume', previewVolume)" onclick="window.AppNotificationSound?.unlock()" @disabled(!$soundEnabled)>
            </label>
            <div class="profile-notification-sounds">
                <button type="button" onclick="window.AppNotificationSound?.unlock(); window.AppNotificationSound?.play('order', {{ $volume }})"><i class="bx bx-receipt"></i> Pedido</button>
                <button type="button" onclick="window.AppNotificationSound?.unlock(); window.AppNotificationSound?.play('ready', {{ $volume }})"><i class="bx bx-check-circle"></i> Listo</button>
                <button type="button" onclick="window.AppNotificationSound?.unlock(); window.AppNotificationSound?.play('delivery', {{ $volume }})"><i class="bx bx-cycling"></i> Delivery</button>
            </div>
        </section>

        <section>
            <div class="profile-notification-settings__subheading"><i class="bx bx-moon"></i><span><strong>Horario silencioso</strong><small>Los avisos se guardan sin emitir sonido.</small></span></div>
            <label class="profile-setting-switch profile-setting-switch--compact">
                <span><strong>Activar horario</strong></span>
                <input type="checkbox" wire:model="quietHoursEnabled"><i aria-hidden="true"></i>
            </label>
            <div class="profile-notification-times">
                <label><span>Desde</span><input type="time" wire:model="quietHoursStart" @disabled(!$quietHoursEnabled)></label>
                <label><span>Hasta</span><input type="time" wire:model="quietHoursEnd" @disabled(!$quietHoursEnabled)></label>
            </div>
        </section>
    </div>

    @if (count($this->eventOptions()))
        <fieldset class="profile-notification-events" @disabled(!$notificationsEnabled)>
            <legend>Tipos de avisos</legend>
            <p>Solo se muestran eventos que corresponden a tus funciones actuales.</p>
            @foreach ($this->eventOptions() as $eventKey => $event)
                <label>
                    <span class="profile-notification-events__icon"><i class="bx {{ $event[0] }}"></i></span>
                    <span><strong>{{ $event[1] }}</strong><small>{{ $event[2] }}</small></span>
                    <input type="checkbox" wire:model="eventPreferences.{{ $eventKey }}"><i aria-hidden="true"></i>
                </label>
            @endforeach
        </fieldset>
    @endif

    <div class="profile-notification-settings__actions">
        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save"><i class="bx bx-save me-1"></i>Guardar preferencias</span>
            <span wire:loading wire:target="save">Guardando…</span>
        </button>
        <span x-data="{ show: false }" x-on:profile-notifications-saved.window="show = true; setTimeout(() => show = false, 3000)" x-show="show" x-transition class="badge bg-label-success"><i class="bx bx-check me-1"></i>Preferencias guardadas</span>
    </div>
</form>
