import { getApp, getApps, initializeApp } from 'firebase/app';
import { getAuth, signInWithCustomToken } from 'firebase/auth';
import { getDatabase, onChildAdded, onValue, orderByChild, query, ref, startAt } from 'firebase/database';

const state = window.AppRealtimeNotifications ??= {
    starting: false,
    started: false,
    unsubscribeNotification: null,
    unsubscribeConnection: null,
};

function emitStatus(status, detail = {}) {
    window.dispatchEvent(new CustomEvent('app-realtime-notification-status', {
        detail: { status, fallback: status !== 'connected', ...detail },
    }));
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
        const signals = query(
            ref(database, session.path),
            orderByChild('created_at_ms'),
            startAt(Date.now() - 10_000),
        );

        state.unsubscribeNotification = onChildAdded(signals, () => {
            window.Livewire?.dispatch('notifications-check');
        }, () => emitStatus('fallback', { reason: 'listener_error' }));

        state.unsubscribeConnection = onValue(ref(database, '.info/connected'), snapshot => {
            emitStatus(snapshot.val() === true ? 'connected' : 'fallback', {
                reason: snapshot.val() === true ? null : 'disconnected',
            });
        });

        state.started = true;
    } catch (error) {
        emitStatus('fallback', { reason: 'initialization_error' });
        console.warn('Firebase Realtime Database no está disponible; Livewire continúa activo.', error);
    } finally {
        state.starting = false;
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startRealtimeNotifications, { once: true });
} else {
    startRealtimeNotifications();
}

document.addEventListener('livewire:navigated', startRealtimeNotifications);
