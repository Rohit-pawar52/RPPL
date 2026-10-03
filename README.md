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

- **Editions** — a tournament season (name, year, status: upcoming/active/completed, public registration toggle + fee, and an optional registration opening/closing date-time window), with a PDF edition report.
- **Teams** and **Venues** — reusable master data (with team logo / player photo uploads on the public disk), active/inactive without deleting historical usage.
- **Players** — master player directory (name, phone, email, DOB, primary role, batting/bowling style, photo), independent of any one edition.
- **Edition Teams** — which teams participate in a given edition (create/view/delete only — no free-form editing of a participation record's own identity).
- **Squads (Team Players)** — assigning a player's edition registration to a specific team/edition, with jersey numbers.

### Match lifecycle & ball-by-ball scoring

- Full match lifecycle as explicit actions (never a generic status field the client can drive arbitrarily): schedule a match → set the playing XI → start toss → record toss → start match → play innings → finalize result — plus **cancel**/**abandon** at any point.
- Two-innings model with explicit innings start/complete actions.
- Frozen tournament cricket scoring rules: explicit Start Innings/New Batter/New Over Bowler/Mid-Over Bowler Change steps, Free Hit tracking, Retired Hurt/Out, Change Strike, standard Penalty Runs, and a "same bowler can't bowl two overs in a row" guard.
- **One-click match-day scoring pad** (0/1/2/3/4/6/Wd/Nb/W) with a live This Over/Previous Over strip, current batter and bowler figures, current partnership, last wicket, and (during a chase) target/runs needed/required run rate — all derived live, nothing double-stored. Ball submissions are duplicate-safe (an accidental double-tap or a retried request after a dropped connection can never record the same ball twice) and the screen recovers its exact state after a refresh or reconnect.
- **Universal Undo** reverses whichever scoring action was most recent — a delivery, a strike correction, a retirement, a bowler/batter selection, or a penalty-runs award — not only the last ball, while always preserving who undid what and when.
- **Quick correction** of any of the latest 3 deliveries (runs, extras, wicket details, commentary) with a full before/after audit trail; older deliveries require the innings to be reopened first.
- Match result is always server-derived from completed innings totals — never chosen by the admin/scorer.
- Read-only match scorecard (admin and public), plus a **downloadable PDF scorecard**.
- **Real-time live scoring** on the public match page via Laravel Reverb (WebSocket broadcasting) + Laravel Echo — a `MatchScoreUpdated` event fires after each committed scoring action and the public "live" view updates without a page refresh; falls back to polling (`live-data`) if a socket connection isn't available. Corrections made after the fact simply refresh the public score to the corrected figure.
- **Optional match reminder push notification** — an admin may enable "Send reminder before match" (a configurable minutes-before, default 30) on any match. The due instant is always *derived* as `scheduled_at` minus that many minutes, never stored separately, so rescheduling the match before the reminder fires automatically moves it with no special handling needed. Only ever considered for an upcoming (`scheduled`/`toss`) match whose start time hasn't already passed — cancelling, abandoning, completing, or starting the match makes an unsent reminder permanently ineligible. Picked up by `rppl:dispatch-match-reminders` (see Scheduled tasks) via the same atomic-claim + existing-notification-pipeline pattern scheduled announcements use.

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

### Admin panel navigation

- The admin sidebar is grouped into "Management" sections — Tournament, Player, Content, Finance, Communication and System — each expanding to its own pages (for example Content Management → News, Videos, Photos, Advertisements, Announcements, Rules & Regulations, Content Pages), with Dashboard, Matches and Reports above them. The group holding the current page opens automatically and the current page is highlighted, including on create/edit/detail pages and filtered lists. The whole menu is defined in one place, `config/admin_navigation.php`; adding or moving a page never means editing Blade.
- Scorers only see Dashboard and Matches; everything else is admin-only (backend policies remain the real boundary). On a desktop the sidebar can collapse to an icon rail (remembered in the browser); on phones and tablets it is a drawer. The top bar shows where you are ("Group / Page"), a View site link and the account menu with Logout.
- Admin pages share one header pattern (title, one-line description, action buttons) and shared form, button, empty-state and status components; forms lock their submit button while saving to prevent double submits.

### Public page-view tracking and Analytics

- The public site quietly records visits to three detail pages — a **match** (`/matches/{id}`, the Match Info page only, not its scorecard, squads or live tabs), an **edition** (`/editions/{id}`) and a **player profile** (`/players/{id}`) — into the `page_views` table, ready for a future admin Analytics page. Nothing reads them back yet, and no public page changes.
- Only a successful browser visit counts: lists, the homepage, 404s and redirects, HEAD/AJAX/prefetch requests, the live-score polling endpoint and WebSocket traffic never do, and neither do logged-in admins/scorers or (best-effort) bots.
- Visitors are told apart by a first-party random cookie, `rppl_vid` (about 2 years); only a keyed hash of it is stored, never the cookie value or the IP address. The same visitor opening the same match, edition or player again within 30 minutes counts once (a player page's `?edition_id=`/`?page=` links are the same player). Each row stores the time in UTC plus the date and hour in the configured display timezone at that moment.
- Recording happens after the page has been sent and is best-effort: a failure is logged and never affects the visitor. **Privacy:** this adds a persistent analytics cookie, so mention it in the Privacy Policy page (editable under Admin → Content Pages).
- **Admin → Analytics** (admin-only, between Reports and the Management groups) reads those records back: for a chosen date range (today, yesterday, last 7 / 30 days or a custom range, in the display timezone) it shows total views, unique visitors, the match / edition / player split, daily and hour-of-day tables, and paginated most-viewed matches, editions and players, optionally narrowed to one content type. A deleted match, edition or player stays in the numbers and is listed as "Deleted …". Only counts are shown — never a visitor identifier. Retention, rollups and tracking settings are not built yet.

### Public website design

- The whole public site shares one design: a deep navy header and footer, a light blue-grey page, white cards, green for live/active states and slate for secondary text, with compact spacing and no large banners. A small set of shared styles and components (cards, buttons, status pills, tabs, responsive tables) keeps every page consistent.
- Matches read like a live-cricket platform: a live match shows a green "LIVE" card on the homepage and a match page with the score, chase info, colour-coded ball-by-ball commentary (4 / 6 / wicket / extras) and a Match Details panel; scorecards, squads and match info share one header with tabs. Everything shown comes from existing match data.
- Fully responsive: the header becomes a single menu on phones and tablets, tables scroll sideways instead of breaking the page, and pages (including the live page) are laid out for a phone rather than just stacked. English and Hindi are unchanged.

### Public player registration (guest, no login)

A self-serve replacement for collecting player registrations via a form, entirely on the public site:

- **Submission** — a guest answers the questions of the Season 3 Google Form: name, age (5–99), mobile number, optional email, role, batting hand and bowling arm, village (gram), tehsil and district, a photo, and the registration payment — a screenshot of the payment they made after scanning the QR code, plus, optionally, the UTR / transaction ID. Everything except the email, the age and the UTR is required (the Google Form only required the name), the mobile number must be a valid 10-digit Indian number (+91 and spaces are cleaned up) and the UTR a plain 8–30 character letters-and-numbers id; Aadhaar and date of birth are no longer asked. A unique `RPPL-{year}-{id}` registration number is issued. The **mobile number is the identity**: a number that already belongs to a player reuses that player (never overwriting their existing details — what the player answered for this edition is kept on the registration), while an email that belongs to someone else can neither merge two people nor block a registration. Inactive players and duplicate same-edition registrations are rejected without revealing *why* (no enumeration). Registration is only accepted while an edition has both `registration_open` and a configured fee — at most one edition can be open for registration at a time. An admin may additionally set an optional **registration period** (opens at / closes at, entered and shown in `system.display_timezone`): before the opening time the page says registration hasn't opened yet (with the opening date), inside the window the form shows its closing date, and after the closing time registration is closed — all enforced server-side, including on submission. Editions with no dates set behave exactly as before.
- **Registration closing reminder** — optionally, one push notification ("RPPL Registration Closing Soon") a configurable number of minutes before registration closes (default 1440 = 24 hours). Its due time is derived from the closing time, so moving the deadline before it fires moves the reminder too; it is never sent once registration has already closed (e.g. after scheduler downtime), and once sent it is never re-sent even if the deadline later changes. Picked up by `rppl:dispatch-registration-closing-reminders` via the same atomic-claim + existing-notification-pipeline pattern as match reminders.
- **Paying** — the form shows the amount from the edition and, when the admin has set them under **Settings → Payments → UPI payment details**, the UPI QR code and UPI ID (with a Copy button, and on phones a "Pay with a UPI app" button, since a QR code can't be scanned from the same screen). Without them it shows only the general payment instructions.
- **Uploads** — the photo (JPEG/PNG/WebP) and the payment screenshot (JPEG/PNG) may be up to 10 MB each, like the Google Form, but never more than the server can really accept: the limit shown on the form, checked in the browser before a slow upload, and enforced on the server is cut down to what PHP's `upload_max_filesize` / `post_max_size` allow, so a player is told "up to 4.5 MB" instead of meeting a bare error page. Fields keep what was typed after an error, and the submit button locks while the upload is in progress.
- **Private files** — the photo and the payment screenshot (and any Aadhaar document from before) are stored on private disk storage (never public URLs) and are only ever served back through an authenticated, policy-checked admin route that reads the stored path from the database row itself (never from a request parameter). The submitted photo is never shown on the public site by itself.
- **Admin review & payment verification** — the existing admin Player Registration screens show the registration number, player details, age, batting hand and bowling arm, village/tehsil/district, submitted files (view/download via the private routes above), registration fee, the UTR the player typed and the one the OCR read off the screenshot ("matches" / "differs", and a warning when another registration typed the same UTR), and let an admin set `payment_status` (pending/paid/failed/refunded) and record a payment reference/UTR. Deleting a registration (only when it has no squad assignment) also cleans up its owned files, and **Data Cleanup → Registration Documents** can purge the submitted photos (separately from Aadhaar + payment proofs) once an edition is no longer open.
- **Quick payment verification** — on a registration's page, **Mark paid** and **Mark failed** (which asks for a reason) are one click each and then open the **next pending registration** of the same edition (oldest first); a **Next pending** button skips, and the registrations list has **Review pending** for the filtered edition. For a failed payment the player sees the admin's reason (escaped) plus "please contact the administration" on the status page; the admin settles it offline. No SMS/WhatsApp is sent.
- **Import from a Google Form sheet or an Excel file** — *Import Excel / CSV* on the registrations page accepts an Excel `.xlsx` (first sheet) or a CSV, and a *Download a sample Excel sheet* link gives a ready-to-fill workbook with the columns and two example players (one with every answer, one with only the required name and mobile number). Everything below applies to both file types, plus an optional *Bowling arm* column. It accepts the Google Form response sheet exactly as downloaded from Google Sheets, with no renaming or deleting of columns: Timestamp, Email Address, Name, Age, Mobile Number, Role, Left hand/right hand, Gram, Tehsil, District, the payment/UTR answer, and the photo and payment-screenshot columns. Only the name is required. Messy answers are cleaned up instead of rejected (a +91 or spaces in a phone number, "22 years", differently worded roles) and the date/time is read as day/month/year in the display timezone. People are matched by mobile number — the form's Email Address is just the Google account that submitted it, so it is only saved when no other player has it and never merges two players — and every value that was dropped or adjusted, and every row that was skipped, is listed after the import. A *Check only* run reports all of that without importing anything. Photos and payment screenshots can't travel in a spreadsheet, so each row's Google Drive links are saved and then, in the background (queue worker), the files are copied into private storage on the *same registration*: the link is in the same row as the player, so a file can't reach the wrong person. This only works for files shared as "Anyone with the link"; a private, removed or non-image file keeps its link, and the registration page's *Copy files from Google Drive* button retries it and says why it failed. A copied payment screenshot also gets its transaction ID read (OCR). *Use as profile photo* on the registration page resizes the submitted photo (longest side 800 px, JPEG, rotated upright) and makes it the player's public profile photo, replacing the old one after a confirmation. The registration then holds the age as entered, village/tehsil/district, the UTR the player typed and the two links, all editable afterwards on its Edit page and searchable (village, tehsil, district, UTR). Importing the same sheet again is safe: players who are already registered are skipped and never changed, so only new responses are added. The original `name, phone, email, registration_fee, payment_status, registered_at` format still works.
- **Status lookup** — a guest can check their registration/payment status at any time with just their **phone number** (no login, no OTP), so losing or not copying the Registration Number is no problem: the phone lists their registration(s) — registration number, edition, fee, payment status, date — with the player's name. Adding the optional Registration Number narrows it to that registration. The success page also keeps showing the number if it is refreshed. Deliberately available even when new registration is closed, and for completed editions or a since-deactivated player. Never exposes email/DOB/documents/payment reference/UTR — the name is the only personal detail shown.
- Rate-limited (both submission and status lookup) to slow down abuse without a CAPTCHA/OTP.

### Tournament setup

- **One active season** — only one edition can be *Active* at a time; activating a second one is refused with a message naming the active one (it is never completed silently). Registration is likewise open for one edition at a time.
- **Venues for a village ground** — a venue has a name, village (gram), tehsil, district and an **exact map location**. The venue form has a map picker (OpenStreetMap + Leaflet, no API key, nothing to configure): search a place, switch to **Satellite** to find the ground, click or drag the pin; latitude/longitude are saved and stay editable as numbers. The public **Match Info** tab and the public venue page show the village/tehsil/district, a small map with the pin and a **Get directions** button that opens Google Maps to the pin. Older venues with only a city/country keep showing that text.
- **Edition hub** — opening a season (Admin → Editions → the season) shows a slim header strip (status, year, registration open, Summary PDF, Edit) and compact **summary cards that are also the way in**: Registrations ("48 · 6 pending"), Teams, Squads ("players in teams · without a team"), Matches ("played · to play") and Finance (balance, in / out, with a link to Contributions). Each card's *View more* opens that section of the season; below the cards sits the season summary (points table, top 5 run scorers / wicket takers, records).
- **Teams inside a season** (*Editions › Season › Teams*) — the season's teams with squad size and match count. *Add existing teams* is a multi-select (search, select all) of only the active teams not yet in the season, so several go in at once; *Create a new team* makes a brand-new team (name, short name, logo) and adds it in one step. A team can be removed from a season only while it has no matches; its squad entries go with it (the players' registrations and the team itself stay). New teams cannot be added to a completed season.
- **Squads inside a season** (*Editions › Season › Squads*) — an overview of every team's squad (players, total bought for) and how many registered players are still in no team, then a page per team: the squad list with **jersey number, role and bought-for amount editable right in the list** (one *Save squad*; jersey swaps work), a remove button (not for a player who has played a match), and *Add players*: a multi-select of only this season's players who are not yet in any team (search, select all) where an **amount box appears beside each ticked player**, so a whole auction result for a team goes in at once. *Registered offline?* adds a player by name and mobile number: it creates the player (or reuses the one with that number, never editing them), their registration for the season (paid by default, with the season's fee) and the squad place in one step. Jersey, role and amount are all optional. New players cannot be added to a completed season.
- **Matches and Registrations inside a season** — *Editions › Season › Matches* lists only that season's matches (time in the display timezone, stage, overs, venue, status, with the status filter) and *Add match* opens the match form with the season fixed and Team A / Team B limited to that season's teams (match number, overs and venue pre-filled). *Editions › Season › Registrations* lists only that season's registrations (search, payment status, team / "without a team" filters, Review pending and Export for the season) and lets the admin **tick several players and add them to a team in one go**. Registrations and Players are also still available from *Player Management* in the sidebar.
- **Sidebar** — Tournament Management now holds Editions, Teams and Venues; a season's teams, squads, matches and registrations are opened from its hub. The older Edition Teams and Squads pages still work.
- **Default venue** — one venue can be marked *Default venue* (a tick on the venue form; ticking it clears the previous default, and an inactive venue cannot be the default). Every new-match form (the standalone one and the one inside a season) starts with it selected, and the admin can still pick another venue for any match. Without a default, the venue of the season's latest match is used.
- **New match form** — starts on the current season with the next free match number and the overs and venue of its latest match; overs have quick-pick buttons (6, 8, 10, 12, 15, 20) since they differ from match to match.

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
- **Media Files** — removes *orphaned* uploads only: files sitting in a known upload folder (videos, video thumbnails, advertisements and their previews, photos, news images, rules images, player photos, team logos, contributor photos) that no database record refers to any more. The tab lists each category's folder, file count and orphan count, previews the files, and deletes one category at a time after a confirmation; the list is re-scanned on the server at delete time. Content is never judged: an inactive, unpublished or old video/photo/post/rule still refers to its file, so the file is kept, and no record is ever deleted. A file is kept if any record anywhere refers to it, files uploaded in the last 24 hours are always protected (an upload being saved looks orphaned for a moment), and only plain generated file names directly inside those folders are ever considered. Site branding (logo/favicon) and the private registration documents are deliberately not covered. Manual only, never scheduled; each run is written to the cleanup log with counts but no file paths.
- **System** — a read-only **Failed Jobs** list (newest first, 20 per page: when it failed, which job, queue, connection, and a one-line error summary) with a detail page per job showing the full error/stack trace. It is for seeing what went wrong only: there is no retry button and no per-job delete, and the stored job data itself (which can contain player details) is never shown, only the job's name. Below the list, the existing option deletes old `failed_jobs` records before a chosen date. (Two related candidates were deliberately **not** built: `job_batches`, since this app never uses `Bus::batch()` so the table is never populated; and application log cleanup, judged unsafe to build against the configured `single` log channel — see the Phase 3.49 report.)
- Every date cutoff is interpreted as midnight **in the configured `system.display_timezone`**, converted to UTC before the query runs — not the server's own timezone — and the selected date itself is always excluded ("before this date"). Every destructive action shows an exact, server-calculated affected-record count (never a client-supplied number) before the confirmation dialog.
- Every cleanup action writes an immutable audit row to `data_cleanup_logs` (admin, category, action, criteria, records affected, files deleted) — a simple, reusable trail answering who ran what, when, and how much it affected.

### Announcements (public ticker)

- Admin-managed announcements (`admin/announcements`) with a start/end scheduling window and a computed status (scheduled/active/expired/disabled) — never a manually-set status field.
- Active announcements scroll across a CSS-only marquee ticker on every public page (`AnnouncementTickerComposer`); admin controls the display order via an explicit `sort_order`, not a generic column sort.
- **Optional push notification, entirely separate from ticker visibility** — an admin may additionally choose Send Now or Schedule for Later (a future date/time, entered and shown in `system.display_timezone`). Both reuse the exact same Notification/`SendNotificationJob`/Firebase pipeline the standalone Notifications module uses — there is only ever one outbound push code path. A scheduled send is picked up by `rppl:dispatch-scheduled-announcements` (see Scheduled tasks below) once due; an atomic database claim (`lockForUpdate()` + a `notification_dispatched_at` check) guarantees it can never double-send even if the scheduler runs it more than once. Editing an announcement after its notification has already fired never resends or reschedules it.

### Scheduled tasks (`rppl:dispatch-scheduled-announcements`, `rppl:dispatch-match-reminders`, `rppl:dispatch-registration-closing-reminders`, `rppl:dispatch-tournament-day-reminders`, `rppl:cleanup-failed-jobs`)

One Laravel Scheduler, registered in `bootstrap/app.php`, running the reminder/announcement tasks every minute and the failed-job cleanup once a day — production needs exactly one OS cron entry regardless of how many scheduled tasks exist; see the Production checklist below for the exact line and the full explanation of what still runs as separate long-running processes (queue worker, Reverb). Every task is a thin scan-and-dispatch: find due, eligible rows; atomically claim one; hand it to the existing notification pipeline. None ever calls Firebase directly. (The match result notification is not scheduled — it is queued right after an admin finalizes a match.)

- **Tournament-day morning reminder** — optional (Settings → System, off by default): on any day with matches, ONE summary push per edition, e.g. "Today's RPPL Matches — 3 matches today. First: MI vs RCB at 08:00 AM." (a single match also names the venue), linking to the public Matches page. "Today" is the calendar day in `system.display_timezone`. Sends from the configured time (default 07:00) until 12:00 local time, so a late-starting scheduler still sends it but a stale "morning" reminder is never sent in the afternoon. Only `scheduled`/`toss`/`live` matches count — completed/cancelled/abandoned are excluded, and a day with no eligible matches sends nothing. A `tournament_day_notifications` row (unique per edition + date) is written only once the push is actually queued, so it never double-sends and a failed queue attempt is retried on the next run; a match added after the day's summary has gone out does not trigger a second one.
- **Automatic failed job cleanup** — optional (Settings → System, off by default): once a day at 03:00 (app timezone), failed queue jobs older than the configured retention (7–365 days, default 30) are deleted. Only the `failed_jobs` table is ever touched — pending jobs are never affected — and a job that failed exactly at the retention boundary is kept (`failed_at` must be strictly older). Recent failures stay visible in Data Cleanup → System, and the manual delete-by-date there is unchanged. Running `php artisan rppl:cleanup-failed-jobs` by hand respects the same on/off setting; there is no retry. Each run that deletes something writes one line to the application log (the Data Cleanup audit log records admin actions only).

### Featured videos (public)

- The public homepage shows up to 3 active videos in a compact **Featured Videos** section, placed below the Match Centre and Featured Match (live and match info always come first) and above the Points Table. The section disappears entirely when there are no active videos.
- A public **Videos** page (`/videos`) lists every active video, 12 per page, in the same order (lowest priority number first, then newest). Inactive videos never appear on either page.
- Videos never autoplay. Each card uses the browser's own player (thumbnail as the poster when one exists, only metadata loaded until the viewer presses Play). Starting one video pauses any other that's playing on the page.
- Admins can switch a video Active/Inactive with one click straight from the Admin → Videos table (no need to open Edit); the change is applied server-side and only admins can do it.

### Sponsor advertisements (public)

- Admin → Content Management → **Advertisements** manages sponsor images and short videos. Each one has a title, a **sponsor level**, an image (JPG, PNG or WebP up to 3 MB) or a muted video (MP4 or WebM up to 8 MB, with an optional preview image), Active/Inactive status, optional show-from / show-until dates and a "how often" number. The list switches an ad on or off with one click.
- The sponsor level decides where an ad appears — the admin never picks a position. **Main** sponsor: one big banner at the top of the home, edition (points table), match-info and live-score pages, always shown; only one Main sponsor can be active on a given day (a second one is refused with a message naming the first, unless their dates do not overlap). **Normal** sponsors: one banner below the content (on the live page, below the score and above the commentary); when there are several, one is picked on every page load, a higher "how often" number being picked more often. **Mini** sponsors: small logos together in an "Our sponsors" strip at the bottom (images only).
- Ads are display only: no link, no click, no pop-up or overlay, and a small "Sponsored" label. Each sits in a box of fixed proportions so the page never jumps while it loads, videos start only when scrolled into view and never play sound, and a missing file or any problem loading ads simply leaves no gap. The live-score area that refreshes every few seconds never contains an ad.
- Show-from / show-until are calendar days in the display timezone; an ad with no dates is always shown while active. Ad files are included in Data Cleanup → Media Files.
- Not built yet (ideas for later): clickable links, view/click counts, footer-wide sponsors on every page, more placements.

### Photos (public gallery)

- Admin → Photos manages a simple photo gallery, modelled on Videos: title, optional description, an uploaded image (JPG, PNG or WebP up to 5 MB — never SVG or other file types), Active/Inactive status and a priority (lower number shows first, newest first among ties). Files are stored under randomly generated names; replacing a photo only removes the old file once the new one is saved, and deleting a photo removes its file (a file that is already missing never blocks the delete).
- A public **Photos** page (`/photos`) shows only Active photos as a responsive grid (2 columns on phones, 3 on large screens), 12 per page, with a simple in-page viewer when a photo is clicked (it falls back to opening the image if the browser doesn't support it). The empty state and labels are available in English and Hindi; photo titles and descriptions are shown exactly as entered.
- Like Videos, each Photos table row has a one-click Active/Inactive control.
- **News**, **Videos** and **Photos** are reachable from the public header's **More** menu on desktop and from the mobile menu.

### News (public)

- Admin → News manages tournament news posts: a heading, the news text (plain text — line breaks are kept and any HTML is shown as typed, never rendered), zero to ten optional images (JPG, PNG or WebP up to 5 MB each — never SVG or other file types), Active/Inactive status, a priority (lower number shows first, then newest published) and a **Published at** date/time entered and shown in the display timezone (blank = publish now on create, keep the current time on edit).
- A post is public only while it is **Active and its published time has passed**, so a future time schedules it without any extra setup. Nothing is sent as a push notification.
- Each post gets a clean, unique web address generated from its heading (e.g. `/news/rppl-season-3-registration-starts`, with `-2`, `-3`… added for repeated headings). The address is kept when the heading is edited later, so links that were already shared keep working.
- On edit, existing images can be removed individually (tick Remove) and more can be added, up to ten in total; removed images and the files of a deleted post are cleaned up from storage. Each News table row has the same one-click Active/Inactive control as Videos and Photos.
- The public **News** page (`/news`) lists posts as cards (cover image = the first image, date, heading, short excerpt, Read More), 9 per page; each post has its own page with the full text and all its images (clickable, in the same simple viewer as Photos). Labels and empty states are in English and Hindi; the post text itself is never translated.


### Rules & Regulations (public page)

- A public **Rules & Regulations** page (`/rules`, linked from the header's **More** menu) shows the tournament rulebook grouped into categories (e.g. Cricket Rules, RPPL Specific Rules), one tab per category.
- Only active categories that contain at least one active rule appear — an inactive category, an inactive rule, or a category whose rules are all inactive never shows up. Categories and rules follow their admin-set display order.
- The selected category lives in the URL (`/rules?type=cricket-rules`), so a category can be linked to directly and stays selected on refresh. An unknown or hidden category in the link simply falls back to the first category.
- Each rule shows its number, title, text (line breaks kept), an optional image, and an **Important** marker when flagged. A short notice at the top states that in any critical, disputed or unforeseen situation not clearly covered by the rules, the RPPL Committee's decision is final.
- When nothing is published yet, the page shows a short "will be published here soon" message.

### Bilingual public website (English / हिन्दी)

- A compact **English / हिन्दी** language switcher lives in the public header (desktop dropdown + mobile drawer), next to the existing "More" menu — no separate language page, no URL prefix (`/matches`, `/rules`, `/videos` etc. stay exactly the same in both languages).
- Built on Laravel's native localization (`lang/{en,hi}/*.php`) — no external translation service, no database table. The choice is stored in a long-lived `rppl_locale` cookie (~5 years), read by a small `SetPublicLocale` middleware registered **only** on the public routes — the Admin panel and Scorer panel always render in English regardless of a visitor's cookie.
- Covers the homepage, Match Centre/Featured Match, Matches list, Live/Scorecard/Squads/Match Info tabs, Teams, Players, Venues, Editions (incl. the points table, leaderboard and records), Videos, Rules & Regulations (including a natural-Hindi translation of the Committee final-decision notice), and the Player Registration + status-lookup forms (including their validation messages).
- Player/team/venue/edition names, scores, dates, registration numbers, and all other Admin-entered or DB-sourced content are never translated — only the surrounding static UI labels. Hindi cricket terminology favours plain, commonly-spoken words (लाइव, ओवर, विकेट, स्कोरकार्ड) over stiff textbook translations.
- Two deliberate scope boundaries for this V1 pass: the scorecard's batting/bowling table (`shared/scorecard/_innings.blade.php`) is shared verbatim with the Admin scorecard, so its column headers stay English even in Hindi mode; and the live match page's realtime-updated rows (redrawn client-side by `public-live-match.js` on every poll/websocket push) also stay English, since only the Blade half of those labels could be localized without the JS re-render immediately reverting them — translating the JS side is a natural follow-up, not done here.

### Content pages (Privacy Policy / Terms & Conditions / FAQs)

- Three fixed content-page slots (`admin/content-pages`), each with Markdown-authored content rendered safely to HTML (`MarkdownRenderer`, raw HTML input escaped, unsafe links rejected) and shown at its own public route/footer link.
- No create/delete — the three slots are fixed identities, only their content is editable.

### Videos (homepage clips)

- Admin-managed short RPPL clips (`admin/videos`) — title, optional description, an MP4/WebM video file, an optional JPG/PNG/WebP thumbnail, a status (Active/Inactive) and a priority number (lower shows first).
- Upload size is capped by `VIDEOS_MAX_UPLOAD_MB` (default 50 MB), aimed at short ~1–2 minute clips; errors are worded in MB. No transcoding or auto-generated thumbnails — files are stored as uploaded.
- Editing without choosing a new file keeps the existing video/thumbnail; replacing or deleting a video removes the old stored files. Activating/deactivating is just the Status field on the edit form.

### Rules & Regulations (admin)

- One **Rules & Regulations** sidebar entry (`admin/rules`) lists every rule with its order, title, rule type, status, an "important" star and last-updated date, filterable by rule type and status and searchable by title.
- Each rule has a rule type, a title, plain-text content (line breaks kept, no HTML), an optional JPG/PNG/WebP image up to 2 MB, a sort order (lower shows first within its type), a status (Active/Inactive) and an optional "especially important" highlight.
- Editing without a new image keeps the current one; uploading a new image replaces it (the old file is deleted); a "Remove image" checkbox clears it with no replacement; deleting a rule also deletes its image.
- **Rule types** (the categories rules are grouped under, e.g. Cricket Rules) are managed from a "Manage Rule Types" button on the Rules page (`admin/rule-types`) — name, a unique lowercase-hyphenated slug, optional description, sort order and Active/Inactive status. Deactivating a type hides all of its rules from the public website without touching the rules themselves.
- A rule type that still has rules can't be deleted — the admin gets a friendly message to move/delete its rules first or deactivate the type instead.

### Global settings, dynamic branding & theme

- A tabbed admin Settings screen (`admin/settings`) covering general branding (application name, short name, tagline, logo/favicon, footer text), contact details, system options (currency symbol, display timezone, the committee dues target — see Finance below), and encrypted payment-gateway secrets — each tab its own scoped update action, never one generic "update settings" endpoint.
- **Times are shown in the display timezone everywhere** — every date and time is stored in UTC and shown in the configured display timezone (Settings → System, India by default), in one format: `03 Oct 2026, 12:20 AM` (12-hour clock with AM/PM — midnight reads 12:20 AM — and a plain `03 Oct 2026` where only a date is shown). That covers the admin panel (lists, detail pages, edit forms, notification and match histories, reports), the public site, the PDFs, and the CSV exports. Times an admin types (a registration's "Registered at", a match start, a news post's publish time) are read in that timezone and converted back to UTC, and the date-range filters on registrations and matches use the same calendar days that are shown, so a registration at 12:20 AM on 3 October is found by "3 October", not "2 October". A test (`DisplayTimezoneConventionTest`) fails if a view or export formats a stored UTC time directly instead of going through `display_datetime()`.
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

- `APP_ENV=production` and `APP_DEBUG=false` (`.env.example` now defaults to `APP_DEBUG=false`; enable debugging only in your own local `.env`. `APP_DEBUG=true` must never run in production — it leaks stack traces and configuration). Also set `SESSION_SECURE_COOKIE=true` when serving over HTTPS.
- `SESSION_SECURE_COOKIE=true`, served over HTTPS (not set by default; without it the session cookie is also sent over plain HTTP).
- `php artisan storage:link` has been run on the deployed instance (see step 5 above — easy to forget on a fresh deploy).
- A queue worker is running under a process supervisor (e.g. Supervisor/systemd), not just a one-off terminal — required for OCR and push notification sending (step 10 above), and now also for scheduled announcements/match reminders (see below).
- Real Firebase credentials configured (step 11) if push notifications are wanted; the app works fine without them, just with that one feature inactive.
- PHP upload limits large enough for the player registration form's two 10 MB files: `upload_max_filesize` of at least `10M` and `post_max_size` of at least `25M` in `php.ini` (and `client_max_body_size 25m;` if nginx is in front). Lower limits do not break the form — it detects them and offers smaller maximums — but players with phone photos will hit them.
- **Settings → Payments → UPI payment details** filled in (UPI ID and QR code) before registration is opened, so the form can show where to pay.
- A unique `APP_KEY` generated per environment (`php artisan key:generate`) — also used to encrypt the Settings module's Razorpay secret fields; rotating it later invalidates any already-stored encrypted values.
- If a queued job (OCR, notification send) exhausts its retries, it lands in `failed_jobs` with no other signal to an admin — check periodically with `php artisan queue:failed`, and retry with `php artisan queue:retry {id}` (or `all`) once the underlying issue is fixed.

### Scheduled tasks — exactly ONE cron entry, regardless of how many are added later

Add this single line to the server's crontab (adjust the path):

```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

That one entry invokes Laravel's Scheduler every minute, which internally runs whichever registered tasks are actually due (currently: `rppl:dispatch-scheduled-announcements`, `rppl:dispatch-match-reminders`, `rppl:dispatch-registration-closing-reminders` and `rppl:dispatch-tournament-day-reminders` every minute, plus `rppl:cleanup-failed-jobs` daily — all registered in `bootstrap/app.php`'s `->withSchedule()`). **Adding a future scheduled feature (a tournament-day reminder, a cleanup task, anything else) only ever means adding another registration inside that same `->withSchedule()` closure — it never means adding a second cron line.** Three genuinely separate things are required in production, and only the first is cron-triggered:

1. **The one Scheduler cron above** — triggers due tasks, then exits; never runs anything long-lived itself.
2. **A queue worker** (`php artisan queue:work database --queue=default`), supervised (Supervisor/systemd) — the Scheduler's own tasks only ever *dispatch* a Job (e.g. `SendNotificationJob`); the actual Firebase/OCR work happens here, in the separately-running worker. Never launched from the Scheduler.
3. **Reverb** (`php artisan reverb:start`), supervised, only if realtime live-score broadcasting is enabled — entirely unrelated to the Scheduler, unchanged by this feature.

Verify what's currently registered at any time with `php artisan schedule:list`.

## Demo data

`php artisan migrate:fresh --seed` seeds a complete, internally-consistent demo dataset — two independent seasons of the same four teams (Mumbai Indians, Royal Challengers Bengaluru, Chennai Super Kings, Kolkata Knight Riders), with real connected data behind every module in the Features list:

- **RPPL 2026 (active)** — 60 squad players with full 15-player squads, 4 extra registrants who are not squadded and sit in pending / failed / refunded payment states (so the admin verification banner and payment filters have something to show), and 6 league fixtures: 3 played to a real completed result and 3 left scheduled, so the whole upcoming-match flow can be tried from a clean start.
- **RPPL 2025 (completed)** — a finished previous season with its own edition-team rows, 13-player squads (52 players, plus 3 unsquadded refunded/failed registrations), and 7 five-over matches played in April 2025: six league matches and a Final between the league's top two. Results are deliberately varied (a successful chase, a defended total, a low-scoring game, a high-scoring game, a last-ball finish, a comfortable win, a close Final). The points table, top run scorers, top wicket takers and records all derive from those matches.
- **Every match is played ball by ball through the real scoring services** (never by writing totals), so scorecards, standings, statistics and the live/realtime pages behave exactly as they do for a real match. All seeded matches are 5 overs a side, purely so a fresh match is quick to try.
- **Content** — news posts for both seasons (the 2025 champion post and the 2026 recaps are generated from the stored match results, so they always agree with the data), 4 sample gallery photos and a few news cover images (small generated placeholder pictures, clearly labelled as placeholders), the fixed content pages, 9 rules, and announcements including an expired and a disabled one (the live ticker still shows only the current three). Videos are not seeded because a real video file cannot be generated safely.
- **Finance** — contributors, committee dues (fully paid for the completed 2025 season; a mix of paid / partly paid / unpaid for 2026), contributions with their linked income transactions, and ledger income and expenses for both seasons.
- **Login accounts** — `admin@rppl.test` / `password` (Admin) and `scorer@rppl.test` / `password` (Scorer). **Local/demo credentials only — never use these in production.**
- Deliberately **not** seeded: push-notification device tokens and delivery history (they would fake real Firebase results), failed jobs, scoring audit events and cleanup logs. No seeded registration has an Aadhaar document or payment proof, and OCR is never dispatched (`ocr_status` is `failed`, not the raw `pending`).

The seeders are **idempotent**: running `php artisan db:seed` against an already-seeded database adds nothing, and on a database that only has the older 2026 data it adds just the new pieces (2025, extra registrations, news, photos, finance and rules) without touching existing rows — no `migrate:fresh` needed. The 2026 fixtures' dates are relative to seed time, so re-seed before a live demo if the database is more than a few days old.

Seeder layout: `database/seeders/Rppl2026/` and `database/seeders/Rppl2025/` (one class per area per season: edition, registrations and squads, fixtures, finance), `database/seeders/Demo/` (users, settings, content pages, notifications, announcements, rules, news, photos), orchestrated in dependency order by `DatabaseSeeder`. The older `Demo\DemoEdition/Match/Finance/Registration/Team/PlayerSeeder` classes are unused alternatives.

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
