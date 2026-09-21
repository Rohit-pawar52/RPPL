import axios from 'axios';
import { initializeApp } from 'firebase/app';
import { getMessaging, getToken, isSupported, onMessage } from 'firebase/messaging';
import Swal from 'sweetalert2';

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
 * The only place a token is ever sent anywhere. Imports axios directly
 * (rather than relying on window.axios, which resources/js/bootstrap.js
 * only sets up for pages that load app.js — the public layout does not,
 * it loads only this module) — axios already carries Laravel's XSRF
 * cookie/header automatically for a same-origin POST regardless of
 * which instance is used, so this needs no extra CSRF wiring. Never
 * logs the token, the response, or a raw SDK error; a failure here must
 * never break public navigation.
 */
async function submitToken(token) {
    await axios.post(SUBSCRIBE_URL, { token });
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
    } catch (error) {
        // A returning visitor's silent refresh failing is not worth
        // surfacing in the UI — the button stays in its default state
        // and a future click can retry the whole flow. Still logged to
        // the console (never the token/credentials, just the SDK's own
        // error) so a developer can diagnose without this ever
        // interrupting a real visitor.
        console.error('Push notification subscription refresh failed:', error);
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
        } catch (error) {
            // Never the token/credentials — just the SDK's own error,
            // logged so a developer can diagnose a failed click without
            // this ever showing a scary message to a real visitor.
            console.error('Enable Notifications failed:', error);
            setState(button, 'default');
        }
    });
}

/**
 * The reliable in-page confirmation, shown only while the tab is
 * actually visible — shared by BOTH of Firebase's own routing paths
 * (onMessage below, and the service worker's relay further down), since
 * in local testing Firebase inconsistently chose one or the other across
 * otherwise-identical sends. The OS-level notification (shown
 * separately, by the service worker) remains the primary channel and is
 * unaffected either way; this is purely additive, for the case where the
 * visitor already has RPPL open when it arrives, so they always see
 * something regardless of Windows/Chrome notification settings. No
 * click-to-navigate here — that would need its own action_url
 * sanitization copy, and isn't worth it unless actually wanted.
 */
function showInPageConfirmation(title, body) {
    if (document.visibilityState !== 'visible') {
        return;
    }

    Swal.fire({
        icon: 'info',
        title,
        text: body,
        toast: true,
        position: 'top-end',
        timer: 6000,
        timerProgressBar: true,
        showConfirmButton: false,
    });
}

/**
 * Foreground-only — onMessage() never fires for a background push (the
 * service worker's onBackgroundMessage() handles that; see
 * firebase-messaging-sw.js). Native Notification API AND the shared
 * in-page SweetAlert — Firebase's foreground/background routing proved
 * inconsistent in local testing, so both display paths are wired to
 * BOTH of Firebase's routing outcomes rather than assuming only one
 * will ever fire.
 */
function initForegroundMessages(messaging) {
    onMessage(messaging, (payload) => {
        const title = payload?.notification?.title ?? 'RPPL';
        const body = payload?.notification?.body ?? '';

        showInPageConfirmation(title, body);

        if (Notification.permission !== 'granted') {
            return;
        }

        const notification = new Notification(title, { body });

        notification.onclick = () => {
            window.focus();
            notification.close();
        };
    });
}

/**
 * The service worker relays EVERY push it receives here (see
 * firebase-messaging-sw.js) regardless of Firebase's own
 * onMessage/onBackgroundMessage routing choice — see
 * showInPageConfirmation() above for why both paths are covered.
 */
function initServiceWorkerMessageRelay() {
    navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data?.type !== 'RPPL_PUSH_RECEIVED') {
            return;
        }

        showInPageConfirmation(event.data.title, event.data.body);
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
        initServiceWorkerMessageRelay();
        await maybeRefreshExistingSubscription(button, messaging, registration);
    } catch (error) {
        // Any Firebase/service-worker initialization failure must never
        // break public navigation — the control simply stays hidden.
        // Logged (never the token/credentials) so it's diagnosable.
        console.error('Push notification initialization failed:', error);
    }
});
