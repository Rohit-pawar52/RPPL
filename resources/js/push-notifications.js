import axios from 'axios';
import { initializeApp } from 'firebase/app';
import { getMessaging, getToken, isSupported, onMessage } from 'firebase/messaging';
import Swal from 'sweetalert2';

/**
 * Public Firebase Cloud Messaging subscription (Phase B2), with a
 * soft-prompt opt-in UX on top (Phase 3.47) — deliberately isolated
 * from public-live-match.js/Echo-Reverb and every admin/scoring
 * script; no login.
 *
 * The browser-native permission popup is NEVER triggered automatically
 * on load/scroll/timeout. It is only ever triggered by an explicit
 * user action — clicking the header bell (#fcm-subscribe-button), or
 * clicking "Enable Notifications" on the RPPL-controlled soft prompt
 * (#rppl-push-soft-prompt) — and only when Notification.permission is
 * currently 'default'. The soft prompt itself is RPPL-controlled UI
 * that may appear automatically (after a short delay, subject to the
 * eligibility rules in maybeScheduleSoftPrompt()); it never IS the
 * native prompt.
 *
 * A returning visitor whose permission is already 'granted' has their
 * CURRENT token silently re-submitted to the existing
 * /notifications/subscribe backend on load, purely to keep RPPL's
 * record fresh — see maybeRefreshExistingSubscription(); that reuses
 * an already-granted permission, never a new prompt.
 *
 * Only the PUBLIC Firebase Web config lives here — never a secret, and
 * never the private service-account credential (that never appears in
 * any browser-reachable file). getToken()/service worker registration
 * require a secure context: HTTPS in production, `localhost` is fine for
 * local development.
 */

const SUBSCRIBE_URL = '/notifications/subscribe';
const SERVICE_WORKER_URL = '/firebase-messaging-sw.js';

// The soft prompt appears this long after the page is ready — long
// enough that a visitor has actually seen the site first, short enough
// to still catch someone browsing quickly. Centralized here rather
// than repeated at each call site.
const SOFT_PROMPT_DELAY_MS = 2000;

// How long an explicit "Not Now" suppresses the AUTOMATIC soft prompt.
// Never affects a manual bell click — see initBellButton().
const COOLDOWN_DURATION_MS = 7 * 24 * 60 * 60 * 1000;
const COOLDOWN_STORAGE_KEY = 'rppl_push_prompt_next_at';

// Optional same-session anti-repeat (Phase 3.47 §14) — keeps the
// automatic prompt from reappearing on every reload within one browser
// session even before a 7-day cooldown would otherwise apply.
const SESSION_SHOWN_KEY = 'rppl_push_prompt_shown_session';

const firebaseConfig = {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
    authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
    storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
    appId: import.meta.env.VITE_FIREBASE_APP_ID,
};

const vapidKey = import.meta.env.VITE_FIREBASE_VAPID_KEY;

const BELL_LABELS = {
    default: 'Enable notifications',
    working: 'Enabling notifications…',
    enabled: 'Notifications enabled',
    denied: 'Notifications blocked — click for help',
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

/**
 * localStorage/sessionStorage can throw (private browsing, blocked
 * storage, disabled cookies) — every access goes through these small
 * wrappers so a storage failure only ever means "the soft prompt may
 * reappear more than intended," never a broken page. Nothing sensitive
 * is ever stored here — only a dismissal timestamp and a same-session
 * boolean flag (see the module docblock and Phase 3.47 §20/§7).
 */
function readStorage(storage, key) {
    try {
        return storage.getItem(key);
    } catch {
        return null;
    }
}

function writeStorage(storage, key, value) {
    try {
        storage.setItem(key, value);
    } catch {
        // Ignored — see the docblock above.
    }
}

function removeStorage(storage, key) {
    try {
        storage.removeItem(key);
    } catch {
        // Ignored — see the docblock above.
    }
}

function isWithinCooldown() {
    const raw = readStorage(window.localStorage, COOLDOWN_STORAGE_KEY);

    if (!raw) {
        return false;
    }

    const nextAt = Number(raw);

    return Number.isFinite(nextAt) && Date.now() < nextAt;
}

function startCooldown() {
    writeStorage(window.localStorage, COOLDOWN_STORAGE_KEY, String(Date.now() + COOLDOWN_DURATION_MS));
}

/**
 * Called only after a successful Enable — a stale "come back later"
 * cooldown from an earlier "Not Now" no longer means anything once the
 * visitor has actually enabled notifications (Phase 3.47 §12).
 */
function clearCooldown() {
    removeStorage(window.localStorage, COOLDOWN_STORAGE_KEY);
}

function setBellState(button, indicator, state) {
    button.dataset.state = state;
    button.disabled = state === 'working';

    const label = BELL_LABELS[state] ?? BELL_LABELS.default;
    button.setAttribute('aria-label', label);
    button.title = label;

    if (indicator) {
        indicator.dataset.state = state;
    }
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
 * Runs on every page load. 'denied' reflects the blocked state on the
 * bell without ever calling requestPermission() again. 'granted'
 * silently re-fetches and re-submits the CURRENT token (a no-op from
 * the visitor's point of view — no prompt, the server's own upsert
 * behavior prevents duplicate rows). 'default' leaves the bell in its
 * normal, clickable state.
 */
async function maybeRefreshExistingSubscription(button, indicator, messaging, registration) {
    if (Notification.permission === 'denied') {
        setBellState(button, indicator, 'denied');

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
        setBellState(button, indicator, 'enabled');
    } catch (error) {
        // A returning visitor's silent refresh failing is not worth
        // surfacing in the UI — the bell stays in its default state
        // and a future click can retry the whole flow. Still logged to
        // the console (never the token/credentials, just the SDK's own
        // error) so a developer can diagnose without this ever
        // interrupting a real visitor.
        console.error('Push notification subscription refresh failed:', error);
    }
}

/**
 * The one place that actually calls Notification.requestPermission() —
 * shared by the bell's own click handler and the soft prompt's Enable
 * button, so there is exactly one enable flow, never two competing
 * implementations (Phase 3.47 §26).
 */
async function enableNotifications(button, indicator, messaging, registration) {
    setBellState(button, indicator, 'working');

    try {
        const permission = await Notification.requestPermission();

        if (permission !== 'granted') {
            setBellState(button, indicator, permission === 'denied' ? 'denied' : 'default');

            return;
        }

        const token = await getToken(messaging, { vapidKey, serviceWorkerRegistration: registration });

        if (!token) {
            setBellState(button, indicator, 'default');

            return;
        }

        await submitToken(token);
        setBellState(button, indicator, 'enabled');
        clearCooldown();
    } catch (error) {
        // Never the token/credentials — just the SDK's own error,
        // logged so a developer can diagnose a failed click without
        // this ever showing a scary message to a real visitor.
        console.error('Enable Notifications failed:', error);
        setBellState(button, indicator, 'default');
    }
}

/**
 * A browser can never be forced to re-show its native permission
 * prompt once a site has been blocked — the only real remedy is the
 * visitor's own browser site-settings. This is RPPL-controlled UI
 * explaining that, never another attempt at requestPermission().
 */
function showBlockedHelp() {
    Swal.fire({
        icon: 'info',
        title: 'Notifications Blocked',
        text: 'Notifications are blocked in your browser. Please allow notifications for this site from your browser’s site settings.',
        confirmButtonText: 'Got it',
    });
}

function hideSoftPrompt(prompt) {
    prompt?.classList.add('hidden');
}

/**
 * The bell always reflects Notification.permission — the browser's own
 * state is authoritative, never cached (Phase 3.47 §10). A denied
 * click explains the block instead of prompting again; a granted click
 * is a no-op (already enabled); only a default click proceeds to the
 * shared enable flow, intentionally bypassing the 7-day "Not Now"
 * cooldown — that cooldown only ever suppresses the AUTOMATIC prompt
 * (Phase 3.47 §8).
 */
function initBellButton(button, indicator, prompt, messaging, registration) {
    button.addEventListener('click', () => {
        if (Notification.permission === 'denied') {
            showBlockedHelp();

            return;
        }

        if (Notification.permission === 'granted') {
            return;
        }

        hideSoftPrompt(prompt);
        enableNotifications(button, indicator, messaging, registration);
    });
}

/**
 * Wires the soft prompt's own two actions, if the prompt markup is
 * present on this page (layouts.public only — never admin/guest/
 * maintenance). Returns the prompt element (or null) so the caller can
 * decide whether/when to reveal it.
 */
function initSoftPrompt(button, indicator, messaging, registration) {
    const prompt = document.getElementById('rppl-push-soft-prompt');

    if (!prompt) {
        return null;
    }

    document.getElementById('rppl-push-soft-prompt-enable')?.addEventListener('click', () => {
        hideSoftPrompt(prompt);
        enableNotifications(button, indicator, messaging, registration);
    });

    document.getElementById('rppl-push-soft-prompt-dismiss')?.addEventListener('click', () => {
        hideSoftPrompt(prompt);
        startCooldown();
    });

    return prompt;
}

/**
 * Decides whether the AUTOMATIC soft prompt should appear at all, and
 * if so, reveals it after SOFT_PROMPT_DELAY_MS. Eligibility (Phase
 * 3.47 §2/§6/§7/§14): permission must currently be 'default' (never
 * 'granted' or 'denied'), no active 7-day cooldown from an earlier
 * "Not Now", and not already shown once this browser session. Permission
 * is re-checked again right before revealing, in case the visitor used
 * the bell directly during the delay.
 */
function maybeScheduleSoftPrompt(prompt) {
    if (!prompt) {
        return;
    }

    if (Notification.permission !== 'default') {
        return;
    }

    if (isWithinCooldown()) {
        return;
    }

    if (readStorage(window.sessionStorage, SESSION_SHOWN_KEY)) {
        return;
    }

    window.setTimeout(() => {
        if (Notification.permission !== 'default') {
            return;
        }

        writeStorage(window.sessionStorage, SESSION_SHOWN_KEY, '1');
        prompt.classList.remove('hidden');
    }, SOFT_PROMPT_DELAY_MS);
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

    const indicator = document.getElementById('fcm-subscribe-indicator');

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

        const softPrompt = initSoftPrompt(button, indicator, messaging, registration);
        initBellButton(button, indicator, softPrompt, messaging, registration);
        initForegroundMessages(messaging);
        initServiceWorkerMessageRelay();
        await maybeRefreshExistingSubscription(button, indicator, messaging, registration);
        maybeScheduleSoftPrompt(softPrompt);
    } catch (error) {
        // Any Firebase/service-worker initialization failure must never
        // break public navigation — the control simply stays hidden.
        // Logged (never the token/credentials) so it's diagnosable.
        console.error('Push notification initialization failed:', error);
    }
});
