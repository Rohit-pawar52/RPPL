# Tournament Management rework: checklist

Working list from the one-module-at-a-time discussion with Rohit (started 2026-10-03).
Nothing here is built yet unless it says **Done**.

## Decisions made
- An **edition = one whole season** (e.g. "RPPL Season 3"), from registration to the final.
- Only **one edition is active at a time**. Edition status stays **manual** (no automatic switching).
- Registration is open for **one edition at a time** (already enforced).
- A **completed** season stays viewable and an admin **can still edit it when needed** (to correct a mistake). Today completed blocks new registrations / teams / squad entries for the admin too.
- **Teams** are master records reused across seasons: some teams stay, some change each season. A season's team list is separate from the master team record.
- A team's **squad is per season** (a returning team starts the new season with an empty squad); a player's **registration** is per season and joins the squad, while the player record (matched by mobile number) carries history across seasons.
- **Removing a team from a season**: allowed only while it is safe, i.e. the team was just added / the tournament has not started (no matches for that team). Removing it also removes its squad (registrations stay). A team that already has matches cannot be removed. In practice deletes are rare; they are for undoing a recent mistake.
- **How squads are formed**: an **auction** where team owners bid; the admin enters the result afterwards. Players added are normally already paid, but a player can be added **without an online registration** (offline registration + auction).
- On a squad entry **jersey number, squad role and sold amount are all optional** (the admin adds them or not). No maximum squad size was asked for, so none is enforced.
- **Venues**: a season is played at **one venue** (all its matches). It is a village ground, so the venue needs a **village (gram) name** (today there is only a city) and an **exact map location** so it can be shown on a map.
- **Matches**: the league fixtures are entered up front, one by one. Knockout matches (quarter-final, semi-final, final) are created later, once the teams are known.
- **Overs per innings differ by match** (6, 8 or 10), so overs stay a per-match field.
- **Payment verification** is done by the admin **one registration at a time** (no bulk). **No SMS / WhatsApp / paid notifications for now**: only free tools.
- **Registrations and Players** are reachable **both ways**: from inside a season (that season's registrations / players) and from the sidebar's Player Management (all of them).
- **Edition hub layout (step 2)**: no separate tabs row *and* cards. The **summary cards with counts are themselves the entry points**: each card (Teams, Squads, Matches, Registrations, Finance) shows its count and a "View more" link that opens that section's list inside the season. Very **compact** UI so the admin scrolls as little as possible.
- Clicking a card opens that section as **its own page with a breadcrumb** (e.g. *Editions › RPPL Season 3 › Teams*), where add / edit / delete happen; the back link returns to the hub. Chosen because each page stays small and the code is easier to maintain.
- **Hub page layout**: a slim header strip (season name, year, status, venue, Edit / PDF), then the **quick-count cards** (Registrations "48 · 6 pending", Teams, Squads "players in teams / still without a team", Matches "played / remaining", Finance income / expense / balance), and **below them the season summary** (points table, top scorers / wicket takers, records) kept compact.
- **Compact summary**: full points table; top 5 scorers and top 5 wicket takers with "View all"; records as one slim strip. **One Finance card** shows income / expense / balance plus the contributions total.
- Wanted navigation style: **drill-down like category → sub-category**. Open a season, click Teams to see that season's teams and add / edit / delete them there; the same for Registrations, Squads, Matches, etc.

## To do
- [ ] Let an admin **edit a completed season** (corrections), instead of blocking every add / change. Decide exactly what stays blocked for the public (new registrations stay closed).
- [x] **Done (step 1)** Enforce **one "active" edition**: today two editions can both be set to active with no check.
- [x] **Done (step 2A)** **Edition as the hub**: one page per season with sections (Teams, Squads, Matches, Registrations, Finance), each opening its list *inside that season* with add / edit / delete there (season pre-selected, no season dropdowns).
- [x] **Done (step 2A)** Teams in a season: "Add teams" opens a **multi-select** list of existing teams not yet in this season (tick many, add in one go), **and** lets the admin **create a brand-new team right there** (name, short name, logo) which joins this season; remove a team from the season; open a team to see its squad.
- [x] **Done (step 2B)** "Add players" can also **create a new player on the spot** (name, mobile) for offline registrations: one step creates the player, their registration for this season and the squad entry.
- [x] **Done (step 2B)** Store the **sold amount** (auction price) on each squad entry, shown in the squad list; nothing else about the auction for now.
- [x] **Done (step 2B)** Squad of a team: list on the team's page; **"Add players" is a multi-select so 10-15 players go in at once**. The list shows only that season's players who are **not yet in any team**: once added they disappear from it, and a player removed from a team shows up again. Search + select-all; jersey / role / sold amount stay optional. When a player is ticked, a small **amount box appears beside them** (can be left empty) so the auction result goes in with the add; the amount (and jersey / role) can **also be edited later** from the squad list.
- [x] **Done (step 2C)** Matches inside a season: Team A / Team B list only that season's teams.
- [x] **Done (step 1)** Match form: overs as quick picks (6 / 8 / 10 plus a custom number), pre-filled with the last match's overs; the match number pre-filled with the next free number in the season (still editable).
- [x] **Done (step 1)** Registration page: one-click **Mark paid / Mark failed** buttons (no edit form needed) and a **Next pending** button that opens the next pending registration; marking **failed** asks for a **reason** (saved on the registration, so the admin remembers why).
- [x] **Done (step 1)** Player status page: when payment is **failed**, show the admin's **reason** plus "please contact the admin"; no re-upload (the admin settles it offline).
- [x] **Done (step 2C)** Registrations inside a season, with a bulk "Add to team" for selected registrations.
- [x] **Done (step 1)** Venue: add a **village (gram)** field (replacing the city / country emphasis) and a **map location** field so the exact ground is shown on a map (public match page + admin). Decided: a **map picker in the venue form** (OpenStreetMap + Leaflet, no API key): search a place, **satellite toggle** for village grounds, click to drop a pin, latitude/longitude saved. Public match page shows a small map with the pin and an "Open in Google Maps" button.
- [x] **Done (step 1)** Venue fields: **name, village (gram), tehsil, district, map location** (city / country dropped); active/inactive kept as is.
- [x] **Done (step 1)** **Match Info page (public)**: show the venue location with a map and a "Get directions" button (opens Google Maps to the pin) so people can find the ground.
- [ ] A season has one **main venue**: set it on the edition and pre-fill it for every match of that season.
- [x] **Done (step 2C, simplified: Matches stays top-level for scorers; Editions / Teams / Venues in one group)** Sidebar after the hub exists: **Tournament** = Editions (hub), Matches; **Setup** = Teams, Venues; **Player Management** stays (Players, Registrations); "Season Teams" and "Squad Players" leave the sidebar (they open inside a season).

## Future ideas (not now)
- A full live **auction setup** (live bidding screen, base price, team budgets, player queue, public display).

- **TBC knockout placeholders**: create the semi-final / final matches early with the teams shown as "TBC" (so the fixture plan is visible), filled in once decided. Not now.

## Open questions
- Do scorers use anything in Tournament Management besides Matches?

## Modules discussed so far
- [x] Editions: meaning, status, one active, wanted drill-down navigation
- [x] Teams: master record reused across seasons; multi-select add + create-in-place decided
- [x] Season teams: removal rule decided (only before matches exist; squad goes with it, registrations stay)
- [x] Squads: auction result entry, sold amount, optional jersey/role, offline players decided
- [x] Venues (one per season, village + map location decided; map picker decided)
- [x] Matches: league up front, knockouts later (TBC placeholders = future)
- [ ] Registrations (one-by-one verification, no SMS decided; quick paid/failed buttons, next pending and failure reason decided)
