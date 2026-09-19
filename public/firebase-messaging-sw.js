/**
 * Firebase Cloud Messaging background service worker (Phase B2).
 *
 * Uses the COMPAT SDK loaded via importScripts() rather than the modular
 * npm package the rest of the app bundle uses (see
 * resources/js/push-notifications.js) — a classic (non-module) service
 * worker, registered with no special options, cannot `import` an ES
 * module or the npm package graph. This is Firebase's own long-
 * documented pattern for firebase-messaging-sw.js and works reliably
 * across every browser that supports FCM web push, rather than
 * depending on newer, less consistently supported module-worker
 * registration. Correctness here matters more than syntax consistency
 * with the rest of the app bundle — see the Phase B2 report.
 *
 * Firebase Web configuration is PUBLIC data — never a secret, and never
 * the private service-account credential (which never appears in any
 * browser-reachable file). It is hardcoded below because a plain static
 * file under public/ cannot read Vite/.env values at request time. Keep
 * these values in sync with the VITE_FIREBASE_* values in .env whenever
 * the Firebase project's web config changes, and keep the SDK version
 * below in sync with the "firebase" version in package.json.
 */
importScripts('https://www.gstatic.com/firebasejs/12.19.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/12.19.0/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: 'REPLACE_WITH_VITE_FIREBASE_API_KEY',
    authDomain: 'REPLACE_WITH_VITE_FIREBASE_AUTH_DOMAIN',
    projectId: 'REPLACE_WITH_VITE_FIREBASE_PROJECT_ID',
    storageBucket: 'REPLACE_WITH_VITE_FIREBASE_STORAGE_BUCKET',
    messagingSenderId: 'REPLACE_WITH_VITE_FIREBASE_MESSAGING_SENDER_ID',
    appId: 'REPLACE_WITH_VITE_FIREBASE_APP_ID',
});

const messaging = firebase.messaging();

const DEFAULT_ACTION_URL = '/';

/**
 * Duplicated from resources/js/push-notifications.js rather than shared
 * — this file loads as a classic script (importScripts, no ES module
 * import) and cannot import from the app bundle. Accepts only an
 * internal relative path beginning with exactly one "/": rejects an
 * absolute URL of any scheme (http/https/javascript/data — none of
 * which start with "/"), a protocol-relative "//host" path, and any
 * whitespace-containing value. Never trusts the push payload blindly.
 */
function sanitizeActionUrl(candidate) {
    if (typeof candidate !== 'string' || candidate.length === 0) {
        return DEFAULT_ACTION_URL;
    }

    if (candidate.charAt(0) !== '/' || candidate.charAt(1) === '/' || /\s/.test(candidate)) {
        return DEFAULT_ACTION_URL;
    }

    return candidate;
}

/**
 * Background-only (the tab is not focused/not open — see onMessage() in
 * push-notifications.js for the foreground case). The expected future
 * server payload shape — notification.title/body + data.action_url — is
 * NOT sent by anything yet; server-side sending is a later phase (see
 * the Phase B2 report).
 */
messaging.onBackgroundMessage((payload) => {
    const title = payload?.notification?.title ?? 'RPPL';
    const body = payload?.notification?.body ?? '';
    const actionUrl = sanitizeActionUrl(payload?.data?.action_url);

    self.registration.showNotification(title, {
        body,
        icon: '/favicon.ico',
        data: { actionUrl },
    });
});

/**
 * Focuses an already-open same-origin RPPL tab and navigates it to the
 * notification's (already-sanitized) action URL, or opens a new tab if
 * none is open. Never navigates to an external origin — every URL used
 * here is built from the sanitized internal path above, resolved
 * against this service worker's own origin.
 */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const actionUrl = event.notification.data?.actionUrl ?? DEFAULT_ACTION_URL;
    const targetUrl = new URL(actionUrl, self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const alreadyOpen = clients.find((client) => client.url === targetUrl);

            if (alreadyOpen) {
                return alreadyOpen.focus();
            }

            const sameOrigin = clients.find((client) => new URL(client.url).origin === self.location.origin);

            if (sameOrigin) {
                return sameOrigin.focus().then((focused) => focused.navigate(targetUrl));
            }

            return self.clients.openWindow(targetUrl);
        }),
    );
});
