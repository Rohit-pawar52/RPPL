# RPPL Tournament Management System

A Laravel-based admin panel and public tournament website for RPPL (Rohit Pawar Premier League) — a local cricket tournament. Covers everything from setting up an edition, teams, and players, through ball-by-ball live scoring, to standings/statistics, committee/contributor fundraising with receipts, and a public "Google-Forms-replacement" player registration flow.

This README is meant to be comprehensive enough that reading it alone tells you what the project is and what it can currently do. **Whenever a new feature is built, add it here** — see [`memory.md`](memory.md) for the standing instructions this project follows.

## Contents

- [Features](#features)
- [Architecture conventions](#architecture-conventions)
- [Tech stack](#tech-stack)
- [Getting started](#getting-started)
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

### Finance — committee contributions & general contributors ("Chanda")

- **Committee members** — a reusable identity for people who contribute funds to an edition (distinct from admin/scorer user accounts — no login).
- **General contributors** — a separate, broader "Chanda" identity list, optionally linked explicitly (admin-set, never inferred by name/phone) to a Committee Member, so the same real person's contributions from both sources combine into one public ranking identity.
- **Edition contributions** — a committee-member or general-contributor payment against an edition, with an enforced minimum for committee contributions; automatically creates a matching **Edition Transaction** (income) in the finance ledger; deleting a contribution atomically removes that transaction. Contributions are never edited after the fact — a mistaken entry is deleted and re-recorded.
- **Edition transactions** — a general income/expense ledger per edition, with CSV export.
- **PDF/HTML contribution receipts**, individually downloadable/printable per contribution.
- **Public contributor recognition leaderboard** on the public edition page — ranks contributors by their combined contribution total (committee + explicitly-linked general contributions, aggregated), but the public page never shows amounts, phone numbers, notes, or which "type" a contributor is — only rank, photo (or an initials avatar), name, and a recognition badge (Top Contributor / 2nd / 3rd / Top 10). Admin screens continue to show full contribution detail (amount, source, date, notes, recorded-by, linked transaction).
- Contributors (and committee members) can have an optional public profile photo, managed from the admin Contributor form.

### Public player registration (guest, no login)

A self-serve replacement for collecting player registrations via a form, entirely on the public site:

- **Submission** — a guest fills in name/phone/DOB/player type, uploads an Aadhaar document and payment-proof screenshot, and is issued a unique `RPPL-{year}-{id}` registration number. Reuses an existing Player identity by phone/email when one already matches (never overwrites that player's existing details); rejects ambiguous/conflicting matches and duplicate same-edition registrations without revealing *why* (no enumeration). Registration is only accepted while an edition has both `registration_open` and a configured fee — at most one edition can be open for registration at a time.
- **Private documents** — Aadhaar/payment-proof are stored on private disk storage (never public URLs) and are only ever served back through an authenticated, policy-checked admin route that reads the stored path from the database row itself (never from a request parameter).
- **Admin review & payment verification** — the existing admin Player Registration screens show the registration number, player details, calculated age, submitted documents (view/download via the private routes above), registration fee, and let an admin set `payment_status` (pending/paid/failed/refunded) and record a payment reference/UTR. Deleting a registration (only when it has no squad assignment) also cleans up its owned document files.
- **Status lookup** — a guest can check their registration/payment status at any time using their **Registration Number + phone** (no login, no OTP) — deliberately available even when new registration is currently closed, and even for completed editions or an since-deactivated player. Never exposes phone/email/DOB/documents/payment reference — just registration number, player name, edition, fee, and a plain-language payment status.
- Rate-limited (both submission and status lookup) to slow down abuse without a CAPTCHA/OTP.

## Architecture conventions

This codebase deliberately stays small and boring rather than speculative:

- **Controller → FormRequest (validation) → Policy (authorization) → Service (business rules) → Eloquent model → Blade view.** No repositories, DTOs, observers, generic events/listeners, or job queues unless a genuine, demonstrated need exists (real-time scoring's broadcast event is the one deliberate exception).
- `is_active` exists only on master/reusable entities (players, teams, venues, committee members, contributors, users) — never on historical/pivot data (a completed match, a recorded contribution, a squad assignment), which must always remain exactly as it happened.
- Explicit workflow actions instead of a generic "update status" endpoint wherever a client must never be able to drive an arbitrary state transition (match lifecycle, innings, contributions).
- Identity linking (e.g. Contributor ↔ Committee Member) is always an explicit, admin-set foreign key — never inferred by matching on name or phone.
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
5. Build frontend assets:
   ```
   npm run dev   # or: npm run build
   ```
6. Serve the application:
   ```
   php artisan serve
   ```
7. (Optional, for live match scoring) Start Reverb in a second terminal:
   ```
   php artisan reverb:start
   ```
   Set matching `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` in `.env` first (any values work locally, they just need to match on both the app and Reverb). Without this running, everything else still works — score updates simply won't arrive live until the page is refreshed.

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
