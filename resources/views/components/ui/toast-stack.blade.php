@props(['duration' => 4400])

<div class="app-feedback-stack"
    x-data="{
        toasts: [],
        add(detail) {
            const toast = {
                id: `${Date.now()}-${Math.random()}`,
                type: ['success', 'error', 'warning', 'info'].includes(detail?.type) ? detail.type : 'info',
                title: detail?.title || '',
                message: detail?.message || ''
            };
            this.toasts.push(toast);
            setTimeout(() => this.remove(toast.id), {{ (int) $duration }});
        },
        remove(id) { this.toasts = this.toasts.filter((toast) => toast.id !== id); },
        icon(type) {
            return { success: 'bx-check-circle', error: 'bx-error-circle', warning: 'bx-error', info: 'bx-info-circle' }[type];
        }
    }"
    x-on:notify.window="add($event.detail)"
    aria-live="polite"
    aria-atomic="false">
    <template x-for="toast in toasts" :key="toast.id">
        <article class="app-feedback-toast" :class="`is-${toast.type}`" :role="toast.type === 'error' ? 'alert' : 'status'" x-transition>
            <span class="app-feedback-toast__icon" aria-hidden="true"><i class="bx" :class="icon(toast.type)"></i></span>
            <span class="app-feedback-toast__copy">
                <strong x-show="toast.title" x-text="toast.title"></strong>
                <span x-text="toast.message"></span>
            </span>
            <button type="button" x-on:click="remove(toast.id)" aria-label="Cerrar aviso"><i class="bx bx-x" aria-hidden="true"></i></button>
        </article>
    </template>
</div>
