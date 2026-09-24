# RPPL Tournament Management System

A Laravel-based admin panel and public tournament website for RPPL (RajaBhoj Pawar Premier League) — a local cricket tournament. Covers everything from setting up an edition, teams, and players, through ball-by-ball live scoring, to standings/statistics, committee/contributor fundraising with receipts, and a public "Google-Forms-replacement" player registration flow.

This README is meant to be comprehensive enough that reading it alone tells you what the project is and what it can currently do. **Whenever a new feature is built, add it here** — see [`memory.md`](memory.md) for the standing instructions this project follows.

## Contents

- [Features](#features)
- [Architecture conventions](#architecture-conventions)
- [Tech stack](#tech-stack)
- [Getting started](#getting-started)
- [Demo data](#demo-data)
- [Testing](#testing)
- [Branching & workflow](#branching--workflow)
- [Continuous Integration](#continuous-integration)

## Features

### Roles & authorization

- Two admin-side roles: **admin** (full tournament management) and **scorer** (match-day scoring only — no editions/teams/players/finance/registration/user management).
- Every admin resource is guarded by its own Policy (`EditionPolicy`, `TeamPolicy`, `PlayerPolicy`, `GameMatchPolicy`, `MatchPlayerPolicy`, `TeamPlayerPolicy`, `VenuePolicy`, `PlayerRegistrationPolicy`, `EditionTransactionPolicy`, `CommitteeMemberPolicy`, `ContributorPolicy`, `EditionContributionPolicy`, `UserPolicy`), not just route middleware.
- User accounts (admin/scorer) support an approval workflow and block/unblock — no self-registration for admin accounts. Accounts are deactivated, never deleted, so historical "recorded by" attribution never breaks.
- Public website and public registration/status-lookup require no login at all.

### Editions, teams, venues, players

- **Editions** — a tournament season (name, year, status: upcoming/active/completed, public registration toggle + fee), with a PDF edition report.
- **Teams** and **Venues** — reusable master data (with team logo / player photo uploads on the public disk), active/inactive without deleting historical usage.
- **Players** — master player directory (name, phone, email, DOB, primary role, batting/bowling style, photo), independent of any one edition.
- **Edition Teams** — which teams participate in a given edition (create/view/delete only — no free-form editing of a participation record's own identity).
- **Squads (Team Players)** — assigning a player's edition registration to a specific team/edition, with jersey numbers.

### Match lifecycle & ball-by-ball scoring

- Full match lifecycle as explicit actions (never a generic status field the client can drive arbitrarily): schedule a match → set the playing XI → start toss → record toss → start match → play innings → finalize result — plus **cancel**/**abandon** at any point.
- Two-innings model with explicit innings start/complete actions.
- Ball-by-ball delivery entry (runs, extras — wide/no-ball/bye/leg-bye, wickets with dismissal type/fielder) with **undo latest delivery** as the only correction path (scoped to the current innings).
- Match result is always server-derived from completed innings totals — never chosen by the admin/scorer.
- Read-only match scorecard (admin and public), plus a **downloadable PDF scorecard**.
- **Real-time live scoring** on the public match page via Laravel Reverb (WebSocket broadcasting) + Laravel Echo — a `MatchScoreUpdated` event fires after each committed scoring action and the public "live" view updates without a page refresh; falls back to polling (`live-data`) if a socket connection isn't available.

### Standings & statistics

- Edition standings table (points/wins/losses/ties/no-results), completed-matches-only, isolated per edition.
- Edition-level statistics: leaderboards and tournament records (highest individual score, best bowling figures, most sixes, etc.), computed from completed matches only.

### Finance — one consolidated admin area

Phase 3.48 collapsed what used to be three separate concepts (Finance ledger, Committee members, General "Chanda" contributors) into one **Finance** sidebar entry with five tabs — Overview, Contributions, Ledger, Contributors, Committee — sharing the same tab bar (`resources/views/admin/finance/_tabs.blade.php`), even though each tab keeps its own URL/controller.

- **One Contributor identity per person.** `contributors` is the single master list of everyone who ever contributes money — being on a committee is **not** a separate person identity, it's an edition-specific *membership* (`edition_committee_members`: `edition_id` + `contributor_id`, unique per pair). Ask "is this contributor a committee member of this edition?" via `Contributor::isCommitteeMemberOf($edition)` — never by which historical field happened to be populated on a row.
- **Committee tab** — add/remove a Contributor from the *selected edition's* committee (removal is blocked if that contributor already has recorded contribution history for that edition, to keep history consistent), plus a one-click **"Copy Previous Edition Committee"** (idempotent — copies membership only, never contributions/payment history, from the chronologically preceding edition by year).
- **Committee dues** — a single **global** Settings value, `finance.committee_minimum_contribution` (default ₹1000, in the Settings "System" tab), is the **target total** a committee member is expected to reach for an edition — reachable across any number of separate contributions (installments), never a minimum enforced on one payment. Status per member is computed fresh on every read: Not Paid / Partially Paid / Paid in Full (overpayment is never an error). Because the target is one global value rather than a per-edition historical snapshot, **changing it changes the displayed target for every edition**, including past ones, the next time dues are viewed — already-recorded contribution amounts are never touched by this.
- **Contributions tab** — one contribution form for every contributor (no more separate committee/general forms); selecting a current committee member shows a live "Committee Member" badge with Target/Paid So Far/Remaining. Any contribution automatically creates a matching **Edition Transaction** (income) in the finance ledger; deleting a contribution atomically removes that transaction. Contributions are never edited after the fact — a mistaken entry is deleted and re-recorded.
- **Ledger tab** — the general income/expense ledger per edition; a transaction created from a contribution is clearly labeled "Created from contribution" with a link back to it, and is locked from direct manual editing/deletion (only deleting the contribution itself removes both rows together).
- **PDF/HTML contribution receipts**, individually downloadable/printable per contribution, a selected-rows bulk receipt PDF, plus CSV export (all contributions, or just the selected rows) — admin-only, distinct from the public aggregated leaderboard.
- **Public contributor recognition leaderboard** on the public edition page — ranks contributors by their combined contribution total, but the public page never shows amounts, phone numbers, notes, or committee status — only rank, photo (or an initials avatar), name, and a recognition badge (Top Contributor / 2nd / 3rd / Top 10).
- Contributors can have an optional public profile photo, managed from the admin Contributor form.
- The legacy `committee_members` table and a few pre-3.48 rows' `committee_member_id` columns are kept (not physically dropped) purely for historical traceability — no admin UI reads or writes them any more; old `admin/committee-members/*` links redirect to the new Finance/Committee/Contributor pages instead of 404ing.

### Public player registration (guest, no login)

A self-serve replacement for collecting player registrations via a form, entirely on the public site:

- **Submission** — a guest fills in name/phone/DOB/player type, uploads an Aadhaar document and payment-proof screenshot, and is issued a unique `RPPL-{year}-{id}` registration number. Reuses an existing Player identity by phone/email when one already matches (never overwrites that player's existing details); rejects ambiguous/conflicting matches and duplicate same-edition registrations without revealing *why* (no enumeration). Registration is only accepted while an edition has both `registration_open` and a configured fee — at most one edition can be open for registration at a time.
- **Private documents** — Aadhaar/payment-proof are stored on private disk storage (never public URLs) and are only ever served back through an authenticated, policy-checked admin route that reads the stored path from the database row itself (never from a request parameter).
- **Admin review & payment verification** — the existing admin Player Registration screens show the registration number, player details, calculated age, submitted documents (view/download via the private routes above), registration fee, and let an admin set `payment_status` (pending/paid/failed/refunded) and record a payment reference/UTR. Deleting a registration (only when it has no squad assignment) also cleans up its owned document files.
- **Status lookup** — a guest can check their registration/payment status at any time using their **Registration Number + phone** (no login, no OTP) — deliberately available even when new registration is currently closed, and even for completed editions or an since-deactivated player. Never exposes phone/email/DOB/documents/payment reference — just registration number, player name, edition, fee, and a plain-language payment status.
- Rate-limited (both submission and status lookup) to slow down abuse without a CAPTCHA/OTP.

### Admin dashboard & reports

- **Dashboard** — an operational overview for the currently-relevant edition (active, else soonest upcoming, else most recently completed): registration/team/squad/match counts, an actionable "pending payment verification" banner, a Registration Payments summary (paid/pending/failed/refunded counts and the actual amount collected), a Finance snapshot (income/expenses/balance), and a Contributions snapshot (total, record count, recognized-contributor count) — each linking straight into the relevant admin page, already filtered to the selected edition.
- **Reports hub** — a central, edition-scoped page (`admin/reports`) linking to every existing report/export (Edition Summary PDF, Registration CSV, Finance CSV, Contribution CSV, a recent-completed-matches list with scorecard PDF links) plus one new **Financial Summary** — a print-friendly page showing income/expense/balance, registration payment figures, and contribution totals together for the edition. The contribution total is always shown as a subset of Finance income (every contribution already has a matching income transaction), never added on top of it; registration payments are a separate operational figure, never folded into the finance ledger balance. Admin-only.

### Push notifications (Firebase Cloud Messaging)

Broadcast push notifications to public website visitors, entirely anonymous — no visitor login exists or is required:

- **Guest subscription** — a visitor clicks "Enable Notifications" in the public header; the browser's own permission prompt is the only thing that ever appears (never triggered automatically). A granted browser token is stored in `fcm_tokens`, keyed by the token itself so a returning visitor's repeat subscription updates the same row rather than creating a duplicate.
- **Admin content management** (`admin/notifications`, admin-only) — create/edit a `Notification`'s title, message, and an optional internal action URL (an external link is rejected by validation; there is no open-redirect surface).
- **Send / Resend** — one explicit action always broadcasts the notification's *current* content to every currently active subscriber, creating a new, immutable `NotificationSend` snapshot every time (editing the notification after a send never rewrites what that send actually contained). Sending is queued (`SendNotificationJob`) and reports back attempted/accepted/failed counts — "Accepted" means Firebase accepted the message for delivery, never "Delivered" or "Read". A permanently invalid/unregistered token is deactivated automatically; a transient failure is not.
- No audience selector, schedule, topic, or rich media in V1 — every send targets every active subscriber, by design.

### Data retention & cleanup (`admin/data-cleanup`)

One centralized, admin-only destructive-cleanup module — normal operational work and permanent data deletion are deliberately kept apart, never scattered as delete buttons inside other modules. Three tabs:

- **Notifications** — delete old `Notification`/`NotificationSend` records before a chosen date; delete every FCM token Firebase has already reported invalid ("inactive"); delete FCM tokens not seen in a chosen number of days (replaces an earlier "keep the latest N" approach, which could remove a genuinely active token while an older invalid one survived).
- **Registration Documents** — purges private Aadhaar/payment-proof *files* for a selected edition that is no longer open for public registration, without ever touching the registration record itself (player, payment status, registration number, fee, and financial history all remain exactly as they were) — only the file and its own path column are cleared.
- **System** — deletes old `failed_jobs` records before a chosen date. (Two related candidates were deliberately **not** built: `job_batches`, since this app never uses `Bus::batch()` so the table is never populated; and application log cleanup, judged unsafe to build against the configured `single` log channel — see the Phase 3.49 report.)
- Every date cutoff is interpreted as midnight **in the configured `system.display_timezone`**, converted to UTC before the query runs — not the server's own timezone — and the selected date itself is always excluded ("before this date"). Every destructive action shows an exact, server-calculated affected-record count (never a client-supplied number) before the confirmation dialog.
- Every cleanup action writes an immutable audit row to `data_cleanup_logs` (admin, category, action, criteria, records affected, files deleted) — a simple, reusable trail answering who ran what, when, and how much it affected.

### Announcements (public ticker)

- Admin-managed announcements (`admin/announcements`) with a start/end scheduling window and a computed status (scheduled/active/expired/disabled) — never a manually-set status field.
- Active announcements scroll across a CSS-only marquee ticker on every public page (`AnnouncementTickerComposer`); admin controls the display order via an explicit `sort_order`, not a generic column sort.

### Content pages (Privacy Policy / Terms & Conditions / FAQs)

- Three fixed content-page slots (`admin/content-pages`), each with Markdown-authored content rendered safely to HTML (`MarkdownRenderer`, raw HTML input escaped, unsafe links rejected) and shown at its own public route/footer link.
- No create/delete — the three slots are fixed identities, only their content is editable.

### Global settings, dynamic branding & theme

- A tabbed admin Settings screen (`admin/settings`) covering general branding (application name, short name, tagline, logo/favicon, footer text), contact details, system options (currency symbol, display timezone, the committee dues target — see Finance below), and encrypted payment-gateway secrets — each tab its own scoped update action, never one generic "update settings" endpoint.
- Branding and theme colors are consumed live, application-wide (public site, admin panel, PDFs/receipts) via `BrandingComposer`/CSS custom properties — changing a setting takes effect on the very next request, no rebuild/redeploy needed.
- A single `money()` helper (`app/helpers.php`) formats every currency figure (admin screens, PDFs, the public registration flow) using the configured currency symbol, so there is one place formatting rules live rather than a hardcoded `₹` scattered through views.
- **Maintenance mode** — an admin-configurable toggle that shows a branded maintenance page to public visitors (admin login/panel remains accessible) instead of Laravel's default down-page.

### Admin table UX — search, filters, sorting, pagination, exports

The highest-traffic admin lists (Player Registrations, Edition Transactions, Edition Contributions, Matches, Players, Contributors) share a consistent, server-side table experience rather than each hand-rolling its own:

- **Search & domain filters** — module-specific, server-side (e.g. registration#/name/phone on Registrations; edition/type on Transactions) — never a generic all-columns search.
- **Date-range filtering** (Player Registrations, Edition Transactions, Edition Contributions, Matches) — inclusive `from_date`/`to_date` on each module's operationally meaningful date column, validated server-side.
- **Validated sorting** — clickable column headers with an allow-listed sort column + asc/desc toggle (`FiltersAdminTables::allowedSort()`); a raw/unknown sort column always falls back safely, never passed straight into `orderBy()`.
- **Rows-per-page** — a "Rows" selector on every one of these tables, allow-listed to **10 / 20 / 50 / 100 / 200** (default **20**); an invalid or missing value falls back to the default rather than being trusted directly.
- **Row selection & selective reporting** (Player Registrations, Edition Transactions, Edition Contributions, Matches) — an explicit "select all **on this page**" checkbox (never "select every filtered record") feeding a selected-rows CSV export; Edition Contributions additionally offers a selected-rows **bulk PDF receipt** run. Matches also gained its first-ever CSV export (filtered and selected) in this pass, where none existed before.
- All of search/filters/date-range/sort/per-page state survives pagination via the query string — no session/localStorage table state.
- Deliberately **not** added to Content Pages, Settings, Users, Data Cleanup, Announcements (its `sort_order` is a manual ticker-order field, not a generic sort), Notifications, Editions, or Venues — each screen is either not a real data table or too small (≤14 rows) to justify it.

## Architecture conventions

This codebase deliberately stays small and boring rather than speculative:

- **Controller → FormRequest (validation) → Policy (authorization) → Service (business rules) → Eloquent model → Blade view.** No repositories, DTOs, observers, generic events/listeners, or job queues unless a genuine, demonstrated need exists (real-time scoring's broadcast event is the one deliberate exception).
- `is_active` exists only on master/reusable entities (players, teams, venues, contributors, users) — never on historical/pivot data (a completed match, a recorded contribution, a squad assignment, an edition committee membership), which must always remain exactly as it happened.
- Explicit workflow actions instead of a generic "update status" endpoint wherever a client must never be able to drive an arbitrary state transition (match lifecycle, innings, contributions).
- One real person is always one identity row (e.g. Contributor) — a role like "committee member" is a separate, scoped membership/relationship record, never a second identity table to keep in sync.
- Every new admin resource gets its own Policy from the start, not a shared/generic gate.

## Tech stack

- PHP 8.2+ / Laravel 12
- MySQL/MariaDB (dev), SQLite in-memory (automated tests)
- Laravel Reverb (WebSocket broadcasting) + Laravel Echo / pusher-js for real-time match scoring
- barryvdh/laravel-dompdf for PDF exports (edition report, scorecard, contribution receipts)
- Vite + Tailwind CSS, vanilla JS (no frontend framework), SweetAlert2 for confirmation dialogs
- Laravel Pint for code style

## Getting started

1. Install PHP dependencies:
   ```
   composer install
   ```
2. Install JS dependencies:
   ```
   npm install
   ```
3. Copy the environment file and set your database credentials:
   ```
   cp .env.example .env
   php artisan key:generate
   ```
4. Run migrations:
   ```
   php artisan migrate
   ```
5. Link the public storage disk — **required** for branding (logo/favicon), player/team/contributor photos, and any other uploaded file to actually be servable; without this they 404 even though the upload itself succeeds:
   ```
   php artisan storage:link
   ```
6. Build frontend assets:
   ```
   npm run dev   # or: npm run build
   ```
7. Serve the application:
   ```
   php artisan serve
   ```
8. (Optional, for live match scoring) Start Reverb in a second terminal:
   ```
   php artisan reverb:start
   ```
   Set matching `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` in `.env` first (any values work locally, they just need to match on both the app and Reverb). Without this running, everything else still works — score updates simply won't arrive live until the page is refreshed.
9. (Optional, for payment-proof OCR) Install [Tesseract](https://github.com/tesseract-ocr/tesseract) locally so a guest registration's payment screenshot gets an automatic transaction-ID suggestion:
   ```
   # Debian/Ubuntu
   sudo apt-get install tesseract-ocr tesseract-ocr-eng
   ```
   Without it installed, registration submission works exactly the same — OCR is best-effort background processing (see `ProcessPaymentProofOcr`) and never blocks or affects registration creation; it just leaves the suggestion unextracted. If `tesseract` isn't on your system `PATH`, set `TESSERACT_PATH` in `.env` to its full executable path (leave blank to use `PATH`). The extracted suggestion (and a same-reference duplicate warning) appears on each registration's admin detail page, advisory only — it never replaces the admin-entered Payment reference.
10. Start a queue worker — **required** for OCR processing and for push notification sending, both of which run as queued jobs against `QUEUE_CONNECTION=database`:
    ```
    php artisan queue:work
    ```
11. (Optional, for push notifications) Two separate sets of Firebase configuration:
    - **Browser (public, safe to commit as blanks in `.env.example`)** — the `VITE_FIREBASE_*` values and `VITE_FIREBASE_VAPID_KEY` in `.env`, from your Firebase project's Web app config. Without these, the public "Enable Notifications" control simply stays hidden — the rest of the site is unaffected. **After changing any of these, run `npm run build` (or restart `npm run dev`)** — both regenerate `public/firebase-messaging-sw.js` from the same `.env` values (see `resources/build/generate-firebase-sw.js`) before starting Vite, so the app bundle and the service worker can never drift out of sync with each other or ship a placeholder value; the generated file itself is gitignored and edited only via `resources/js/firebase-messaging-sw.template.js`, never directly.
    - **Server (private, NEVER commit)** — download a service-account JSON key from Firebase Console → Project Settings → Service Accounts and place it at `storage/app/firebase/firebase-service-account.json` (already gitignored). **Leave `FIREBASE_CREDENTIALS` in `.env` blank** — `AppServiceProvider` automatically points Firebase at that same conventional path (via `storage_path()`, resolved fresh on whichever machine the app is actually running on), so nothing environment-specific ever needs to go in `.env`; only set the env var explicitly if a credential must live somewhere non-standard. Without any credential configured, admin notification content management (create/edit/Send button) still works — a queued send to a non-zero audience will simply fail safely and log the error rather than actually reaching Firebase; a send with zero active subscribers always completes successfully regardless, since no Firebase call is made in that case.
    - **Production requirement**: Firebase Web Push (the browser subscription flow) requires a secure context — HTTPS in production, `localhost` is fine for local development. This is a deployment/hosting requirement, not something RPPL's code can relax.

## Production checklist

`.env.example` is a local-development template — flip these explicitly before going live:

- `APP_ENV=production` and `APP_DEBUG=false` (`.env.example` defaults `APP_DEBUG=true`, which must never run in production — it leaks stack traces/config).
- `SESSION_SECURE_COOKIE=true`, served over HTTPS (not set by default; without it the session cookie is also sent over plain HTTP).
- `php artisan storage:link` has been run on the deployed instance (see step 5 above — easy to forget on a fresh deploy).
- A queue worker is running under a process supervisor (e.g. Supervisor/systemd), not just a one-off terminal — required for OCR and push notification sending (step 10 above).
- Real Firebase credentials configured (step 11) if push notifications are wanted; the app works fine without them, just with that one feature inactive.
- A unique `APP_KEY` generated per environment (`php artisan key:generate`) — also used to encrypt the Settings module's Razorpay secret fields; rotating it later invalidates any already-stored encrypted values.
- No scheduler/cron entry is required — this app has no `Schedule::` jobs.
- If a queued job (OCR, notification send) exhausts its retries, it lands in `failed_jobs` with no other signal to an admin — check periodically with `php artisan queue:failed`, and retry with `php artisan queue:retry {id}` (or `all`) once the underlying issue is fixed.

## Demo data

`php artisan migrate:fresh --seed` seeds a complete, internally-consistent stakeholder demo dataset — everything in this README's Features list has real, connected data to show:

- **3 editions**: RPPL 2024 (completed/historical), RPPL 2025 (active — the richest dataset, and the only one with public registration open), RPPL 2026 (upcoming/draft).
- **Login accounts** — `admin@rppl.test` / `password` (Admin) and `scorer@rppl.test` / `password` (Scorer). **Local/demo credentials only — never use these in production.**
- ~45 reusable players, 6 teams, 3 venues; registrations/squads for the historical and active editions (paid/pending/failed/refunded payment variety on the active edition).
- Matches covering every lifecycle state on the active edition — scheduled, toss, live (exactly one, for the live-scoring/public-live-page demo), completed (via real ball-by-ball scoring through the actual scoring services, so scorecards/statistics/standings are genuinely derived, not fabricated), cancelled, and abandoned (with its partial delivery history preserved) — plus completed historical matches and draft upcoming fixtures.
- 24 contributors — 10 on the active edition's committee (deliberately covering every dues status the Finance "Committee" tab shows: not paid, partially paid, paid in full via one payment, paid in full via installments, and over-target) plus 14 general contributors — with contributions (via `EditionContributionService`, so each has exactly one linked finance transaction) and additional manual finance ledger entries, enough to populate the Dashboard, Reports, and the public contributor leaderboard across every badge tier.
- No OCR jobs are ever dispatched by the seeders. Every seeded registration's `ocr_status` is `failed` rather than the raw `pending` default — see `database/seeders/Demo/DemoRegistrationSeeder.php`'s docblock for why. Exactly one demo registration (the active edition's first squad slot) has a `payment_proof_path`, pointing at a hand-built, non-photographic, solid-color placeholder PNG (`database/seeders/Demo/assets/demo-payment-proof.png` — no text, no photo, no identity data), stored through the exact same private disk/directory the real guest-upload flow uses, purely so the admin document-review screen (view/download) has one real file to demonstrate. No seeded registration has an `aadhaar_document_path` — a labeled "DEMO DOCUMENT" placeholder was judged too close to real-ID territory to safely fake without GD/Imagick (not installed here), so that slot is deliberately left empty instead.

**Before a live demo**, re-run `php artisan migrate:fresh --seed` if it's been more than a day or two since the database was last seeded — the one seeded "live" match and the one seeded "toss"-stage match have a `scheduled_at` fixed relative to seed time, so their kickoff time visibly drifts into the past the longer you wait (`match_status` itself does not change on its own). Everything else in the demo dataset is not time-sensitive.

The seeder architecture lives in `database/seeders/Demo/` (one class per domain area — users, players, teams, editions, registrations/squads, matches/scoring, finance), orchestrated by `DatabaseSeeder`. It replaces the previous `RpplDemoSeeder`, which used real IPL franchise/player names.

## Testing

Tests run against an in-memory SQLite database (configured in `phpunit.xml`) — no separate test database setup is needed.

```
php artisan test
```

Code style is enforced with Laravel Pint:

```
./vendor/bin/pint --test   # check only
./vendor/bin/pint          # auto-fix
```

## Branching & workflow

- `main` — production, protected (PR required, no direct pushes)
- `uat` — staging, protected (PR required, no direct pushes)
- Feature branches go off `uat` → PR into `uat` → verify on the UAT deployment → PR from `uat` into `main` to promote to production

## Continuous Integration

Every pull request into `uat` (or `main`) triggers a GitHub Actions workflow (`.github/workflows/ci.yml`) that:

- Builds frontend assets (`npm ci && npm run build`) — several views render through `@vite()`, which fails without a build present
- Installs PHP dependencies and runs the full test suite (`php artisan test`) — no external database is needed, since tests run against in-memory SQLite
- Checks code style (`./vendor/bin/pint --test`)

The `uat` (and `main`) branch protection rules require this check to pass before a PR can be merged — a broken build, a failing test, or a style violation blocks the merge button entirely. CI only decides whether a change is allowed to merge; deployment is a separate, later concern triggered by the merge itself, once a hosting target is set up for this project.
