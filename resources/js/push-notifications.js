import { initializeApp } from 'firebase/app';
import { getMessaging, getToken, isSupported, onMessage } from 'firebase/messaging';

/**
 * Public Firebase Cloud Messaging subscription (Phase B2) — deliberately
 * isolated from public-live-match.js/Echo-Reverb and every admin/scoring
 * script; no login. A browser permission prompt is only ever triggered
 * by an explicit click on #fcm-subscribe-button (see initSubscribeButton())
 * — never on load/scroll/timeout. A returning visitor whose permission
 * is already 'granted' has their CURRENT token silently re-submitted to
 * the existing /notifications/subscribe backend on load, purely to keep
 * RPPL's record fresh — see maybeRefreshExistingSubscription(); that is a
 * re-use of an already-granted permission, never a new prompt.
 *
 * Only the PUBLIC Firebase Web config lives here — never a secret, and
 * never the private service-account credential (that never appears in
 * any browser-reachable file). getToken()/service worker registration
 * require a secure context: HTTPS in production, `localhost` is fine for
 * local development.
 */

const SUBSCRIBE_URL = '/notifications/subscribe';
const SERVICE_WORKER_URL = '/firebase-messaging-sw.js';

const firebaseConfig = {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
    authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
    storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
    appId: import.meta.env.VITE_FIREBASE_APP_ID,
};

const vapidKey = import.meta.env.VITE_FIREBASE_VAPID_KEY;

const LABELS = {
    default: '🔔 Enable Notifications',
    working: 'Enabling…',
    enabled: '🔔 Notifications On',
    denied: 'Notifications Blocked',
};

function hasCompleteConfig() {
    return Boolean(
        firebaseConfig.apiKey
            && firebaseConfig.authDomain
            && firebaseConfig.projectId
            && firebaseConfig.messagingSenderId
            && firebaseConfig.appId
            && vapidKey,
    );
}

function setState(button, state) {
    button.textContent = LABELS[state] ?? LABELS.default;
    button.disabled = state === 'working' || state === 'denied';
    button.dataset.state = state;
}

/**
 * The only place a token is ever sent anywhere — axios already carries
 * Laravel's XSRF cookie/header automatically for a same-origin POST, so
 * this needs no extra CSRF wiring. Never logs the token, the response,
 * or a raw SDK error; a failure here must never break public navigation.
 */
async function submitToken(token) {
    await window.axios.post(SUBSCRIBE_URL, { token });
}

/**
 * Runs on every page load. 'denied' shows the blocked state without ever
 * calling requestPermission() again. 'granted' silently re-fetches and
 * re-submits the CURRENT token (a no-op from the visitor's point of view
 * — no prompt, no visible change beyond the button reflecting "on").
 * 'default' leaves the button in its normal, clickable state.
 */
async function maybeRefreshExistingSubscription(button, messaging, registration) {
    if (Notification.permission === 'denied') {
        setState(button, 'denied');

        return;
    }

    if (Notification.permission !== 'granted') {
        return;
    }

    try {
        const token = await getToken(messaging, { vapidKey, serviceWorkerRegistration: registration });

        if (!token) {
            return;
        }

        await submitToken(token);
        setState(button, 'enabled');
    } catch {
        // A returning visitor's silent refresh failing is not worth
        // surfacing — the button stays in its default state and a
        // future click can retry the whole flow.
    }
}

function initSubscribeButton(button, messaging, registration) {
    button.addEventListener('click', async () => {
        setState(button, 'working');

        try {
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                setState(button, permission === 'denied' ? 'denied' : 'default');

                return;
            }

            const token = await getToken(messaging, { vapidKey, serviceWorkerRegistration: registration });

            if (!token) {
                setState(button, 'default');

                return;
            }

            await submitToken(token);
            setState(button, 'enabled');
        } catch {
            setState(button, 'default');
        }
    });
}

/**
 * Foreground-only — onMessage() never fires for a background push (the
 * service worker's onBackgroundMessage() handles that; see
 * firebase-messaging-sw.js). Native Notification API only, no toast
 * library. This is preparation for a later phase: no real payload can
 * arrive until server-side sending exists.
 */
function initForegroundMessages(messaging) {
    onMessage(messaging, (payload) => {
        if (Notification.permission !== 'granted') {
            return;
        }

        const title = payload?.notification?.title ?? 'RPPL';
        const body = payload?.notification?.body ?? '';

        const notification = new Notification(title, { body });

        notification.onclick = () => {
            window.focus();
            notification.close();
        };
    });
}

document.addEventListener('DOMContentLoaded', async () => {
    const button = document.getElementById('fcm-subscribe-button');

    if (!button) {
        return;
    }

    if (!('Notification' in window) || !('serviceWorker' in navigator) || !hasCompleteConfig()) {
        if (import.meta.env.DEV && !hasCompleteConfig()) {
            // Public Firebase Web config only — never a secret — safe to
            // note in the dev console so a developer knows why the
            // control stays hidden.
            console.info('Push notifications disabled: Firebase Web config is incomplete.');
        }

        return;
    }

    try {
        const supported = await isSupported();

        if (!supported) {
            return;
        }

        const registration = await navigator.serviceWorker.register(SERVICE_WORKER_URL);
        const app = initializeApp(firebaseConfig);
        const messaging = getMessaging(app);

        button.classList.remove('hidden');

        initSubscribeButton(button, messaging, registration);
        initForegroundMessages(messaging);
        await maybeRefreshExistingSubscription(button, messaging, registration);
    } catch {
        // Any Firebase/service-worker initialization failure must never
        // break public navigation — the control simply stays hidden.
    }
});
