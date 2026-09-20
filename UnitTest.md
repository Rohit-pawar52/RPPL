# RPPL Testing & UAT Documentation

**Last Updated:** 2026-09-19
**Automated Baseline:** 746 tests passing, 2562 assertions, 0 failures (PHPUnit, `tests/`)
**Manual UAT Status:** IN PROGRESS — Batches 1–4 complete

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

## How to Execute This Checklist

For every item currently marked `NOT TESTED`:

- **PASS** — change the status to `PASS` only after manually verifying the actual behavior matches the stated "Expected" result.
- **FAIL** — leave/set it as `FAIL` and create a matching entry in [Issues Found During UAT](#10-issues-found-during-uat).
- **BLOCKED** — use when another defect, missing precondition, or environment problem prevents the test from actually being run.
- **N/A** — use only when the test genuinely does not apply (e.g. an optional feature not configured in this environment).

**Never mark a test `PASS` just because a corresponding PHPUnit test exists or passes.** Automated and manual verification are tracked completely independently in this project — see the Documentation Maintenance Rule at the end of this document.

## Test Data Safety

- Use **dummy/non-sensitive files** for Aadhaar documents, payment proof screenshots, and any photo uploads during UAT. Never use a real Aadhaar document or real personal financial data.
- Use a **dedicated UAT Edition** (and dedicated UAT teams/players/venue) rather than testing against real historical tournament data where practical.
- Do **not** run destructive database reset commands (`migrate:fresh`, `db:wipe`, etc.) against an environment holding data you care about.

---

## Contents

1. [Environment / UAT Setup](#1-environment--uat-setup)
2. [Batch 1 — Foundation, Admin CRUD & Public Registration](#2-batch-1--foundation-admin-crud--public-registration)
3. [Batch 2 — Teams & Match Setup](#3-batch-2--teams--match-setup)
4. [Batch 3 — Scoring, Realtime & Match Result](#4-batch-3--scoring-realtime--match-result)
5. [Batch 4 — Public Website, Statistics & Standings](#5-batch-4--public-website-statistics--standings)
6. [Batch 5 — Finance, Contributions & Reports](#6-batch-5--finance-contributions--reports)
7. [Batch 6 — Responsive, Privacy & Final Smoke](#7-batch-6--responsive-privacy--final-smoke)
8. [Master Module Coverage Checklist](#8-master-module-coverage-checklist)
9. [Pre-UAT / Product Observations](#9-pre-uat--product-observations)
10. [Issues Found During UAT](#10-issues-found-during-uat)
11. [Final UAT Summary](#11-final-uat-summary)
12. [Future Regression Checklist](#12-future-regression-checklist)

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

**Status: COMPLETE — all 6 checks PASS**

### Statistics & Standings

#### UAT-STAT-01 — Edition standings reflect the completed match
**Area:** Standings · **Role:** Admin · **Status:** PASS

**Purpose:** Verify the completed UAT match is correctly reflected in the Edition standings, including win/loss and points information.

**Preconditions:** The UAT Match was finalized in Batch 3.

**Steps:**
1. Open the UAT Edition's admin show page.
2. Review the standings table.

**Expected:** The standings table reflects the finalized match's outcome, with the relevant win/loss and points information updated correctly for both teams.

**Result:** PASS

---

#### UAT-STAT-02 — Player statistics/leaderboard reflect the match
**Area:** Player Statistics · **Role:** Admin · **Status:** PASS

**Steps:**
1. Review the Edition's statistics/leaderboard data.

**Expected:** Runs, wickets, and any relevant records generated from the manually scored UAT match are reflected correctly.

**Result:** PASS

---

#### UAT-STAT-03 — Public Edition page matches admin standings/statistics
**Area:** Public Website · **Role:** Guest · **Status:** PASS

**Steps:**
1. Open the public Edition page for the UAT Edition.
2. Compare standings/statistics against the admin-side data.

**Expected:** The public Edition page displays standings/statistics consistently with the underlying completed match and the admin-side information — no discrepancy.

**Result:** PASS

### Public Directory / Website

#### UAT-PUB-01 — Public Players/Teams/Venues directories
**Area:** Public Website · **Role:** Guest · **Status:** PASS

**Steps:**
1. Open the public Players index and a UAT player's show page.
2. Open the public Teams index and a UAT team's show page.
3. Open the public Venues index and the UAT venue's show page.

**Expected:** All pages load successfully and display the relevant UAT entities; where photos/logos exist, they render correctly.

**Result:** PASS

---

#### UAT-PUB-02 — Public home page and registration navigation
**Area:** Public Website · **Role:** Guest · **Status:** PASS

**Steps:**
1. Open the public home page.
2. Use the Player Registration navigation/action.

**Expected:** The home page loads correctly and the Player Registration navigation/action works as expected.

**Result:** PASS

---

#### UAT-PUB-03 — Public Edition page quick mobile sanity check
**Area:** Public Website · **Role:** Guest · **Status:** PASS

**Purpose:** A quick 375px sanity check of the public Edition page — not full responsive certification, which remains covered by the dedicated Batch 6 responsive checks.

**Steps:**
1. View the public Edition page at a 375px mobile viewport.

**Expected:** No major broken layout or unusable horizontal-overflow problem.

**Result:** PASS

### Batch 4 Summary

| Area | Tests | Passed | Failed | Blocked | Notes |
|---|---|---|---|---|---|
| Statistics & Standings | 3 | 3 | 0 | 0 | — |
| Public Directory / Website | 3 | 3 | 0 | 0 | UAT-PUB-03 is a quick sanity check only; full responsive certification is in Batch 6 |
| **Total** | **6** | **6** | **0** | **0** | |

**Batch 4 Status: PASS**

---

## 6. Batch 5 — Finance, Contributions & Reports

**Status: NOT TESTED**

### Finance Ledger / Committee / Contributors

#### UAT-FIN-01 — Record income and expense transactions
**Area:** Finance Ledger · **Role:** Admin · **Status:** NOT TESTED

**Purpose:** Verify manual income and expense entries can be recorded against the UAT Edition.

**Steps:**
1. Open `admin.edition-transactions.create`.
2. Record one income transaction (edition, amount, category) for the UAT Edition.
3. Record one expense transaction the same way.

**Expected:** Both transactions appear in the Finance ledger index, correctly scoped to the UAT Edition, with correct type/amount.

**Result:** NOT TESTED

---

#### UAT-COMM-01 — Create a Committee Member
**Area:** Committee Members · **Role:** Admin · **Status:** NOT TESTED

**Steps:**
1. Open `admin.committee-members.create`.
2. Enter details and save.

**Expected:** Committee Member is created, appears in the index, and is selectable as a contribution source.

**Result:** NOT TESTED

---

#### UAT-CONTRIB-01 — Create a Contributor with a photo
**Area:** Contributors · **Role:** Admin · **Status:** NOT TESTED

**Steps:**
1. Open `admin.contributors.create`.
2. Enter details and upload a photo.
3. Save and view the show page.

**Expected:** Contributor is created; the uploaded photo renders correctly on the admin show/edit page.

**Result:** NOT TESTED

---

#### UAT-CONTRIB-02 — Committee contribution at/above the enforced minimum
**Area:** Contributions · **Role:** Admin · **Status:** NOT TESTED

**Preconditions:** The Committee Member from UAT-COMM-01 exists.

**Steps:**
1. Open `admin.edition-contributions.create`.
2. Select the Committee Member as source, an amount at/above the enforced committee minimum, and the UAT Edition.
3. Save.

**Expected:** Contribution is recorded, and a matching income transaction is automatically created in the Finance ledger for the same amount.

**Result:** NOT TESTED

---

#### UAT-CONTRIB-03 — General contributor contribution
**Area:** Contributions · **Role:** Admin · **Status:** NOT TESTED

**Preconditions:** The Contributor from UAT-CONTRIB-01 exists.

**Steps:**
1. Record a contribution against the general Contributor, any positive amount, for the UAT Edition.

**Expected:** Contribution is recorded with its own matching income transaction.

**Result:** NOT TESTED

### Public Contributor Leaderboard & Receipt

#### UAT-LEAD-01 — Leaderboard photo and initials fallback
**Area:** Public Contributor Leaderboard · **Role:** Guest · **Status:** NOT TESTED

**Preconditions:** UAT-CONTRIB-01/02/03 completed; at least one other contributor with no photo also has a contribution on the UAT Edition.

**Steps:**
1. Open the public UAT Edition page's contributor leaderboard section.

**Expected:** The photographed contributor shows their actual circular photo; a contributor without a photo shows an initials avatar instead.

**Result:** NOT TESTED

---

#### UAT-LEAD-02 — Badge wording and amount privacy
**Area:** Public Contributor Leaderboard · **Role:** Guest · **Status:** NOT TESTED

**Steps:**
1. Inspect the leaderboard's badge label for the top-ranked contributor(s).
2. View page source for the leaderboard section.

**Expected:** Badge wording matches the application's defined recognition tiers (e.g. "Top Contributor"); no contribution amount, phone number, or notes appear anywhere in the rendered HTML.

**Result:** NOT TESTED

---

#### UAT-RCPT-01 — Contribution receipt HTML and PDF
**Area:** Contribution Receipt · **Role:** Admin · **Status:** NOT TESTED

**Steps:**
1. Open the HTML receipt preview for a UAT contribution.
2. Download the receipt PDF for the same contribution.

**Expected:** Both the HTML preview and the PDF show the correct amount/date/contributor name and agree with each other.

**Result:** NOT TESTED

### Finance/Contribution Cross-Checks

#### UAT-FINX-01 — Ledger income includes each contribution exactly once
**Area:** Finance/Contribution Consistency · **Role:** Admin · **Status:** NOT TESTED

**Preconditions:** UAT-FIN-01, UAT-CONTRIB-02, and UAT-CONTRIB-03 completed.

**Steps:**
1. Compare the Finance ledger's total income for the UAT Edition against the manual income entry plus both contribution amounts.

**Expected:** Each contribution appears in the ledger exactly once (as its own income transaction); no double-counting.

**Result:** NOT TESTED

---

#### UAT-FINX-02 — Dashboard Finance card matches the ledger
**Area:** Dashboard · **Role:** Admin · **Status:** NOT TESTED

**Steps:**
1. Compare the Dashboard's Finance card (income/expense/balance) against the Finance ledger page for the same Edition.

**Expected:** Figures match exactly.

**Result:** NOT TESTED

---

#### UAT-FINX-03 — Dashboard Contributions card shows the "already included" note
**Area:** Dashboard · **Role:** Admin · **Status:** NOT TESTED

**Steps:**
1. View the Dashboard's Contributions card for the UAT Edition.

**Expected:** Total matches the Contributions page total; the card visibly notes that this total is already included within Finance income (not additional money on top).

**Result:** NOT TESTED

---

#### UAT-FINX-04 — Public leaderboard hides amount; admin Contributions page shows it
**Area:** Finance/Contribution Consistency · **Role:** Admin + Guest · **Status:** NOT TESTED

**Steps:**
1. Note the amount for a UAT contribution on the admin Contributions page.
2. Compare against the same contributor's presentation on the public leaderboard.

**Expected:** The admin page shows the actual amount; the public leaderboard never shows any amount for the same contributor.

**Result:** NOT TESTED

### Dashboard & Reports

#### UAT-DASH-01 — Pending-verification banner link
**Area:** Dashboard · **Role:** Admin · **Status:** NOT TESTED

**Preconditions:** At least one registration with `payment_status = pending` exists for the current Dashboard Edition.

**Steps:**
1. Open the Dashboard.
2. Click the pending-verification banner/link.

**Expected:** Lands on the Player Registrations index, pre-filtered to the correct Edition and `payment_status = pending`.

**Result:** NOT TESTED

---

#### UAT-DASH-02 — Registration Payments paid amount uses real stored fees
**Area:** Dashboard · **Role:** Admin · **Status:** NOT TESTED

**Preconditions:** At least two paid registrations exist with different stored `registration_fee` values.

**Steps:**
1. Compare the Dashboard's "Paid Amount" figure against the sum of the actual stored `registration_fee` values on paid registrations for the Edition.

**Expected:** Figure reflects the real stored fees, not the edition's current fee multiplied by the paid count.

**Result:** NOT TESTED

---

#### UAT-REPORT-01 — Registration CSV
**Area:** Reports · **Role:** Admin · **Status:** NOT TESTED

**Steps:**
1. On `admin/reports` for the UAT Edition, download the Registration CSV.
2. Open the file.

**Expected:** File downloads and opens correctly with sensible headers/rows matching the UAT registrations.

**Result:** NOT TESTED

---

#### UAT-REPORT-02 — Finance CSV
**Area:** Reports · **Role:** Admin · **Status:** NOT TESTED

**Steps:**
1. Download the Finance CSV for the UAT Edition.

**Expected:** File downloads and opens correctly, including the manual and contribution-linked transactions, each correctly labeled by source.

**Result:** NOT TESTED

---

#### UAT-REPORT-03 — Contribution CSV
**Area:** Reports · **Role:** Admin · **Status:** NOT TESTED

**Steps:**
1. Download the Contribution CSV for the UAT Edition.

**Expected:** File downloads and opens correctly with reference/contributor-name/source/amount/date/notes/recorded-by/transaction-id columns; no phone column.

**Result:** NOT TESTED

---

#### UAT-REPORT-04 — Edition Summary PDF
**Area:** Reports · **Role:** Admin · **Status:** NOT TESTED

**Steps:**
1. Download the Edition Summary PDF from Reports for the UAT Edition.

**Expected:** Opens cleanly, not clipped, and includes overview/registrations/teams/matches/standings/statistics sections.

**Result:** NOT TESTED

---

#### UAT-REPORT-05 — Financial Summary print preview
**Area:** Reports · **Role:** Admin · **Status:** NOT TESTED

**Steps:**
1. Open the Financial Summary page for the UAT Edition.
2. Open the browser's print preview for the page.

**Expected:** Renders cleanly both on screen and in print preview; figures are readable and not cut off.

**Result:** NOT TESTED

---

#### UAT-REPORT-06 — Reports Edition selector updates all figures
**Area:** Reports · **Role:** Admin · **Status:** NOT TESTED

**Steps:**
1. On `admin/reports`, change the Edition selector to a different edition.

**Expected:** Every link and figure on the page updates to reflect the newly selected edition.

**Result:** NOT TESTED

### Batch 5 Summary

| Area | Tests | Passed | Failed | Blocked | Not Tested |
|---|---|---|---|---|---|
| Finance Ledger / Committee / Contributors | 5 | 0 | 0 | 0 | 5 |
| Public Contributor Leaderboard & Receipt | 3 | 0 | 0 | 0 | 3 |
| Finance/Contribution Cross-Checks | 4 | 0 | 0 | 0 | 4 |
| Dashboard & Reports | 8 | 0 | 0 | 0 | 8 |
| **Total** | **20** | **0** | **0** | **0** | **20** |

**Batch 5 Status: NOT TESTED**

---

## 7. Batch 6 — Responsive, Privacy & Final Smoke

**Status: NOT TESTED**

### Cancellation / Abandonment

#### UAT-CANCEL-01 — Cancel a scheduled match before scoring
**Area:** Match Lifecycle · **Role:** Admin · **Status:** NOT TESTED

**Purpose:** Verify a scheduled match (before toss/scoring) can be cleanly cancelled.

**Preconditions:** Use a disposable throwaway match — do not use the main UAT match already scored in Batch 3.

**Steps:**
1. Create a new scheduled match.
2. Cancel it before starting toss.

**Expected:** Status becomes "cancelled"; no scoring data is created; the cancelled match cannot continue through the normal toss/scoring workflow; public/admin presentation of the match remains sensible (clearly shown as cancelled, not as an active fixture).

**Result:** NOT TESTED

---

#### UAT-CANCEL-02 — Abandon a live match after at least one delivery
**Area:** Match Lifecycle · **Role:** Admin/Scorer · **Status:** NOT TESTED

**Preconditions:** Use a separate disposable match — do not use the main UAT match.

**Steps:**
1. Create a match, complete toss, start it, and record at least one delivery.
2. Abandon the match.

**Expected:** Status becomes "abandoned"; the delivery/scoring history already recorded remains preserved and visible; further scoring is blocked; public/admin status presentation remains sensible.

**Result:** NOT TESTED

### Responsive / Mobile

*Representative widths only — not every page needs all three: 375px mobile, 768px tablet, 1366px desktop.*

#### UAT-RESP-01 — Public Home / Edition pages
**Area:** Responsive · **Role:** Guest · **Status:** NOT TESTED

**Steps:** View the public Home and Edition pages at 375px, 768px, and 1366px.

**Expected:** No horizontal overflow or broken layout at any of the three widths.

**Result:** NOT TESTED

---

#### UAT-RESP-02 — Public Player Registration
**Area:** Responsive · **Role:** Guest · **Status:** NOT TESTED

**Steps:** View the public registration form at 375px; interact with text fields and file pickers.

**Expected:** Form does not overflow; file pickers are usable on a small screen.

**Result:** NOT TESTED

---

#### UAT-RESP-03 — Registration Status lookup
**Area:** Responsive · **Role:** Guest · **Status:** NOT TESTED

**Steps:** View the public status-lookup form at 375px.

**Expected:** Form is usable, no overflow.

**Result:** NOT TESTED

---

#### UAT-RESP-04 — Admin Dashboard
**Area:** Responsive · **Role:** Admin · **Status:** NOT TESTED

**Steps:** View the admin Dashboard at 375px.

**Expected:** Cards stack sensibly; no overlapping text or unusable controls.

**Result:** NOT TESTED

---

#### UAT-RESP-05 — Admin/Scorer live scoring page *(high priority)*
**Area:** Responsive · **Role:** Admin/Scorer · **Status:** NOT TESTED

**Purpose:** This is the page a scorer is most likely to use on a phone/tablet at the actual ground — highest practical priority of the responsive checks.

**Steps:** View the delivery-recording form at 375px and 768px.

**Expected:** Selects/buttons/checkboxes on the scoring form remain usable at both widths; no control is cut off or impossible to tap.

**Result:** NOT TESTED

---

#### UAT-RESP-06 — Public Live Score page
**Area:** Responsive · **Role:** Guest · **Status:** NOT TESTED

**Steps:** View the public live match page at 375px.

**Expected:** Score/innings/recent-deliveries sections remain usable and readable.

**Result:** NOT TESTED

---

#### UAT-RESP-07 — Public Contributor leaderboard
**Area:** Responsive · **Role:** Guest · **Status:** NOT TESTED

**Steps:** View the leaderboard grid at 375px.

**Expected:** Cards wrap to fewer columns cleanly; no overflow.

**Result:** NOT TESTED

---

#### UAT-RESP-08 — Admin Reports page
**Area:** Responsive · **Role:** Admin · **Status:** NOT TESTED

**Steps:** View `admin/reports` at 375px.

**Expected:** Report rows/links remain usable, no horizontal overflow.

**Result:** NOT TESTED

### User & Scorer Management

*Not covered by any existing batch — added as its own subsection. Only behaviors confirmed to exist in the current codebase (`UserController`, `UpdateUserRequest`) are included.*

#### UAT-USER-01 — Admin creates a scorer account
**Area:** User Management · **Role:** Admin · **Status:** NOT TESTED

**Purpose:** Verify an admin can create a new user account with the Scorer role and that it receives scorer-level (not admin-level) access.

**Steps:**
1. Open `admin.users.create`.
2. Create a new account with role = Scorer.
3. Log in as that account.

**Expected:** Account is created and active immediately; logging in as it grants scorer-level navigation/access (Dashboard + Matches only), not admin-level access.

**Result:** NOT TESTED

---

#### UAT-USER-02 — Inactive user cannot authenticate
**Area:** User Management · **Role:** Admin (setup) / the deactivated user (test) · **Status:** NOT TESTED

**Steps:**
1. Deactivate a non-admin test user account (`is_active = false`) via `admin.users.edit`.
2. Attempt to log in as that account.

**Expected:** Login fails the same way an incorrect password would (no distinguishable "account disabled" message); the account cannot authenticate while inactive.

**Result:** NOT TESTED

---

#### UAT-USER-03 — Admin self-lockout and last-active-admin safeguard
**Area:** User Management · **Role:** Admin · **Status:** NOT TESTED

**Purpose:** Verify the application prevents an admin from removing their own admin access, and prevents deactivating/demoting the last remaining active admin account.

**Steps:**
1. While logged in as an admin, attempt to deactivate your own account or change your own role away from Admin.
2. With only one active admin account in the system, attempt to deactivate or demote that account from a different admin session (or after temporarily creating a second admin to test the general rule, then reducing back to one).

**Expected:** Both attempts are rejected with a validation error (cannot deactivate/demote your own account; at least one active administrator account must remain in the system).

**Result:** NOT TESTED

### Security/Privacy Spot Check

#### UAT-SEC-01 — Scorer authorization spot check
**Area:** Authorization · **Role:** Scorer · **Status:** NOT TESTED

**Steps:**
1. As scorer, attempt to access `admin/reports`, `admin/edition-transactions`, `admin/edition-contributions`, and `admin/users`.
2. As scorer, access Matches/scoring pages.

**Expected:** Scorer retains intended match/scoring access; admin-only areas (finance, contributions, reports, user management) remain protected (403) according to current policies.

**Result:** NOT TESTED

---

#### UAT-SEC-02 — Guest/admin-route/private-document protection
**Area:** Authorization / Privacy · **Role:** Guest · **Status:** NOT TESTED

**Steps:**
1. While logged out, attempt to open an admin route directly.
2. Attempt to open an Aadhaar/payment-proof document URL directly.

**Expected:** Admin routes require authentication (redirect to login); Aadhaar/payment-proof documents cannot be accessed publicly — a logged-out user never receives the private document.

**Result:** NOT TESTED

---

#### UAT-SEC-03 — Public-source privacy spot check
**Area:** Privacy · **Role:** Guest · **Status:** NOT TESTED

**Steps:**
1. Inspect the rendered HTML/page source of the public registration-status result and the contributor leaderboard.

**Expected:** No hidden inputs, `data-*` attributes, HTML comments, embedded JS, or URLs leak `payment_reference`, private document paths, contributor phone numbers, intentionally-hidden contribution amounts, or other admin-only financial data.

**Result:** NOT TESTED

### Final Smoke

#### UAT-SMOKE-01 — Uninterrupted admin navigation walkthrough
**Area:** Final Smoke · **Role:** Admin · **Status:** NOT TESTED

**Steps:**
1. In one session, navigate: Dashboard → Reports → Edition → Registrations → Teams → Match → Scorecard → Dashboard.

**Expected:** Navigation works throughout; active-navigation highlighting makes sense; no broken links; no obvious browser console errors; no unexpected 500 errors.

**Result:** NOT TESTED

### Batch 6 Summary

| Area | Tests | Passed | Failed | Blocked | Not Tested |
|---|---|---|---|---|---|
| Cancellation / Abandonment | 2 | 0 | 0 | 0 | 2 |
| Responsive / Mobile | 8 | 0 | 0 | 0 | 8 |
| User & Scorer Management | 3 | 0 | 0 | 0 | 3 |
| Security/Privacy Spot Check | 3 | 0 | 0 | 0 | 3 |
| Final Smoke | 1 | 0 | 0 | 0 | 1 |
| **Total** | **17** | **0** | **0** | **0** | **17** |

**Batch 6 Status: NOT TESTED**

---

## 8. Master Module Coverage Checklist

This is **not** a separate set of test results — it is an index showing which detailed UAT entries above cover each current application module, so the whole application's coverage can be seen at a glance.

| Module | Covered by |
|---|---|
| Authentication | UAT-AUTH-01 – 05 |
| User / Scorer management | UAT-USER-01 – 03 |
| Editions | UAT-ED-01 – 03 |
| Players | UAT-PLY-01, UAT-PLY-02 |
| Public registration | UAT-REG-01 – 05 |
| Registration status lookup | UAT-STATUS-01 – 04 |
| Registration review (admin) | UAT-REGADM-01 – 06 |
| Registration CSV import | UAT-IMPORT-01 |
| Registration CSV export | UAT-REPORT-01 |
| Teams | UAT-TEAM-01 |
| Edition Teams | UAT-TEAM-02 |
| Squads | UAT-TEAM-03 |
| Venues | UAT-VEN-01 |
| Matches | UAT-MATCH-01 |
| Playing XI | UAT-XI-01, UAT-XI-02 |
| Toss | UAT-TOSS-01, UAT-TOSS-02 |
| Innings | UAT-INN-01 – 03 |
| Ball-by-ball scoring | UAT-SCORE-01 – 08 |
| Undo delivery | UAT-SCORE-07, UAT-SCORE-08 |
| Realtime (Reverb + polling) | UAT-LIVE-01 – 04 |
| Public live match page | UAT-LIVE-01 – 04, UAT-RESP-06 |
| Match result | UAT-RESULT-01 – 03 |
| Scorecard (admin/public) | UAT-SCC-01, UAT-SCC-02 |
| Scorecard PDF | UAT-SCC-03 |
| Player statistics | UAT-STAT-02 |
| Standings | UAT-STAT-01, UAT-STAT-03 |
| Public Players | UAT-PUB-01 |
| Public Teams | UAT-PUB-01 |
| Public Venues | UAT-PUB-01 |
| Finance | UAT-FIN-01, UAT-FINX-01, UAT-FINX-02 |
| Committee Members | UAT-COMM-01 |
| Contributors | UAT-CONTRIB-01 |
| Contributions | UAT-CONTRIB-02, UAT-CONTRIB-03 |
| Contribution leaderboard | UAT-LEAD-01, UAT-LEAD-02, UAT-FINX-04 |
| Contribution receipt | UAT-RCPT-01 |
| Dashboard | UAT-DASH-01, UAT-DASH-02, UAT-FINX-02, UAT-FINX-03 |
| Reports (hub) | UAT-REPORT-01 – 06 |
| Finance CSV | UAT-REPORT-02 |
| Contribution CSV | UAT-REPORT-03 |
| Edition Summary PDF | UAT-REPORT-04 |
| Financial Summary | UAT-REPORT-05 |
| Cancellation | UAT-CANCEL-01 |
| Abandonment | UAT-CANCEL-02 |
| Authorization | UAT-SEC-01, UAT-REGADM-04 |
| Privacy | UAT-SEC-02, UAT-SEC-03, UAT-STATUS-03, UAT-LEAD-02, UAT-REGADM-05 |
| Responsive/mobile | UAT-RESP-01 – 08, UAT-ED-03, UAT-REG-04, UAT-PUB-03 |

### File Upload Coverage

| Upload | Covered by |
|---|---|
| Player photo | UAT-PLY-01 |
| Team logo | UAT-TEAM-01 |
| Contributor photo | UAT-CONTRIB-01 |
| Aadhaar image | UAT-REG-01 |
| Aadhaar PDF | UAT-REG-02 |
| Payment proof screenshot | UAT-REG-01 |

### Download / Export Coverage

| Export/Download | Covered by |
|---|---|
| Registration CSV | UAT-REPORT-01 |
| Finance CSV | UAT-REPORT-02 |
| Contribution CSV | UAT-REPORT-03 |
| Match Scorecard PDF | UAT-SCC-03 |
| Edition Summary PDF | UAT-REPORT-04 |
| Contribution Receipt PDF | UAT-RCPT-01 |
| Financial Summary (print preview, no PDF exists) | UAT-REPORT-05 |

### Critical Business Consistency Checks

| Invariant | Covered by |
|---|---|
| Registration paid amount uses actual stored `registration_fee` values | UAT-DASH-02 |
| Contributions enter Finance income exactly once | UAT-FINX-01 |
| Contribution total is never added again on top of Finance balance | UAT-FINX-03 |
| Public contributor amount remains hidden | UAT-LEAD-02, UAT-FINX-04 |
| Admin contribution amount remains visible | UAT-FINX-04 |
| Private player-registration documents remain private | UAT-REGADM-04, UAT-REGADM-05, UAT-SEC-02 |
| Completed match cannot continue scoring | UAT-RESULT-03 |
| Cancelled/abandoned matches cannot continue normal scoring | UAT-CANCEL-01, UAT-CANCEL-02 |
| Public stats/standings reflect completed match data | UAT-STAT-01, UAT-STAT-03 |

---

## 9. Pre-UAT / Product Observations

*Product / deployment observations — not current UAT failures.*

1. New admin/scorer accounts currently use `is_active` rather than a separate pending-approval workflow — a newly created account is active immediately upon creation.
2. The first admin account requires deployment/bootstrap creation (e.g. via `php artisan tinker`) rather than any form of public self-registration or setup wizard.
3. Match start currently does not enforce a full 11-player minimum; existing behavior permits smaller configured squads/playing groups (at least one selected player per side is sufficient to start a match).
4. `QUEUE_CONNECTION` may be configured (`database`) even though current application functionality does not require a queue worker to run.

---

## 10. Issues Found During UAT

*No issues currently logged.*

**Intended workflow when a real failure is found:**
1. Identify the failing UAT ID.
2. Record the actual behavior observed (not a guess).
3. Assign a severity (BLOCKER/HIGH/MEDIUM/LOW).
4. Fix the application.
5. Add or update an automated regression test where it would add real value.
6. Retest the UAT case manually.
7. Record the Retest Status on the issue entry.
8. Only then update the corresponding UAT entry's final status.

Use the template below for every failure found:

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

## 11. Final UAT Summary

**Overall Manual UAT: IN PROGRESS**

This document now contains the complete master checklist for the current application (all 6 batches fully written out). Batches 1–4 have been manually executed; Batches 5–6 are documented and ready to execute but have **not** yet been run.

**Completed (manually executed):**
- Batch 1 — Foundation, Admin CRUD & Public Registration (26/26 PASS)
- Batch 2 — Teams & Match Setup (7/7 PASS)
- Batch 3 — Scoring, Realtime & Match Result (23/23 PASS)
- Batch 4 — Public Website, Statistics & Standings (6/6 PASS)

**Documented, not yet executed:**
- Batch 5 — Finance, Contributions & Reports (0/20 tested)
- Batch 6 — Responsive, Privacy & Final Smoke (0/17 tested)

**Confirmed Manual PASS:** 62
**Confirmed FAIL:** 0
**Confirmed BLOCKED:** 0
**Remaining (NOT TESTED):** 37 (20 in Batch 5 + 17 in Batch 6)
**Total documented manual tests:** 99

**Automated baseline (separate from manual UAT, unchanged):** 746 tests / 2562 assertions / 0 failures.

---

## 12. Future Regression Checklist

A short, high-value checklist to re-run manually after any future feature change or refactor — not a duplicate of every UAT case above, only the critical flows most likely to break silently:

- [ ] Authentication (admin/scorer login, guest redirect, logout)
- [ ] Admin/scorer account lifecycle (creation, deactivation, self-lockout and last-active-admin safeguard)
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

## 13. Stakeholder Demo Dataset

`php artisan migrate:fresh --seed` now seeds a complete, internally-consistent stakeholder demo dataset (3 editions, ~45 players, 6 teams, 3 venues, registrations/squads, matches across every lifecycle state with real ball-by-ball scoring, committee/contributor finance) instead of the old `RpplDemoSeeder` placeholder data. See `README.md`'s "Demo data" section for demo login credentials and what the dataset contains. This is separate from, and does not change, the manual UAT results recorded above (62 PASS / 37 NOT TESTED / 99 total) — the demo dataset is for stakeholder walkthroughs, not a substitute for UAT execution.

---

## 14. Push Notifications (Firebase) — Manual Check

A separate module-specific checklist, added after the project's 62/37/99 UAT accounting above was finalized — it does **not** change or add to that count. Automated coverage (818 tests as of Phase B4) already proves the backend/queue/authorization logic without real Firebase; the items below are the real-browser/real-send steps that automated tests structurally cannot cover.

#### UAT-FCM-01 — Guest can enable notifications
**Area:** Public / Push Notifications · **Role:** Guest · **Status:** NOT TESTED

**Steps:** Visit the public site with a real Firebase Web config configured; click "Enable Notifications"; accept the browser permission prompt.

**Expected:** Permission is requested only after the click (never automatically); control becomes "Notifications On"; exactly one `fcm_tokens` row exists for the device with `is_active=true`, `user_id`/`player_id` null, `last_seen_at` populated. Reloading the page does not create a duplicate row.

**Result:** NOT TESTED — requires a real Firebase Web config, not configured in this environment as of Phase B5 (see the Phase B5 report).

#### UAT-FCM-02 — Admin creates and sends a notification
**Area:** Admin / Push Notifications · **Role:** Admin · **Status:** NOT TESTED

**Steps:** Create a notification via `admin.notifications.create`; click Send; wait for the queue worker to process it.

**Expected:** A `NotificationSend` row appears with `attempted_count = accepted_count + failure_count`, `completed_at` populated; the real subscribed device receives a push (foreground: in-page notification; background: OS/browser notification).

**Result:** NOT TESTED — requires a real Firebase service-account credential and a real subscribed device; not configured in this environment as of Phase B5.

#### UAT-FCM-03 — Notification click opens the correct page
**Area:** Public / Push Notifications · **Role:** Guest · **Status:** NOT TESTED

**Steps:** Send a notification with an internal `action_url` (e.g. `/player-registration`); click the resulting browser/OS notification.

**Expected:** An existing RPPL tab is focused and navigated, or a new tab opens, to that internal path; never an external origin.

**Result:** NOT TESTED — requires the same real device as UAT-FCM-01/02.

#### UAT-FCM-04 — Resend preserves prior send history
**Area:** Admin / Push Notifications · **Role:** Admin · **Status:** PASS (automated)

**Expected:** Editing a notification's content after a Send, then clicking Resend, creates a second, independent `NotificationSend` snapshot without altering the first — proven by `tests/Feature/Admin/NotificationSendTest.php`.

**Result:** PASS — covered by automated test, not a live-Firebase concern; listed here for completeness of the module's checklist only.

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
