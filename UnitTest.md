# RPPL Testing & UAT Documentation

**Last Updated:** 2026-09-19
**Automated Baseline:** 746 tests passing, 2562 assertions, 0 failures (PHPUnit, `tests/`)
**Manual UAT Status:** IN PROGRESS — Batches 1–3 complete

## Purpose

This project has two separate, non-overlapping layers of testing:

1. **Automated tests** (`tests/Feature`, `tests/Unit`) — PHPUnit tests run with `php artisan test`. These verify backend logic, validation rules, authorization, and business invariants at the code level. They are not duplicated in this document.
2. **`UnitTest.md` (this file)** — despite the filename, this is **not** a PHPUnit file and contains **no automated unit tests**. It is the project's consolidated manual UAT / functional verification record: real browser behavior, usability, responsive/mobile layout, actual file uploads and downloads, navigation, visual states, JavaScript interactions, realtime (WebSocket/polling) behavior, print/PDF appearance, and practical end-to-end operational workflow — the things an automated PHPUnit suite cannot establish well.

This file is updated section-by-section as manual UAT progresses. A section is only marked as tested once it has actually been executed in a browser — never by inference from the automated suite passing.

---

## Status Legend

| Status | Meaning |
|---|---|
| **PASS** | Manually verified in a browser; behaved as expected |
| **FAIL** | Manually verified; did not behave as expected — logged as an issue |
| **BLOCKED** | Could not be tested (missing precondition, environment issue, or blocked by another failing item) |
| **NOT TESTED** | Not yet manually executed |
| **N/A** | Not applicable to the current environment/configuration |

## Issue Severity Legend

| Severity | Meaning |
|---|---|
| **BLOCKER** | Prevents core workflow from functioning; must be fixed before proceeding |
| **HIGH** | Significant functional or privacy problem; should be fixed before go-live |
| **MEDIUM** | Real problem, workaround exists, not launch-blocking |
| **LOW** | Cosmetic/minor, fix opportunistically |

---

## Contents

1. [Environment / UAT Setup](#1-environment--uat-setup)
2. [Batch 1 — Foundation, Admin CRUD & Public Registration](#2-batch-1--foundation-admin-crud--public-registration)
3. [Batch 2 — Teams & Match Setup](#3-batch-2--teams--match-setup)
4. [Batch 3 — Scoring, Realtime & Match Result](#4-batch-3--scoring-realtime--match-result)
5. [Batch 4 — Public Website, Statistics & Standings](#5-batch-4--public-website-statistics--standings)
6. [Batch 5 — Finance, Contributions & Reports](#6-batch-5--finance-contributions--reports)
7. [Batch 6 — Responsive, Privacy & Final Smoke](#7-batch-6--responsive-privacy--final-smoke)
8. [Pre-UAT / Product Observations](#8-pre-uat--product-observations)
9. [Issues Found During UAT](#9-issues-found-during-uat)
10. [Final UAT Summary](#10-final-uat-summary)
11. [Future Regression Checklist](#11-future-regression-checklist)

---

## 1. Environment / UAT Setup

RPPL has no queued jobs (`app/Jobs` does not exist, no `ShouldQueue` usage anywhere) and no mail sending in current use (`MAIL_MAILER=log`) — a queue worker is **not** required for UAT despite `QUEUE_CONNECTION=database` being configured. Laravel Reverb **is** required for the realtime scoring tests in Batch 3/4.

**Processes required:**
```
Terminal 1: php artisan serve
Terminal 2: php artisan reverb:start
```
`.env` must have matching `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` on both, and `BROADCAST_CONNECTION=reverb`.

**One-time setup (skip whatever is already done):**
```
php artisan migrate            # never migrate:fresh against data you care about
php artisan storage:link       # required for player/team/contributor photos
npm run build                  # or npm run dev — required by @vite()
```

**Pre-flight checklist:**
- [ ] `php artisan migrate:status` shows nothing pending
- [ ] `public/storage` symlink exists and resolves
- [ ] An admin account can log in at `/admin/login`
- [ ] A scorer account can log in
- [ ] Browser DevTools (Console + Network) available
- [ ] Reverb terminal running with no connection errors

**Bootstrapping the first admin account** (no self-registration exists by design — `UserPolicy::create` requires an already-authenticated admin):
```
php artisan tinker
>>> $role = \App\Models\Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
>>> \App\Models\User::create(['role_id' => $role->id, 'name' => 'UAT Admin', 'email' => 'uat-admin@example.test', 'password' => 'password', 'is_active' => true]);
```
Repeat with `slug => 'scorer'` for a scorer account.

**UAT dataset used for Batch 1 onward:** one dedicated Edition ("UAT 2026", active, registration open, fee configured), 2 teams, 1 venue, players/registrations created through the real admin/public flows (not via seeders) — see Batch 1/2 entries below for exactly what was created.

---

## 2. Batch 1 — Foundation, Admin CRUD & Public Registration

**Status: COMPLETE — all 26 checks PASS**

### Authentication & Authorization

#### UAT-AUTH-01 — Admin login and dashboard redirect
**Area:** Authentication · **Role:** Admin · **Status:** PASS

**Purpose:** Verify an admin can log in and lands on the operational dashboard.

**Preconditions:** An active admin account exists.

**Steps:**
1. Open `/admin/login`.
2. Enter valid admin credentials.
3. Submit.

**Expected:** Login succeeds and redirects to `admin.dashboard`, showing the edition overview and sidebar with all sections (Matches, Tournament, System) visible.

**Result:** PASS

---

#### UAT-AUTH-02 — Scorer login and restricted navigation
**Area:** Authentication · **Role:** Scorer · **Status:** PASS

**Purpose:** Verify a scorer account logs in but only sees match-day navigation.

**Preconditions:** An active scorer account exists.

**Steps:**
1. Log in as scorer.
2. Inspect the sidebar.

**Expected:** Login succeeds; sidebar shows Dashboard and Matches only — no Tournament section (Editions/Teams/Registrations/Finance/Committee/Contributors) and no System section (Users/Reports).

**Result:** PASS

---

#### UAT-AUTH-03 — Guest access to admin dashboard redirects to login
**Area:** Authentication · **Role:** Guest · **Status:** PASS

**Purpose:** Verify unauthenticated access to the admin panel is blocked at the route level.

**Steps:**
1. While logged out, open `/admin/dashboard` directly.

**Expected:** Redirected to `/admin/login`; dashboard content never renders.

**Result:** PASS

---

#### UAT-AUTH-04 — Invalid password displays appropriate error
**Area:** Authentication · **Role:** Admin · **Status:** PASS

**Steps:**
1. On `/admin/login`, submit a valid email with an incorrect password.

**Expected:** A generic, friendly validation error is shown; no account-enumeration detail (e.g. does not reveal whether the email exists); session remains logged out.

**Result:** PASS

---

#### UAT-AUTH-05 — Logout terminates authenticated session
**Area:** Authentication · **Role:** Admin · **Status:** PASS

**Steps:**
1. While logged in, click Logout.
2. Use the browser back button.

**Expected:** Session ends and redirects to login; back-navigation does not restore an authenticated admin page (redirects to login again).

**Result:** PASS

### Edition Management

#### UAT-ED-01 — Create active UAT Edition with public registration enabled and registration fee
**Area:** Edition Management · **Role:** Admin · **Status:** PASS

**Purpose:** Verify an edition can be created with registration open and a fee configured, which is the dataset foundation for the rest of UAT.

**Steps:**
1. Open `admin.editions.create`.
2. Enter name "UAT 2026", status Active, enable public registration, set a registration fee.
3. Save.

**Expected:** Edition is created and appears in the index; public registration form later reflects this edition as open with the configured fee.

**Result:** PASS

---

#### UAT-ED-02 — At-most-one registration-open Edition restriction
**Area:** Edition Management · **Role:** Admin · **Status:** PASS

**Purpose:** Verify the system enforces that only one edition can have registration open at a time.

**Steps:**
1. With "UAT 2026" already registration-open, create or edit a second edition and attempt to also enable registration on it.

**Expected:** The second edition's save is rejected with a validation message; only one edition remains registration-open at any time.

**Result:** PASS

---

#### UAT-ED-03 — Edition form/index mobile usability
**Area:** Edition Management · **Role:** Admin · **Status:** PASS

**Steps:**
1. View the edition create form and index table at a 375px mobile viewport.

**Expected:** No horizontal overflow; form fields and index table remain usable.

**Result:** PASS

### Player Management

#### UAT-PLY-01 — Create Player with photo and verify photo rendering
**Area:** Player Management · **Role:** Admin · **Status:** PASS

**Steps:**
1. `admin.players.create` — fill in player details and upload a photo.
2. Save and open the player's show page.

**Expected:** Player is created; the uploaded photo renders correctly on the show page (and index thumbnail).

**Result:** PASS

---

#### UAT-PLY-02 — Deactivate Player and verify historical/admin visibility remains
**Area:** Player Management · **Role:** Admin · **Status:** PASS

**Steps:**
1. Deactivate an existing player.
2. View the player in the admin index/show pages.

**Expected:** Player remains visible in the admin list (shown as inactive), not deleted or hidden; any historical registration referencing this player remains intact.

**Result:** PASS

### Public Guest Registration

#### UAT-REG-01 — Guest Registration with Aadhaar Image
**Area:** Public Player Registration · **Role:** Guest · **Status:** PASS

**Purpose:** Verify a guest can submit a player registration using an Aadhaar image and a payment screenshot.

**Preconditions:**
- Registration-enabled Edition exists.
- Registration fee is configured.
- Dummy/non-sensitive Aadhaar image and payment screenshot available.

**Steps:**
1. Open the public Player Registration page.
2. Enter valid player information.
3. Upload a dummy Aadhaar image (jpg/png).
4. Upload a dummy payment screenshot.
5. Submit the registration.

**Expected:**
- Registration succeeds.
- A unique `RPPL-{year}-{id}`-format registration number is generated.
- Payment status starts as Pending Verification.
- Success page displays only safe information (registration number, edition, player name, fee) and instructs the guest to save the number.
- Uploaded documents are stored privately, never exposed via a public URL.

**Result:** PASS

---

#### UAT-REG-02 — Guest Registration with Aadhaar PDF
**Area:** Public Player Registration · **Role:** Guest · **Status:** PASS

**Purpose:** Verify the Aadhaar upload also accepts a PDF, not only images.

**Steps:**
1. Repeat the registration flow using a dummy Aadhaar **PDF** instead of an image.

**Expected:** Registration succeeds identically to UAT-REG-01; the PDF is accepted and stored privately.

**Result:** PASS

---

#### UAT-REG-03 — Duplicate/same-phone registration handling
**Area:** Public Player Registration · **Role:** Guest · **Status:** PASS

**Purpose:** Verify a second registration attempt using a phone number already registered for the same edition is rejected without leaking why.

**Steps:**
1. Submit a new registration using a phone number that already has a registration for the UAT Edition.

**Expected:** A generic rejection message is shown (no confirmation that the phone/registration already exists in a way that reveals internal state); no second registration record is created.

**Result:** PASS

---

#### UAT-REG-04 — Public registration mobile usability
**Area:** Public Player Registration · **Role:** Guest · **Status:** PASS

**Steps:**
1. Open the public registration form at a 375px mobile viewport.
2. Interact with text fields and file-upload buttons.

**Expected:** Form does not overflow; file pickers are tappable and usable on a small screen.

**Result:** PASS

---

#### UAT-REG-05 — Closed registration state while preserving status-lookup navigation
**Area:** Public Player Registration · **Role:** Guest · **Status:** PASS

**Purpose:** Verify that closing registration does not also hide the historical status-lookup entry point.

**Steps:**
1. Turn off `registration_open` on the UAT Edition.
2. Open the public registration page.

**Expected:** The page shows a "currently closed" message, but a "Check Registration Status" link/section remains visible and functional.

**Result:** PASS

### Admin Registration Review

#### UAT-REGADM-01 — Pending guest registration appears in admin listing/filter
**Area:** Admin Registration Review · **Role:** Admin · **Status:** PASS

**Steps:**
1. Open `admin.player-registrations.index`.
2. Filter by `payment_status = pending`.

**Expected:** The guest registration(s) from UAT-REG-01/02 appear, correctly filtered.

**Result:** PASS

---

#### UAT-REGADM-02 — Admin access to private Aadhaar document
**Area:** Admin Registration Review · **Role:** Admin · **Status:** PASS

**Steps:**
1. Open a guest registration's show page.
2. Click the Aadhaar document action.

**Expected:** The actual uploaded file opens/downloads correctly; the filename is server-generated (not the original uploaded filename, no sensitive data in the name).

**Result:** PASS

---

#### UAT-REGADM-03 — Admin access to private payment proof
**Area:** Admin Registration Review · **Role:** Admin · **Status:** PASS

**Steps:**
1. From the same registration show page, click the Payment Proof action.

**Expected:** The actual uploaded screenshot opens/downloads correctly, server-generated filename.

**Result:** PASS

---

#### UAT-REGADM-04 — Scorer denied private Aadhaar/document access
**Area:** Admin Registration Review · **Role:** Scorer · **Status:** PASS

**Steps:**
1. As a scorer, attempt to open the Aadhaar document URL for the same registration directly.

**Expected:** Access is forbidden (403); the document is never returned.

**Result:** PASS

---

#### UAT-REGADM-05 — Logged-out user cannot access private documents
**Area:** Admin Registration Review · **Role:** Guest · **Status:** PASS

**Steps:**
1. While logged out, open the Aadhaar/payment-proof document URL directly.

**Expected:** Redirected to admin login; the file is never returned to an unauthenticated request.

**Result:** PASS

---

#### UAT-REGADM-06 — Admin payment verification / payment reference update
**Area:** Admin Registration Review · **Role:** Admin · **Status:** PASS

**Steps:**
1. On the registration edit form, set payment status to Paid and enter a payment reference.
2. Save.

**Expected:** Status and reference are saved and reflected correctly on the index/show pages.

**Result:** PASS

### Public Registration Status

#### UAT-STATUS-01 — Correct Registration Number + phone lookup
**Area:** Public Registration Status · **Role:** Guest · **Status:** PASS

**Steps:**
1. Open `/player-registration/status`.
2. Enter the correct registration number and the phone used at registration.

**Expected:** Status result displays registration number, edition, player name, fee, and payment status wording — with no phone, DOB, document links, or payment reference shown.

**Result:** PASS

---

#### UAT-STATUS-02 — Normalized +91 phone lookup
**Area:** Public Registration Status · **Role:** Guest · **Status:** PASS

**Steps:**
1. Repeat the lookup, entering the phone as `+91 XXXXX XXXXX` instead of the plain 10-digit form.

**Expected:** Lookup still resolves correctly to the same registration.

**Result:** PASS

---

#### UAT-STATUS-03 — Wrong phone returns privacy-safe generic response
**Area:** Public Registration Status · **Role:** Guest · **Status:** PASS

**Steps:**
1. Enter the correct registration number with an incorrect phone number.

**Expected:** The same generic "not found" message is shown as for a nonexistent registration number — no distinguishable difference in wording or behavior.

**Result:** PASS

---

#### UAT-STATUS-04 — Historical status lookup remains available when registration closes
**Area:** Public Registration Status · **Role:** Guest · **Status:** PASS

**Steps:**
1. With registration closed (from UAT-REG-05), repeat the status lookup for a registration made earlier.

**Expected:** Lookup still succeeds; closing new registration does not affect historical status checks.

**Result:** PASS

### CSV Import

#### UAT-IMPORT-01 — Small registration CSV import
**Area:** CSV Import · **Role:** Admin · **Status:** PASS

**Purpose:** Verify the registration CSV import correctly creates new rows and safely skips an already-registered player, without partial/inconsistent writes.

**Preconditions:** A small CSV file with the expected columns (`name,phone,email,registration_fee,payment_status,registered_at`) containing one brand-new registration row and one row matching a player already registered for the UAT Edition.

**Steps:**
1. Open `admin.player-registrations.import`.
2. Select the UAT Edition and upload the CSV file.
3. Submit.

**Expected:** Import summary reports one registration created and one skipped (already registered); the admin registration index reflects exactly the new row, with the existing registration left unchanged.

**Result:** PASS

### Batch 1 Summary

| Area | Tests | Passed | Failed | Blocked | Notes |
|---|---|---|---|---|---|
| Authentication & Authorization | 5 | 5 | 0 | 0 | — |
| Edition Management | 3 | 3 | 0 | 0 | — |
| Player Management | 2 | 2 | 0 | 0 | — |
| Public Guest Registration | 5 | 5 | 0 | 0 | — |
| Admin Registration Review | 6 | 6 | 0 | 0 | — |
| Public Registration Status | 4 | 4 | 0 | 0 | — |
| CSV Import | 1 | 1 | 0 | 0 | — |
| **Total** | **26** | **26** | **0** | **0** | |

**Batch 1 Status: PASS**

---

## 3. Batch 2 — Teams & Match Setup

**Status: COMPLETE — all 7 checks PASS**

### Teams / Edition Teams / Squad Assignment

#### UAT-TEAM-01 — Create Teams with logos
**Area:** Teams · **Role:** Admin · **Status:** PASS

**Purpose:** Verify Teams can be created with a logo upload and that the logo renders correctly in the admin UI.

**Preconditions:** Admin is logged in.

**Steps:**
1. Open `admin.teams.create`.
2. Enter a team name and upload a logo image.
3. Save.
4. Repeat for a second team.
5. View both teams on the index and show pages.

**Expected:** Both teams are created; each uploaded logo renders correctly on the index thumbnail and the show page.

**Result:** PASS

---

#### UAT-TEAM-02 — Add Teams to the Edition
**Area:** Edition Teams · **Role:** Admin · **Status:** PASS

**Purpose:** Verify both created teams can be added as participants of the UAT Edition.

**Preconditions:** The UAT Edition and the two teams from UAT-TEAM-01 exist.

**Steps:**
1. Open `admin.edition-teams.create`.
2. Select the UAT Edition and the first team; save.
3. Repeat for the second team.

**Expected:** Both teams appear as participants of the UAT Edition in the Edition Teams listing.

**Result:** PASS

---

#### UAT-TEAM-03 — Assign registered players to squads with jersey numbers
**Area:** Squads (Team Players) · **Role:** Admin · **Status:** PASS

**Purpose:** Verify eligible registered players can be assigned to an Edition Team squad with a jersey number and role, and that the duplicate-jersey guard is enforced.

**Preconditions:** Eligible player registrations exist for the UAT Edition; both Edition Teams from UAT-TEAM-02 exist.

**Steps:**
1. Open `admin.team-players.create`.
2. Assign an eligible registration to one Edition Team with a jersey number and role.
3. Repeat for further eligible registrations across both Edition Teams.
4. Attempt to assign a second player to an already-used jersey number on the same Edition Team.

**Expected:** Valid assignments succeed and appear in the squad listing; the duplicate-jersey attempt on the same Edition Team is rejected with a validation error, and no duplicate squad record is created.

**Result:** PASS

### Venues

#### UAT-VEN-01 — Create a Venue and confirm it is selectable for a Match
**Area:** Venues · **Role:** Admin · **Status:** PASS

**Steps:**
1. Open `admin.venues.create`.
2. Enter venue details and save.
3. Open the Match creation form and check the venue dropdown.

**Expected:** The venue is created and appears as a selectable option when creating a match.

**Result:** PASS

### Match Creation

#### UAT-MATCH-01 — Create a small UAT Match
**Area:** Matches · **Role:** Admin · **Status:** PASS

**Purpose:** Verify a match can be created against the UAT Edition with the two UAT teams, the UAT venue, a scheduled date/time, and a reduced overs-per-innings configuration, and that it initially appears with Scheduled status.

**Preconditions:** UAT Edition, both Edition Teams, and the UAT Venue exist.

**Steps:**
1. Open `admin.matches.create`.
2. Select the UAT Edition, Team A, Team B, and the Venue.
3. Set a scheduled date/time and configure `overs_per_innings` to a small value (1 over) for a compact UAT scoring script.
4. Save.

**Expected:** The match is created and appears in `admin.matches.index` with status **Scheduled**.

**Result:** PASS

### Playing XI

#### UAT-XI-01 — Configure Playing XI for both sides
**Area:** Playing XI · **Role:** Admin · **Status:** PASS

**Purpose:** Verify Playing XI (selected match players) can be configured for both sides from their respective squads, and that doing so allows the match to proceed toward the Toss workflow.

**Preconditions:** The UAT Match exists; both Edition Teams have assigned squad players (UAT-TEAM-03).

**Steps:**
1. From the match show page, open **Manage Playing XI**.
2. Select eligible squad players for Team A.
3. Select eligible squad players for Team B.
4. Return to the match show page.

**Expected:** Selected players are saved for both sides; the match page now allows progression into the Toss workflow (Start Toss becomes available).

**Result:** PASS

---

#### UAT-XI-02 — Incomplete selected-player state blocks progression
**Area:** Playing XI · **Role:** Admin · **Status:** PASS

**Purpose:** Verify the existing guard prevents the match from progressing to Toss/Start when the required selected-player state is incomplete for one or both sides.

**Preconditions:** A match where at least one side does not yet have a selected player.

**Steps:**
1. With only one side's Playing XI selected (or before selecting either side), attempt to proceed to Start Toss / Start Match.

**Expected:** Progression is blocked (the action remains unavailable/disabled) until both sides have at least the required selected-player state.

**Result:** PASS

### Batch 2 Summary

| Area | Tests | Passed | Failed | Blocked | Notes |
|---|---|---|---|---|---|
| Teams / Edition Teams / Squad Assignment | 3 | 3 | 0 | 0 | — |
| Venues | 1 | 1 | 0 | 0 | — |
| Match Creation | 1 | 1 | 0 | 0 | — |
| Playing XI | 2 | 2 | 0 | 0 | — |
| **Total** | **7** | **7** | **0** | **0** | |

**Batch 2 Status: PASS**

---

## 4. Batch 3 — Scoring, Realtime & Match Result

**Status: COMPLETE — all 23 checks PASS**

### Toss and Match Start

#### UAT-TOSS-01 — Toss workflow
**Area:** Match Flow · **Role:** Admin/Scorer · **Status:** PASS

**Purpose:** Verify the toss can be started and recorded (winner + bat/bowl decision).

**Preconditions:** The UAT Match has Playing XI configured for both sides (Batch 2).

**Steps:**
1. From the match show page, Start Toss.
2. Select the toss-winning side and the bat/bowl decision.
3. Save.

**Expected:** Toss is saved successfully; the match becomes eligible to start.

**Result:** PASS

---

#### UAT-TOSS-02 — Start Match
**Area:** Match Flow · **Role:** Admin/Scorer · **Status:** PASS

**Steps:**
1. With the toss recorded, Start Match.

**Expected:** Match transitions to the live state and first-innings scoring (Score Innings) becomes available.

**Result:** PASS

### Live Ball-by-Ball Scoring

#### UAT-SCORE-01 — Normal single
**Area:** Ball-by-Ball Scoring · **Role:** Admin/Scorer · **Status:** PASS

**Steps:**
1. Record a delivery with 1 run off the bat.

**Expected:** Runs update correctly; strike rotation behaves correctly for an odd-runs delivery.

**Result:** PASS

---

#### UAT-SCORE-02 — Dot ball
**Area:** Ball-by-Ball Scoring · **Role:** Admin/Scorer · **Status:** PASS

**Steps:**
1. Record a delivery with 0 runs.

**Expected:** Score remains correct; the legal-ball count advances by one.

**Result:** PASS

---

#### UAT-SCORE-03 — Boundary
**Area:** Ball-by-Ball Scoring · **Role:** Admin/Scorer · **Status:** PASS

**Steps:**
1. Record a delivery with 4 runs off the bat.

**Expected:** Four runs are recorded correctly against the total and the batter's figures.

**Result:** PASS

---

#### UAT-SCORE-04 — Wide
**Area:** Ball-by-Ball Scoring · **Role:** Admin/Scorer · **Status:** PASS

**Steps:**
1. Record a delivery marked as a Wide with its extra run(s).

**Expected:** The extra run(s) are added to the total; the legal-ball count does not incorrectly advance for the wide.

**Result:** PASS

---

#### UAT-SCORE-05 — Wicket / replacement batter
**Area:** Ball-by-Ball Scoring · **Role:** Admin/Scorer · **Status:** PASS

**Steps:**
1. Record a delivery marked as a wicket, selecting the dismissed batter and dismissal type.
2. Select the replacement batter for the vacant end on the next delivery.

**Expected:** The wicket is recorded correctly; the next-batter workflow correctly prompts for and accepts the replacement.

**Result:** PASS

---

#### UAT-SCORE-06 — Over/innings limit
**Area:** Ball-by-Ball Scoring · **Role:** Admin/Scorer · **Status:** PASS

**Steps:**
1. Continue scoring until the configured one-over limit for the innings is reached.

**Expected:** The configured over limit is respected; the scoring state/UI changes appropriately (further delivery entry is no longer available) once the limit is reached.

**Result:** PASS

---

#### UAT-SCORE-07 — Undo latest delivery
**Area:** Ball-by-Ball Scoring · **Role:** Admin/Scorer · **Status:** PASS

**Steps:**
1. Use Undo Last Delivery (with confirmation) on the most recently recorded delivery.

**Expected:** The latest delivery is removed; the canonical score recalculates correctly to reflect its removal.

**Result:** PASS

---

#### UAT-SCORE-08 — Re-enter delivery after undo
**Area:** Ball-by-Ball Scoring · **Role:** Admin/Scorer · **Status:** PASS

**Steps:**
1. After the undo in UAT-SCORE-07, re-enter the delivery.

**Expected:** The delivery can be entered again; the score returns to the expected state as if the undo had not affected the final outcome.

**Result:** PASS

### Innings Completion / Second Innings

#### UAT-INN-01 — First innings completion
**Area:** Innings Flow · **Role:** Admin/Scorer · **Status:** PASS

**Steps:**
1. Complete Innings on the first innings once its over limit is reached.

**Expected:** The first innings becomes completed/locked; Start Second Innings becomes available.

**Result:** PASS

---

#### UAT-INN-02 — Second innings (chase)
**Area:** Innings Flow · **Role:** Admin/Scorer · **Status:** PASS

**Steps:**
1. Start the second innings.
2. Score deliveries for the chase.

**Expected:** The chase-scoring workflow operates correctly (score/target tracking behaves as expected through the second innings).

**Result:** PASS

---

#### UAT-INN-03 — Second innings completion
**Area:** Innings Flow · **Role:** Admin/Scorer · **Status:** PASS

**Steps:**
1. Complete Innings on the second innings.

**Expected:** Second innings completes correctly, consistent with the first innings' completion behavior.

**Result:** PASS

### Match Result

#### UAT-RESULT-01 — Result preview
**Area:** Match Result · **Role:** Admin/Scorer · **Status:** PASS

**Steps:**
1. On the match show page, review the result preview after both innings are completed but before finalizing.

**Expected:** The result preview agrees with the completed innings' scores.

**Result:** PASS

---

#### UAT-RESULT-02 — Finalize Match
**Area:** Match Result · **Role:** Admin/Scorer · **Status:** PASS

**Steps:**
1. Click Finalize Match (with confirmation).

**Expected:** The match transitions to completed status and the result is displayed.

**Result:** PASS

---

#### UAT-RESULT-03 — Completed match rejects further scoring
**Area:** Match Result · **Role:** Admin/Scorer · **Status:** PASS

**Steps:**
1. After finalization, attempt to access the scoring page/record a further delivery for this match.

**Expected:** The completed match cannot accept further scoring/deliveries.

**Result:** PASS

### Scorecard

#### UAT-SCC-01 — Admin scorecard
**Area:** Scorecard · **Role:** Admin · **Status:** PASS

**Steps:**
1. Open the admin scorecard for the completed UAT Match.

**Expected:** The scorecard displays the manually scored innings correctly (batting/bowling figures consistent with what was recorded).

**Result:** PASS

---

#### UAT-SCC-02 — Public scorecard
**Area:** Scorecard · **Role:** Guest · **Status:** PASS

**Steps:**
1. Open the public scorecard page for the same match.

**Expected:** The corresponding match data displays correctly without exposing admin-only information.

**Result:** PASS

---

#### UAT-SCC-03 — Scorecard PDF
**Area:** Scorecard · **Role:** Guest/Admin · **Status:** PASS

**Steps:**
1. Download the Match Scorecard PDF.

**Expected:** The PDF downloads and opens successfully and is visually usable.

**Result:** PASS

### Realtime / Public Live Experience

*Realtime was intentionally tested while the match was still live, rather than after finalization.*

#### UAT-LIVE-01 — Realtime update via Reverb
**Area:** Realtime / Public Live · **Role:** Admin + Guest (two-tab) · **Status:** PASS

**Purpose:** Verify a delivery recorded by the admin/scorer appears on the public live page without a manual refresh, via the WebSocket (Reverb) path.

**Preconditions:** Reverb is running; the UAT Match is live.

**Steps:**
1. Open the admin scoring page in one tab/browser and the public live match page in another.
2. Record a delivery in the admin tab.
3. Observe the public live tab.

**Expected:** The newly recorded delivery appears on the public live page without a manual refresh.

**Result:** PASS

---

#### UAT-LIVE-02 — Polling fallback with Reverb unavailable
**Area:** Realtime / Public Live · **Role:** Admin + Guest · **Status:** PASS

**Steps:**
1. Stop/disconnect the Reverb server.
2. Record another delivery in the admin tab.
3. Observe the public live tab.

**Expected:** The public live page still refreshes the score via the polling fallback even with Reverb unavailable.

**Result:** PASS

---

#### UAT-LIVE-03 — Realtime resumes after Reverb restored
**Area:** Realtime / Public Live · **Role:** Admin + Guest · **Status:** PASS

**Steps:**
1. Restart the Reverb server.
2. Continue scoring and observe the public live tab.

**Expected:** Realtime/live functionality continues normally after Reverb is restored, with no broken page state.

**Result:** PASS

---

#### UAT-LIVE-04 — Refresh on tab visibility change
**Area:** Realtime / Public Live · **Role:** Guest · **Status:** PASS

**Steps:**
1. Background/sleep the public live tab, then return to it.

**Expected:** The canonical score refreshes appropriately upon returning to the tab.

**Result:** PASS

### Batch 3 Summary

| Area | Tests | Passed | Failed | Blocked | Notes |
|---|---|---|---|---|---|
| Toss and Match Start | 2 | 2 | 0 | 0 | — |
| Live Ball-by-Ball Scoring | 8 | 8 | 0 | 0 | — |
| Innings Completion / Second Innings | 3 | 3 | 0 | 0 | — |
| Match Result | 3 | 3 | 0 | 0 | — |
| Scorecard | 3 | 3 | 0 | 0 | — |
| Realtime / Public Live Experience | 4 | 4 | 0 | 0 | Tested while match was still live, before finalization |
| **Total** | **23** | **23** | **0** | **0** | |

**Batch 3 Status: PASS**

---

## 5. Batch 4 — Public Website, Statistics & Standings

**Status: NOT TESTED**

- [ ] UAT-STAT-01 — Edition standings reflect the finalized match's result. **Status: NOT TESTED**
- [ ] UAT-STAT-02 — Player statistics/leaderboard reflect the match's runs/wickets. **Status: NOT TESTED**
- [ ] UAT-STAT-03 — Public edition page shows matching standings/stats. **Status: NOT TESTED**
- [ ] UAT-PUB-01 — Public players/teams/venues index and show pages render correctly with photos/logos. **Status: NOT TESTED**
- [ ] UAT-PUB-02 — Public home page loads and the Register nav link works. **Status: NOT TESTED**
- [ ] UAT-PUB-03 — Public edition page usable at 375px (no horizontal overflow). **Status: NOT TESTED**

---

## 6. Batch 5 — Finance, Contributions & Reports

**Status: NOT TESTED**

### Finance Ledger / Committee / Contributors
- [ ] UAT-FIN-01 — Record one income and one expense transaction for the UAT Edition. **Status: NOT TESTED**
- [ ] UAT-COMM-01 — Create a Committee Member. **Status: NOT TESTED**
- [ ] UAT-CONTRIB-01 — Create a Contributor with a photo upload; preview renders. **Status: NOT TESTED**
- [ ] UAT-CONTRIB-02 — Record a committee contribution meeting the enforced minimum. **Status: NOT TESTED**
- [ ] UAT-CONTRIB-03 — Record a general contributor contribution. **Status: NOT TESTED**

### Public Contributor Leaderboard & Receipt
- [ ] UAT-LEAD-01 — Leaderboard shows the photographed contributor's real photo and initials fallback for one without a photo. **Status: NOT TESTED**
- [ ] UAT-LEAD-02 — Badge wording is correct (e.g. "Top Contributor") and no amount/phone/notes appear anywhere, including page source. **Status: NOT TESTED**
- [ ] UAT-RCPT-01 — Contribution receipt HTML preview and PDF download both show correct amount/date/name. **Status: NOT TESTED**

### Finance/Contribution Cross-Checks
- [ ] UAT-FINX-01 — Ledger income total correctly includes each contribution exactly once (no double-count). **Status: NOT TESTED**
- [ ] UAT-FINX-02 — Dashboard Finance card matches the ledger page's income/expense/balance. **Status: NOT TESTED**
- [ ] UAT-FINX-03 — Dashboard Contributions card matches the Contributions page total, with the "already included in Finance income" note visible. **Status: NOT TESTED**
- [ ] UAT-FINX-04 — Public leaderboard hides amount while the admin Contributions page shows it for the same rows. **Status: NOT TESTED**

### Dashboard & Reports
- [ ] UAT-DASH-01 — Pending-verification banner links to the correctly filtered registration queue. **Status: NOT TESTED**
- [ ] UAT-DASH-02 — Registration Payments card shows the correct paid amount (sum of actual stored fees). **Status: NOT TESTED**
- [ ] UAT-REPORT-01 — Registration CSV downloads and opens with sane headers/data. **Status: NOT TESTED**
- [ ] UAT-REPORT-02 — Finance CSV downloads and includes contribution-linked rows correctly labeled. **Status: NOT TESTED**
- [ ] UAT-REPORT-03 — Contribution CSV downloads with correct columns and no phone column. **Status: NOT TESTED**
- [ ] UAT-REPORT-04 — Edition Summary PDF layout is not clipped and includes all expected sections. **Status: NOT TESTED**
- [ ] UAT-REPORT-05 — Financial Summary print preview renders cleanly. **Status: NOT TESTED**
- [ ] UAT-REPORT-06 — Changing the Reports edition selector updates every link/figure. **Status: NOT TESTED**

---

## 7. Batch 6 — Responsive, Privacy & Final Smoke

**Status: NOT TESTED**

### Cancellation / Abandonment
- [ ] UAT-CANCEL-01 — Cancel a scheduled match before toss; clean status, no scoring artifacts. **Status: NOT TESTED**
- [ ] UAT-CANCEL-02 — Abandon a match after some scoring; existing deliveries remain visible, no further scoring possible. **Status: NOT TESTED**

### Responsive
- [ ] UAT-RESP-01 — Public home/edition page at 375/768/1366px. **Status: NOT TESTED**
- [ ] UAT-RESP-02 — Public registration form at 375px. **Status: NOT TESTED**
- [ ] UAT-RESP-03 — Status lookup form at 375px. **Status: NOT TESTED**
- [ ] UAT-RESP-04 — Admin dashboard at 375px. **Status: NOT TESTED**
- [ ] UAT-RESP-05 — Admin scoring page at 375/768px. **Status: NOT TESTED**
- [ ] UAT-RESP-06 — Public live score page at 375px. **Status: NOT TESTED**
- [ ] UAT-RESP-07 — Contributor leaderboard grid at 375px. **Status: NOT TESTED**
- [ ] UAT-RESP-08 — Admin Reports page at 375px. **Status: NOT TESTED**

### Security/Privacy Spot Check
- [ ] UAT-SEC-01 — Scorer gets 403 on Reports/Finance/Contributions/Users; can still reach Matches/scoring. **Status: NOT TESTED**
- [ ] UAT-SEC-02 — Guest is redirected to login for any admin URL and for private document URLs. **Status: NOT TESTED**
- [ ] UAT-SEC-03 — No hidden fields/attributes leak amount, phone, or payment reference on public pages (page source check). **Status: NOT TESTED**

### Final Smoke
- [ ] UAT-SMOKE-01 — One uninterrupted admin walkthrough (Dashboard → Reports → Edition → Registrations → Teams → Match → Scorecard → Dashboard) with no console errors. **Status: NOT TESTED**

---

## 8. Pre-UAT / Product Observations

*Product / deployment observations — not current UAT failures.*

1. New admin/scorer accounts currently use `is_active` rather than a separate pending-approval workflow — a newly created account is active immediately upon creation.
2. The first admin account requires deployment/bootstrap creation (e.g. via `php artisan tinker`) rather than any form of public self-registration or setup wizard.
3. Match start currently does not enforce a full 11-player minimum; existing behavior permits smaller configured squads/playing groups (at least one selected player per side is sufficient to start a match).
4. `QUEUE_CONNECTION` may be configured (`database`) even though current application functionality does not require a queue worker to run.

---

## 9. Issues Found During UAT

*No issues currently logged. Use the template below for every failure found in future batches.*

### ISSUE-UAT-XXX

- **UAT ID:**
- **Module:**
- **Status:**
- **Severity:**
- **Role:**
- **Device/Viewport:**
- **Preconditions:**
- **Steps to Reproduce:**
- **Expected:**
- **Actual:**
- **Screenshot / Console:**
- **Reproducible:**
- **Resolution:**
- **Regression Test Added:**
- **Retest Status:**

---

## 10. Final UAT Summary

**Overall Manual UAT: IN PROGRESS**

**Completed:**
- Batch 1 — Foundation, Admin CRUD & Public Registration (26/26 PASS)
- Batch 2 — Teams & Match Setup (7/7 PASS)
- Batch 3 — Scoring, Realtime & Match Result (23/23 PASS)

**Cumulative manual result:** 56 PASS / 0 FAIL / 0 BLOCKED

**Pending:**
- Batch 4 — Public Website, Statistics & Standings
- Batch 5 — Finance, Contributions & Reports
- Batch 6 — Responsive, Privacy & Final Smoke

**Automated baseline (separate from manual UAT, unchanged):** 746 tests / 2562 assertions / 0 failures.

---

## 11. Future Regression Checklist

A short, high-value checklist to re-run manually after any future feature change or refactor — not a duplicate of every UAT case above, only the critical flows most likely to break silently:

- [ ] Authentication (admin/scorer login, guest redirect, logout)
- [ ] Public guest registration (image + PDF Aadhaar upload, duplicate handling)
- [ ] Private document access (admin can view, scorer/guest cannot)
- [ ] Registration payment verification (status/reference update)
- [ ] Match setup (creation, Edition Team/Playing XI prerequisites)
- [ ] Playing XI selection
- [ ] Ball-by-ball scoring (runs, extras, wicket + replacement batter)
- [ ] Undo latest delivery
- [ ] Innings completion / second innings start
- [ ] Match finalization and result locking
- [ ] Realtime public live update + polling fallback when Reverb is unavailable
- [ ] Standings/statistics reflect the latest completed match
- [ ] Finance ledger income/expense/balance
- [ ] Contribution recording and its single, non-duplicated ledger entry
- [ ] Public contributor privacy (no amount/phone/notes ever shown)
- [ ] Reports/exports/PDFs (Registration CSV, Finance CSV, Contribution CSV, Edition Summary PDF, Scorecard PDF, Financial Summary)
- [ ] Mobile scoring usability (375px admin scoring page)
- [ ] Authorization/privacy spot check (scorer vs admin vs guest)

---

## Documentation Maintenance Rule

After each UAT section/batch is manually completed:

1. Update the corresponding test statuses.
2. Record only real failures/observations — never invented or assumed results.
3. Add an issue ID (`ISSUE-UAT-XXX`) for every failure.
4. Record fixes separately (in commit history / the relevant phase report), not by silently editing the original test entry.
5. After a fix is applied and retested, record the Retest Status on the issue entry.
6. Never change a status from **NOT TESTED** to **PASS** merely because the automated PHPUnit suite passes — automated and manual verification are tracked independently.
7. Keep the automated test baseline (top of this document) updated separately from manual UAT status — they change on different schedules and for different reasons.
