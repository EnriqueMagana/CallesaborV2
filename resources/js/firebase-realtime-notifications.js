import { getApp, getApps, initializeApp } from 'firebase/app';
import { getAuth, signInWithCustomToken } from 'firebase/auth';
import { getDatabase, onChildAdded, onValue, orderByChild, query, ref, startAt } from 'firebase/database';

const state = window.AppRealtimeNotifications ??= {
    starting: false,
    started: false,
    refreshPending: false,
    unsubscribeNotification: null,
    unsubscribeConnection: null,
};

function emitStatus(status, detail = {}) {
    window.dispatchEvent(new CustomEvent('app-realtime-notification-status', {
        detail: { status, fallback: status !== 'connected', ...detail },
    }));
}

function refreshNotificationCenter() {
    if (!window.Livewire) {
        state.refreshPending = true;
        return;
    }

    state.refreshPending = false;
    window.Livewire.dispatch('notifications-check');
}

function stopRealtimeNotifications() {
    state.unsubscribeNotification?.();
    state.unsubscribeConnection?.();
    state.unsubscribeNotification = null;
    state.unsubscribeConnection = null;
    state.started = false;
}

async function startRealtimeNotifications() {
    const endpoint = document.querySelector('meta[name="realtime-notification-session"]')?.content;
    if (!endpoint || state.starting || state.started) return;

    state.starting = true;

    try {
        const response = await fetch(endpoint, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });
        if (!response.ok) throw new Error(`Realtime session HTTP ${response.status}`);

        const session = await response.json();
        if (!session.enabled) {
            emitStatus('fallback', { reason: 'disabled_or_unavailable' });
            return;
        }

        const firebaseApp = getApps().length ? getApp() : initializeApp(session.config);
        await signInWithCustomToken(getAuth(firebaseApp), session.token);

        const database = getDatabase(firebaseApp, session.config.databaseURL);
        const listenerStartedAt = Date.now();
        const signals = query(
            ref(database, session.path),
            orderByChild('created_at_ms'),
            startAt(listenerStartedAt - 60_000),
        );

        state.unsubscribeNotification = onChildAdded(signals, snapshot => {
            const createdAt = Number(snapshot.val()?.created_at_ms ?? 0);
            if (createdAt === 0 || createdAt >= listenerStartedAt - 60_000) {
                refreshNotificationCenter();
            }
        }, error => {
            stopRealtimeNotifications();
            emitStatus('fallback', { reason: 'listener_error', code: error?.code ?? null });
        });

        state.unsubscribeConnection = onValue(ref(database, '.info/connected'), snapshot => {
            emitStatus(snapshot.val() === true ? 'connected' : 'fallback', {
                reason: snapshot.val() === true ? null : 'disconnected',
            });
        });

        state.started = true;
        refreshNotificationCenter();
    } catch (error) {
        stopRealtimeNotifications();
        emitStatus('fallback', { reason: 'initialization_error' });
        console.warn('Firebase Realtime Database no está disponible; Livewire continúa activo.', error);
    } finally {
        state.starting = false;
    }
}

function resumeRealtimeNotifications() {
    if (document.visibilityState === 'hidden') return;

    if (state.refreshPending) refreshNotificationCenter();
    startRealtimeNotifications();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startRealtimeNotifications, { once: true });
} else {
    startRealtimeNotifications();
}

document.addEventListener('livewire:init', resumeRealtimeNotifications);
document.addEventListener('livewire:initialized', resumeRealtimeNotifications);
document.addEventListener('livewire:navigated', resumeRealtimeNotifications);
document.addEventListener('visibilitychange', resumeRealtimeNotifications);
window.addEventListener('online', resumeRealtimeNotifications);
