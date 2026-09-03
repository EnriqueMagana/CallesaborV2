<div class="developer-console environment-settings" x-data="{ activeGroup: 'general' }">
    <header class="developer-hero environment-hero">
        <div class="developer-hero__content">
            <span class="developer-hero__icon"><i class="bx bx-slider-alt" aria-hidden="true"></i></span>
            <div>
                <span class="developer-eyebrow">DESARROLLO · CONFIGURACIÓN CONTROLADA</span>
                <h1>Variables de entorno</h1>
                <p>Administra parámetros permitidos sin exponer el contenido completo de <code>{{ $environmentFile }}</code>.</p>
            </div>
        </div>

        <div class="environment-hero__tools">
            <div class="environment-clock"
                x-data="{ now: new Date(), timer: null, zone: @js($clockTimezone) }"
                x-init="timer = setInterval(() => now = new Date(), 1000)"
                x-on:livewire:navigating.window="clearInterval(timer)">
                <span class="environment-clock__icon"><i class="bx bx-time-five" aria-hidden="true"></i></span>
                <div>
                    <small x-text="zone"></small>
                    <strong x-text="new Intl.DateTimeFormat('es-MX', { timeZone: zone, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).format(now)">--:--:--</strong>
                    <span x-text="new Intl.DateTimeFormat('es-MX', { timeZone: zone, weekday: 'short', day: '2-digit', month: 'short' }).format(now)"></span>
                </div>
            </div>
            <a href="{{ route('app.super-admin') }}" class="developer-button developer-button--secondary">
                <i class="bx bx-left-arrow-alt" aria-hidden="true"></i> Centro técnico
            </a>
        </div>
    </header>

    @if ($lastAction)
        <div class="environment-alert {{ $lastAction['ok'] ? 'is-success' : 'is-danger' }}" role="status">
            <i class="bx {{ $lastAction['ok'] ? 'bx-check-circle' : 'bx-error-circle' }}" aria-hidden="true"></i>
            <span>{{ $lastAction['message'] }}</span>
        </div>
    @endif

    @error('environment')
        <div class="environment-alert is-danger" role="alert"><i class="bx bx-error-circle" aria-hidden="true"></i><span>{{ $message }}</span></div>
    @enderror

    <div class="environment-security-note">
        <i class="bx bx-shield-quarter" aria-hidden="true"></i>
        <div>
            <strong>Edición protegida</strong>
            <p>APP_KEY y variables desconocidas nunca se muestran ni se pueden editar. Los secretos aparecen en blanco; dejarlos así conserva su valor actual.</p>
        </div>
        <span class="environment-write-state {{ $writable ? 'is-ready' : 'is-blocked' }}">
            <i class="bx {{ $writable ? 'bx-check' : 'bx-lock-alt' }}"></i>
            {{ $writable ? 'Archivo disponible' : 'Solo lectura' }}
        </span>
    </div>

    <form wire:submit.prevent="confirmSave" class="environment-workspace">
        <nav class="environment-tabs" aria-label="Grupos de configuración">
            @foreach ($definitions as $groupKey => $group)
                <button type="button" @click="activeGroup = '{{ $groupKey }}'"
                    :class="{ 'is-active': activeGroup === '{{ $groupKey }}' }"
                    :aria-selected="activeGroup === '{{ $groupKey }}'"
                    class="environment-tab">
                    <span><i class="bx {{ $group['icon'] }}" aria-hidden="true"></i></span>
                    <div><strong>{{ $group['label'] }}</strong><small>{{ count($group['fields']) }} variables</small></div>
                </button>
            @endforeach
        </nav>

        <section class="developer-panel environment-form-panel">
            @foreach ($definitions as $groupKey => $group)
                <div x-show="activeGroup === '{{ $groupKey }}'" x-cloak>
                    <header class="developer-panel__header">
                        <div>
                            <span class="developer-panel__icon"><i class="bx {{ $group['icon'] }}" aria-hidden="true"></i></span>
                            <div><h2>{{ $group['label'] }}</h2><p>{{ $group['description'] }}</p></div>
                        </div>
                    </header>

                    <div class="environment-fields">
                        @foreach ($group['fields'] as $key => $field)
                            <div class="environment-field {{ $field['type'] === 'secret' ? 'is-secret' : '' }}" wire:key="environment-field-{{ $key }}">
                                <div class="environment-field__label">
                                    <label for="environment-{{ $key }}">{{ $field['label'] }}</label>
                                    <code>{{ $key }}</code>
                                </div>
                                <p>{{ $field['description'] }}</p>

                                @if ($field['type'] === 'select')
                                    <select id="environment-{{ $key }}" wire:model.defer="values.{{ $key }}">
                                        <option value="">Usar valor predeterminado</option>
                                        @foreach ($field['options'] as $option)
                                            <option value="{{ $option }}">{{ $option }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($field['type'] === 'boolean')
                                    <select id="environment-{{ $key }}" wire:model.defer="values.{{ $key }}">
                                        <option value="">Usar valor predeterminado</option>
                                        <option value="true">Activado</option>
                                        <option value="false">Desactivado</option>
                                    </select>
                                @elseif ($field['type'] === 'timezone')
                                    <select id="environment-{{ $key }}" wire:model.defer="values.{{ $key }}">
                                        @foreach ($timezones as $timezone)
                                            <option value="{{ $timezone }}">{{ $timezone }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <div class="environment-input-wrap">
                                        <input id="environment-{{ $key }}"
                                            type="{{ $field['type'] === 'secret' ? 'password' : ($field['type'] === 'number' ? 'number' : ($field['type'] === 'email' ? 'email' : ($field['type'] === 'url' ? 'url' : 'text'))) }}"
                                            wire:model.defer="values.{{ $key }}"
                                            @if ($field['type'] === 'secret')
                                                autocomplete="new-password"
                                                placeholder="{{ ($secretConfigured[$key] ?? false) ? 'Configurado · escribe solo para reemplazar' : 'Sin configurar' }}"
                                            @endif>
                                        @if ($field['type'] === 'secret')
                                            <span class="environment-secret-state {{ ($secretConfigured[$key] ?? false) ? 'is-configured' : '' }}">
                                                <i class="bx {{ ($secretConfigured[$key] ?? false) ? 'bx-check-shield' : 'bx-key' }}"></i>
                                                {{ ($secretConfigured[$key] ?? false) ? 'Configurado' : 'Pendiente' }}
                                            </span>
                                        @endif
                                    </div>
                                @endif

                                @error('values.'.$key)<span class="environment-field__error">{{ $message }}</span>@enderror
                                @error($key)<span class="environment-field__error">{{ $message }}</span>@enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <footer class="environment-form-footer">
                <label class="environment-acknowledgement">
                    <input type="checkbox" wire:model="acknowledgeRisk">
                    <span>Revisé los valores y entiendo que una configuración incorrecta puede interrumpir el servicio.</span>
                </label>
                @error('acknowledgeRisk')<span class="environment-field__error">{{ $message }}</span>@enderror
                <button type="submit" class="developer-button developer-button--primary" wire:loading.attr="disabled" wire:target="confirmSave,save" @disabled(! $writable)>
                    <span wire:loading.remove wire:target="confirmSave,save"><i class="bx bx-save" aria-hidden="true"></i> Revisar y guardar</span>
                    <span wire:loading wire:target="confirmSave,save"><i class="bx bx-loader-alt bx-spin" aria-hidden="true"></i> Validando…</span>
                </button>
            </footer>
        </section>
    </form>

    <section class="developer-panel environment-audit">
        <header class="developer-panel__header">
            <div>
                <span class="developer-panel__icon"><i class="bx bx-history" aria-hidden="true"></i></span>
                <div><h2>Actividad reciente</h2><p>Registra quién cambió qué variables, nunca sus valores.</p></div>
            </div>
        </header>
        <div class="developer-table-wrap">
            <table class="developer-table">
                <thead><tr><th>Responsable</th><th>Variables</th><th>Fecha</th><th>Respaldo</th></tr></thead>
                <tbody>
                    @forelse ($recentAudits as $audit)
                        <tr>
                            <td><strong>{{ $audit->changedBy?->name ?? 'Usuario eliminado' }}</strong></td>
                            <td><div class="environment-key-list">@foreach ($audit->changed_keys as $key)<code>{{ $key }}</code>@endforeach</div></td>
                            <td>{{ $audit->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $audit->backup_file ? 'Creado' : 'No requerido' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="developer-table__empty">Todavía no hay cambios registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
