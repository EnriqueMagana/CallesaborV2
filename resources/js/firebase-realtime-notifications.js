import { getApp, getApps, initializeApp } from 'firebase/app';
import { getAuth, signInWithCustomToken } from 'firebase/auth';
import { getDatabase, onChildAdded, onValue, orderByChild, query, ref, startAt } from 'firebase/database';

const state = window.AppRealtimeNotifications ??= {
    starting: false,
    started: false,
    refreshPending: false,
    flushTimer: null,
    pendingEventKeys: new Set(),
    seenSignalIds: new Set(),
    unsubscribeNotification: null,
    unsubscribeConnection: null,
};

// Vite/HMR can preserve an older state object between module evaluations.
state.pendingEventKeys ??= new Set();
state.seenSignalIds ??= new Set();
state.flushTimer ??= null;

function emitStatus(status, detail = {}) {
    window.dispatchEvent(new CustomEvent('app-realtime-notification-status', {
        detail: { status, fallback: status !== 'connected', ...detail },
    }));
}

function dispatchRealtimeChanges(eventKeys) {
    const events = new Set(['notifications-check']);

    eventKeys.forEach(eventKey => {
        if (eventKey.startsWith('order.')) events.add('realtime-orders-changed');
        if (eventKey.startsWith('delivery.')) {
            events.add('realtime-orders-changed');
            events.add('realtime-delivery-changed');
        }
        if (eventKey.startsWith('table.')) events.add('realtime-tables-changed');
    });

    const posRoot = document.querySelector('[data-pos-root]');

    events.forEach(event => {
        if (posRoot && ['realtime-orders-changed', 'realtime-tables-changed'].includes(event)) {
            window.dispatchEvent(new CustomEvent('pos-realtime-refresh-requested', {
                detail: { event },
            }));
            return;
        }

        window.Livewire.dispatch(event);
    });
}

function flushRealtimeChanges() {
    state.flushTimer = null;

    if (!window.Livewire) {
        state.refreshPending = true;
        return;
    }

    state.refreshPending = false;
    const eventKeys = [...state.pendingEventKeys];
    state.pendingEventKeys.clear();
    dispatchRealtimeChanges(eventKeys);
}

function queueRealtimeChange(eventKey = null) {
    if (eventKey) state.pendingEventKeys.add(eventKey);
    if (state.flushTimer !== null) return;

    // One-shot debounce: a burst of Firebase child_added events results in one
    // batched Livewire update instead of one request per notification.
    state.flushTimer = window.setTimeout(flushRealtimeChanges, 150);
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
            if (state.seenSignalIds.has(snapshot.key)) return;
            state.seenSignalIds.add(snapshot.key);
            if (state.seenSignalIds.size > 2_000) {
                state.seenSignalIds.delete(state.seenSignalIds.values().next().value);
            }

            const createdAt = Number(snapshot.val()?.created_at_ms ?? 0);
            if (createdAt === 0 || createdAt >= listenerStartedAt - 60_000) {
                queueRealtimeChange(String(snapshot.val()?.event_key ?? ''));
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
        queueRealtimeChange();
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

    if (state.refreshPending) queueRealtimeChange();
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
