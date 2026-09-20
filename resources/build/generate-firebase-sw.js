// Generates public/firebase-messaging-sw.js from
// resources/js/firebase-messaging-sw.template.js, substituting the
// __VITE_FIREBASE_*__ placeholders with the SAME .env values Vite bakes
// into the app bundle via import.meta.env — the one authoritative
// source for RPPL's public Firebase Web config (Phase B5). Run
// automatically by `npm run build`/`npm run dev` (see package.json);
// never run manually as part of a request — this is a build-time step
// only.
//
// The generated file is gitignored (see .gitignore) so a stale or
// placeholder value can never be committed by accident — it is always
// freshly written from the current .env before the app is served.
//
// These are all PUBLIC Firebase Web config values (never a secret, and
// never the private FIREBASE_CREDENTIALS service-account path used
// server-side — see FcmMessagingService). Missing values are written
// through as empty strings; push-notifications.js/the service worker
// already handle an incomplete config by leaving the feature disabled,
// so this script does not need to fail the build over it.
import { loadEnv } from 'vite';
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const rootDir = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '../..');
const templatePath = path.join(rootDir, 'resources/js/firebase-messaging-sw.template.js');
const outputPath = path.join(rootDir, 'public/firebase-messaging-sw.js');

const env = loadEnv(process.env.NODE_ENV ?? 'development', rootDir, 'VITE_FIREBASE_');

const PLACEHOLDERS = [
    'VITE_FIREBASE_API_KEY',
    'VITE_FIREBASE_AUTH_DOMAIN',
    'VITE_FIREBASE_PROJECT_ID',
    'VITE_FIREBASE_STORAGE_BUCKET',
    'VITE_FIREBASE_MESSAGING_SENDER_ID',
    'VITE_FIREBASE_APP_ID',
];

let contents = readFileSync(templatePath, 'utf8');

for (const key of PLACEHOLDERS) {
    contents = contents.replaceAll(`__${key}__`, env[key] ?? '');
}

writeFileSync(outputPath, contents);

// eslint-disable-next-line no-console
console.log('Generated public/firebase-messaging-sw.js from .env.');
