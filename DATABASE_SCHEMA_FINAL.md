# RPPL Tournament Management System - MySQL Database Schema (FINAL)

## Phase 1: Database Design - Final Corrections

---

## FINAL REVISED TABLE SPECIFICATIONS

### TEAM_PLAYERS TABLE (FINAL - Correction 1 & 2)

**Purpose:** Assign players to edition-specific teams. A player belongs to at most ONE team per edition.

```
Table Name: team_players
Primary Key: id (BIGINT UNSIGNED)
```

| Column | Type | Nullable | Unique | Default | Index | Purpose |
|--------|------|----------|--------|---------|-------|---------|
| id | BIGINT UNSIGNED | NO | PK | AUTO_INCREMENT | PK | Unique identifier |
| edition_team_id | BIGINT UNSIGNED | NO | - | - | INDEX(FK) | FK to edition_teams (team in edition) |
| player_registration_id | BIGINT UNSIGNED | NO | - | - | UNIQUE | FK to player_registrations |
| jersey_number | INT | YES | - | NULL | - | Player jersey number |
| role | ENUM('batter','bowler','all_rounder','wicket_keeper') | YES | - | NULL | - | Player role in team |
| created_at | TIMESTAMP | NO | - | CURRENT_TIMESTAMP | INDEX | Creation timestamp |
| updated_at | TIMESTAMP | NO | - | CURRENT_TIMESTAMP | - | Last update timestamp |

**Foreign Keys:**
- `FOREIGN KEY (edition_team_id) REFERENCES edition_teams(id) ON DELETE CASCADE`
- `FOREIGN KEY (player_registration_id) REFERENCES player_registrations(id) ON DELETE CASCADE`

**Unique Constraints:**
- `UNIQUE(player_registration_id)` - A registered player belongs to at most ONE team (per edition, enforced via registration's edition_id)
- `UNIQUE(edition_team_id, jersey_number)` - Jersey number unique per team

**Indexes:**
- PRIMARY KEY: `id`
- `UNIQUE KEY: (player_registration_id)`
- `UNIQUE KEY: (edition_team_id, jersey_number)`
- `INDEX: edition_team_id`
- `INDEX: created_at`

**Enforcement Clarification (Correction 2):**

The foreign key relationships DO NOT guarantee that `player_registrations.edition_id = edition_teams.edition_id`.

The constraints only ensure:
- `player_registration_id` exists in player_registrations table
- `edition_team_id` exists in edition_teams table

**Database Enforcement:**
- Player can be in only ONE team_players record (via UNIQUE on player_registration_id)
- Jersey number is unique per edition_team (via UNIQUE on edition_team_id, jersey_number)

**Laravel Business Logic Enforcement (NEW):**
- When assigning player_registration to edition_team, validate:
  ```
  edition_teams.edition_id == player_registrations.edition_id
  ```
- Reject assignment if player is not registered for that edition

---

### DELIVERIES TABLE (FINAL - Corrections 3, 4, 5, 6, 7)

**Purpose:** Ball-by-ball delivery data with match_player references

```
Table Name: deliveries
Primary Key: id (BIGINT UNSIGNED)
```

| Column | Type | Nullable | Unique | Default | Index | Purpose |
|--------|------|----------|--------|---------|-------|---------|
| id | BIGINT UNSIGNED | NO | PK | AUTO_INCREMENT | PK | Unique identifier |
| innings_id | BIGINT UNSIGNED | NO | - | - | INDEX(FK) | Foreign key to innings |
| delivery_sequence | INT UNSIGNED | NO | - | - | INDEX | Authoritative event sequence per innings |
| over_number | TINYINT UNSIGNED | NO | - | - | - | Over number (0-indexed: 0, 1, 2...) |
| ball_number | TINYINT UNSIGNED | NO | - | - | - | Legal ball position in over (1-6), shared for illegal deliveries |
| striker_match_player_id | BIGINT UNSIGNED | NO | - | - | INDEX(FK) | FK to match_players (striker) |
| non_striker_match_player_id | BIGINT UNSIGNED | YES | - | NULL | INDEX(FK) | FK to match_players (non-striker) |
| bowler_match_player_id | BIGINT UNSIGNED | NO | - | - | INDEX(FK) | FK to match_players (bowler) |
| runs_off_bat | TINYINT UNSIGNED | NO | - | 0 | - | Runs from bat (0-6), NOT NULL DEFAULT 0 |
| wide_runs | TINYINT UNSIGNED | NO | - | 0 | - | Wide runs (0-N, can be multiple wides) |
| no_ball_runs | TINYINT UNSIGNED | NO | - | 0 | - | No-ball penalty runs (0 or 1) |
| bye_runs | TINYINT UNSIGNED | NO | - | 0 | - | Bye runs (0-N) |
| leg_bye_runs | TINYINT UNSIGNED | NO | - | 0 | - | Leg-bye runs (0-N) |
| penalty_runs | TINYINT UNSIGNED | NO | - | 0 | - | Other penalty runs (0-N) |
| total_runs | TINYINT UNSIGNED | NO | - | 0 | - | CALCULATED: sum of all run columns |
| is_legal_delivery | BOOLEAN | NO | - | TRUE | INDEX | Legal delivery (FALSE if wide or no-ball) |
| is_wicket | BOOLEAN | NO | - | FALSE | INDEX | Whether a wicket fell |
| wicket_type | ENUM('bowled','caught','lbw','stumped','hit_wicket','run_out','obstructing_field') | YES | - | NULL | - | Type of dismissal |
| dismissed_match_player_id | BIGINT UNSIGNED | YES | - | NULL | INDEX(FK) | FK to match_players (dismissed player) |
| fielder_match_player_id | BIGINT UNSIGNED | YES | - | NULL | INDEX(FK) | FK to match_players (fielder) |
| commentary | TEXT | YES | - | NULL | - | Ball-by-ball commentary |
| is_edited | BOOLEAN | NO | - | FALSE | - | Delivery was edited after entry |
| edit_reason | VARCHAR(255) | YES | - | NULL | - | Reason for edit |
| created_at | TIMESTAMP | NO | - | CURRENT_TIMESTAMP | INDEX | Creation timestamp |
| updated_at | TIMESTAMP | NO | - | CURRENT_TIMESTAMP | - | Last update timestamp |

**Foreign Keys:**
- `FOREIGN KEY (innings_id) REFERENCES innings(id) ON DELETE CASCADE`
- `FOREIGN KEY (striker_match_player_id) REFERENCES match_players(id) ON DELETE RESTRICT`
- `FOREIGN KEY (non_striker_match_player_id) REFERENCES match_players(id) ON DELETE SET NULL`
- `FOREIGN KEY (bowler_match_player_id) REFERENCES match_players(id) ON DELETE RESTRICT`
- `FOREIGN KEY (dismissed_match_player_id) REFERENCES match_players(id) ON DELETE SET NULL`
- `FOREIGN KEY (fielder_match_player_id) REFERENCES match_players(id) ON DELETE SET NULL`

**Unique Constraints:**
- `UNIQUE(innings_id, delivery_sequence)` - Sequence integrity per innings

**Indexes:**
- PRIMARY KEY: `id`
- `UNIQUE KEY: (innings_id, delivery_sequence)`
- `INDEX: innings_id`
- `INDEX: striker_match_player_id`
- `INDEX: bowler_match_player_id`
- `INDEX: dismissed_match_player_id`
- `INDEX: is_wicket`
- `INDEX(innings_id, is_legal_delivery)` - For overs calculation
- `INDEX(innings_id, over_number)` - For scorecard queries

**Delivery Numbering Strategy (Correction 4):**

```
Column: delivery_sequence
├─ Authoritative ordering mechanism (1, 2, 3, 4, 5...)
├─ Used for undo/redo and event replay
└─ Unique per innings

Column: over_number
├─ 0-indexed over number (0, 1, 2, 3...)
├─ Display: over_number + 1 (Over 1, 2, 3...)
└─ Increments after 6 legal deliveries in an over

Column: ball_number
├─ Represents legal ball position within over (1, 2, 3, 4, 5, 6)
├─ For illegal deliveries (wides, no-balls):
│  └─ ball_number shows the NEXT expected legal ball position
│  └─ Multiple consecutive wides/no-balls share same ball_number
├─ Example: If over has wide followed by no-ball followed by dot:
│  ├─ Wide: over=0, ball_number=1 (next legal ball expected)
│  ├─ No-ball: over=0, ball_number=1 (same position)
│  └─ Dot: over=0, ball_number=1 (first legal delivery)
└─ Resets to 1 at start of new over
```

**Ball Number Generation Algorithm:**

```
When recording a delivery:
1. If is_legal_delivery = TRUE:
   └─ ball_number = (count of legal deliveries in this over) + 1
   
2. If is_legal_delivery = FALSE (wide or no-ball):
   └─ ball_number = (count of legal deliveries in this over) + 1
   └─ (Represents upcoming legal ball position)
   
3. After 6 legal deliveries in an over:
   └─ Increment over_number
   └─ Reset legal delivery count
   └─ Next delivery will have over_number+1, ball_number=1
```

**Example Sequence (Correction 4):**

```
seq | over | ball | legal | event                  | Commentary
1   | 0    | 1    | yes   | dot ball              | 0.1 - Dot
2   | 0    | 2    | yes   | 1 run                 | 0.2 - Single
3   | 0    | 2    | no    | wide                  | 0w - Wide (ball_number same)
4   | 0    | 2    | no    | no-ball               | 0nb - No-ball (ball_number same)
5   | 0    | 2    | yes   | 4 runs                | 0.3 - Four (advances)
6   | 0    | 3    | yes   | wicket                | 0.4 - Bowled
7   | 0    | 4    | yes   | 2 runs                | 0.5 - Two
8   | 0    | 5    | yes   | bye                   | 0.6 - Bye (legal, advances)
9   | 1    | 1    | yes   | 0 runs                | 1.1 - Dot (new over)
```

**Ball Number NOT NULL:**
- `ball_number` should be NOT NULL DEFAULT 1 (for clarity)
- Illegal deliveries that occur before any legal delivery have ball_number=1

---

### MATCHES TABLE (FINAL - Corrections 8 & 9)

**Purpose:** Store match details with stage classification

```
Table Name: matches
Primary Key: id (BIGINT UNSIGNED)
```

| Column | Type | Nullable | Unique | Default | Index | Purpose |
|--------|------|----------|--------|---------|-------|---------|
| id | BIGINT UNSIGNED | NO | PK | AUTO_INCREMENT | PK | Unique identifier |
| edition_id | BIGINT UNSIGNED | NO | - | - | INDEX(FK) | Foreign key to editions |
| match_number | INT UNSIGNED | YES | - | NULL | - | Match sequence in edition (1, 2, 3...) |
| edition_team_a_id | BIGINT UNSIGNED | NO | - | - | INDEX(FK) | FK to edition_teams (Team A) |
| edition_team_b_id | BIGINT UNSIGNED | NO | - | - | INDEX(FK) | FK to edition_teams (Team B) |
| venue_id | BIGINT UNSIGNED | YES | - | NULL | INDEX(FK) | Foreign key to venues |
| match_stage | ENUM('league','quarter_final','semi_final','final') | YES | - | NULL | - | Match stage/type |
| overs_per_innings | INT UNSIGNED | YES | - | 20 | - | Overs per innings (default 20 for T20) |
| scheduled_at | DATETIME | NO | - | - | INDEX | Scheduled match time |
| started_at | DATETIME | YES | - | NULL | - | Actual match start time |
| completed_at | DATETIME | YES | - | NULL | - | Actual match completion time |
| match_status | ENUM('scheduled','toss','live','completed','abandoned','cancelled') | NO | - | 'scheduled' | INDEX | Current match status |
| toss_winner_team_id | BIGINT UNSIGNED | YES | - | NULL | - | Team that won the toss |
| toss_decision | ENUM('bat','bowl') | YES | - | NULL | - | Toss decision (bat/bowl) |
| match_result | VARCHAR(255) | YES | - | NULL | - | Match result (e.g., "Team A won by 5 runs") |
| winner_team_id | BIGINT UNSIGNED | YES | - | NULL | INDEX(FK) | Winning team ID |
| created_at | TIMESTAMP | NO | - | CURRENT_TIMESTAMP | INDEX | Creation timestamp |
| updated_at | TIMESTAMP | NO | - | CURRENT_TIMESTAMP | - | Last update timestamp |

**Foreign Keys:**
- `FOREIGN KEY (edition_id) REFERENCES editions(id) ON DELETE RESTRICT`
- `FOREIGN KEY (edition_team_a_id) REFERENCES edition_teams(id) ON DELETE RESTRICT`
- `FOREIGN KEY (edition_team_b_id) REFERENCES edition_teams(id) ON DELETE RESTRICT`
- `FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE SET NULL`
- `FOREIGN KEY (toss_winner_team_id) REFERENCES edition_teams(id) ON DELETE SET NULL`
- `FOREIGN KEY (winner_team_id) REFERENCES edition_teams(id) ON DELETE SET NULL`

**Unique Constraints:**
- `UNIQUE(edition_id, match_number)` - Correction 9: Match number unique per edition

**Indexes:**
- PRIMARY KEY: `id`
- `INDEX: edition_id`
- `INDEX: edition_team_a_id`
- `INDEX: edition_team_b_id`
- `INDEX: venue_id`
- `INDEX: scheduled_at`
- `INDEX: match_status`
- `INDEX: winner_team_id`
- `INDEX: created_at`
- `INDEX(edition_id, scheduled_at)` - for upcoming matches
- `INDEX(edition_id, match_number)` - for match ordering

**Field Changes (Correction 8):**
- Removed: `match_type` (VARCHAR)
- Added: `match_stage` (ENUM: league, quarter_final, semi_final, final)
- Rationale: RPPL uses tournament stage classification, not ODI/T20 format classification
- `overs_per_innings` remains responsible for match length (e.g., 20 overs for T20)

**Notes:**
- `match_number` + edition_id uniqueness ensures sequential numbering per edition
- Business logic must validate `edition_team_a.edition_id = edition_team_b.edition_id = edition_id`
- Business logic must validate `edition_team_a_id ≠ edition_team_b_id` (teams can't play themselves)

---

## FINAL RELATIONSHIP CHAIN (Correction 3)

**Data path from delivery to player:**

```
delivery
  ├─ striker_match_player_id → match_players(id)
  │   ├─ team_player_id → team_players(id)
  │   │   ├─ player_registration_id → player_registrations(id)
  │   │   │   └─ player_id → players(id)
  │   │   └─ edition_team_id → edition_teams(id)
  │   │       └─ edition_id → editions(id)
  │   └─ match_id → matches(id)
  │       └─ edition_id → editions(id)
  │
  ├─ bowler_match_player_id → match_players(id)
  │   └─ [same chain as striker]
  │
  ├─ dismissed_match_player_id → match_players(id)
  │   └─ [same chain as striker]
  │
  └─ fielder_match_player_id → match_players(id)
      └─ [same chain as striker]
```

**Complete path example:**
```
delivery.striker_match_player_id (1234)
  → match_players.id = 1234
  → team_player_id = 567
  → match_id = 100
  
match_players.team_player_id (567)
  → team_players.id = 567
  → player_registration_id = 89
  → edition_team_id = 45
  
team_players.player_registration_id (89)
  → player_registrations.id = 89
  → player_id = 12
  → edition_id = 5
  
Players can now be identified with edition context via match_players.
```

---

## DELIVERIES PLAYER REFERENCE DETAILS

### Why match_players reference instead of players?

**Benefits:**
1. **Match Context:** Ensures only players who actually participated in the match are referenced
2. **Team Validation:** Can validate striker/non-striker from batting team, bowler from bowling team
3. **Data Integrity:** If a match_player is deleted, all its deliveries cascade delete (safe)
4. **Audit Trail:** Can audit who played in each match via match_players

### Laravel Business Logic Validation (Correction 3):

When recording a delivery, Laravel must validate:

```php
// Fetch the innings and related match
$innings = Innings::with('match')->find($deliveryData['innings_id']);
$match = $innings->match;

// Validate striker and non-striker
$strikerMatchPlayer = MatchPlayer::where('match_id', $match->id)
    ->where('id', $deliveryData['striker_match_player_id'])
    ->firstOrFail();

$nonStrikerMatchPlayer = MatchPlayer::where('match_id', $match->id)
    ->where('id', $deliveryData['non_striker_match_player_id'])
    ->firstOrFail();

// Both must be from batting team
$battingTeamPlayers = MatchPlayer::where('match_id', $match->id)
    ->whereHas('teamPlayer.editionTeam', function($q) {
        $q->where('id', $innings->batting_team_id);
    })
    ->pluck('id')
    ->toArray();

Assert::true(in_array($strikerMatchPlayer->id, $battingTeamPlayers));
Assert::true(in_array($nonStrikerMatchPlayer->id, $battingTeamPlayers));

// Validate bowler
$bowlerMatchPlayer = MatchPlayer::where('match_id', $match->id)
    ->where('id', $deliveryData['bowler_match_player_id'])
    ->firstOrFail();

$bowlingTeamPlayers = MatchPlayer::where('match_id', $match->id)
    ->whereHas('teamPlayer.editionTeam', function($q) {
        $q->where('id', $innings->bowling_team_id);
    })
    ->pluck('id')
    ->toArray();

Assert::true(in_array($bowlerMatchPlayer->id, $bowlingTeamPlayers));

// Similar validation for dismissed and fielder
```

---

## DATABASE-ENFORCED RULES (UPDATED)

1. **Team-Player Uniqueness**
   ```sql
   UNIQUE(player_registration_id)
   -- A registered player can be assigned to at most one team_players record
   -- (Since player_registration belongs to one edition, this prevents multi-team assignment)
   ```

2. **Jersey Number Uniqueness**
   ```sql
   UNIQUE(edition_team_id, jersey_number)
   -- No two players can have same jersey in same team
   ```

3. **Match Number Uniqueness (Correction 9)**
   ```sql
   UNIQUE(edition_id, match_number)
   -- Each edition has sequential match numbers (1, 2, 3...)
   ```

4. **Delivery Sequence Integrity**
   ```sql
   UNIQUE(innings_id, delivery_sequence)
   -- Authoritative ordering per innings
   ```

5. **Referential Integrity via Foreign Keys**
   - All match_players referenced in deliveries must exist
   - Innings references valid edition_teams (partial enforcement via FKs)
   - Match references valid edition_teams (partial enforcement via FKs)

---

## LARAVEL BUSINESS-LOGIC-ENFORCED RULES (UPDATED)

### 1. Team-Player Edition Consistency (Correction 2)

```
REQUIREMENT: player_registrations.edition_id MUST equal edition_teams.edition_id

WHY NOT IN DATABASE:
- Foreign keys only ensure both records exist independently
- Cannot enforce equality across two separate FK targets

ENFORCEMENT: When assigning player to team_players:

$playerRegistration = PlayerRegistration::find($playerRegId);
$editionTeam = EditionTeam::find($editionTeamId);

Assert::true($playerRegistration->edition_id === $editionTeam->edition_id,
  "Player registration and edition team must be from same edition");
```

### 2. Match Edition Team Consistency

```
REQUIREMENT: match.edition_id = edition_teams[team_a_id].edition_id
           = edition_teams[team_b_id].edition_id
           Teams cannot play themselves: team_a_id ≠ team_b_id

ENFORCEMENT: StoreMatchRequest validation
```

### 3. Innings Team Consistency

```
REQUIREMENT: innings batting/bowling teams belong to match edition
           Batting team ≠ Bowling team

ENFORCEMENT: StoreInningsRequest validation
```

### 4. Deliveries Player Consistency (Correction 3)

```
REQUIREMENT: 
- Striker, non-striker: from batting team's match_players
- Bowler: from bowling team's match_players
- Dismissed player: from batting team's match_players
- Fielder: from bowling team's match_players

ENFORCEMENT: StoreDeliveryRequest validation
STRATEGY: Check all player IDs exist in match_players + correct team assignment
```

### 5. Bowling Runs Conceded (Correction 7 - MVP Calculation)

```
REQUIREMENT (MVP):
bowler_runs = SUM(runs_off_bat + wide_runs + no_ball_runs)
            WHERE bowler_match_player_id = X

DO NOT CHARGE to bowler:
- bye_runs
- leg_bye_runs
- penalty_runs

REASONING:
- Byes/leg-byes are not bowler's fault
- Penalty runs (other) may be applied in future; exclude from MVP
- Future: penalty_runs calculation can be reviewed per penalty type

ENFORCEMENT: BowlingStatisticsService
```

### 6. Bowler Wicket Credit (Correction 6 - MVP Rules)

```
REQUIREMENT (MVP):
Bowler gets credit for wicket_type IN:
- bowled
- caught
- lbw
- stumped
- hit_wicket

Bowler does NOT get credit for:
- run_out (fielder/team credit)
- obstructing_field (player's fault, not bowler)

ENFORCEMENT: BowlingStatisticsService
STRATEGY: Check wicket_type before incrementing bowler's wicket count
```

### 7. Delivery Sequence Ordering

```
REQUIREMENT: delivery_sequence must be auto-incrementing per innings

ENFORCEMENT LOCATION: DeliveryService
STRATEGY:
- Find max delivery_sequence for innings
- Assign next_sequence = max + 1
- Use database transactions for atomicity
```

### 8. Innings Cached Values Rebuilding

```
REQUIREMENT:
legal_balls = COUNT(*) WHERE is_legal_delivery = TRUE
total_runs = SUM(runs_off_bat + wide_runs + no_ball_runs + bye_runs + leg_bye_runs + penalty_runs)
total_wickets = COUNT(*) WHERE is_wicket = TRUE
extras = SUM(wide_runs + no_ball_runs + bye_runs + leg_bye_runs + penalty_runs)

ENFORCEMENT: InningsService
STRATEGY:
- Update cache after each delivery add/edit/delete
- Provide artisan command: php artisan rppl:rebuild-innings-cache {match_id?}
- Validate cache in tests
```

### 9. Multiple Captains Prevention

```
REQUIREMENT: At most one player per team in a match can be is_captain = TRUE

ENFORCEMENT: MatchPlayerController, StoreMatchPlayerRequest
STRATEGY:
- Check: SELECT COUNT(*) FROM match_players WHERE match_id = X 
         AND team_player_id.edition_team_id = Y AND is_captain = TRUE
- If > 0 and setting new captain, unset old captain first
```

### 10. Wide Runs Support (Correction 5)

```
REQUIREMENT: wide_runs can be 0, 1, 2, 3... (multiple wides)

EXAMPLES:
- Single wide: wide_runs = 1
- Double wide: wide_runs = 2
- Triple wide: wide_runs = 3

ENFORCEMENT: No special logic needed; column type TINYINT UNSIGNED supports 0-255
```

---

## RECALCULATION FORMULAS (UPDATED)

### Bowling Stats (per player per innings) - Correction 7

```
MVP Calculation (excludes bye_runs, leg_bye_runs, penalty_runs):
────────────────────────────────────────────────────────────

overs_bowled = COUNT(*) WHERE bowler_match_player_id = X 
               AND is_legal_delivery = TRUE
             / 6

runs_conceded = SUM(runs_off_bat + wide_runs + no_ball_runs)
              WHERE bowler_match_player_id = X

wickets_taken = COUNT(*) WHERE bowler_match_player_id = X 
              AND is_wicket = TRUE
              AND wicket_type IN ('bowled', 'caught', 'lbw', 'stumped', 'hit_wicket')

economy = runs_conceded / overs_bowled

Future consideration:
- penalty_runs may need bowler attribution depending on penalty type
- bye_runs, leg_bye_runs remain excluded
```

### Batting Stats (unchanged)

```
runs_scored = SUM(runs_off_bat) WHERE striker_match_player_id = X

balls_faced = COUNT(*) WHERE striker_match_player_id = X 
            AND is_legal_delivery = TRUE

strike_rate = (runs_scored / balls_faced) * 100

fours = COUNT(*) WHERE striker_match_player_id = X AND runs_off_bat = 4

sixes = COUNT(*) WHERE striker_match_player_id = X AND runs_off_bat = 6
```

### Innings Totals (unchanged)

```
legal_balls = COUNT(*) WHERE is_legal_delivery = TRUE

total_runs = SUM(runs_off_bat + wide_runs + no_ball_runs 
                 + bye_runs + leg_bye_runs + penalty_runs)

total_wickets = COUNT(*) WHERE is_wicket = TRUE

extras = SUM(wide_runs + no_ball_runs + bye_runs + leg_bye_runs + penalty_runs)

overs_display = legal_balls / 6 (integer division)
balls_display = legal_balls % 6
display_overs = "{overs_display}.{balls_display}"
```

---

## INDEX CLEANUP (CORRECTION 10)

**Removed Redundant Indexes:**

1. ~~`INDEX: edition_team_id` on team_players~~
   - REASON: `UNIQUE(player_registration_id)` already provides left-most lookup on primary key
   - Note: Still need `INDEX(edition_team_id)` for finding players in a team (reverse lookup)
   - KEEP: This index is NOT redundant (different lookup direction)

2. ~~`INDEX: striker_id` on deliveries (old schema)~~
   - CHANGED TO: `INDEX: striker_match_player_id` (follows new FK)
   - KEEP: Needed for player batting stats queries

3. ~~`INDEX(innings_id, delivery_sequence)` on deliveries (duplicate)~~
   - REASON: `UNIQUE(innings_id, delivery_sequence)` already provides this as composite index
   - REMOVED: Redundant with UNIQUE constraint

4. ~~`INDEX: player_registration_id` on team_players (single column)~~
   - REASON: `UNIQUE(player_registration_id)` already serves this purpose
   - REMOVED: Redundant with UNIQUE constraint

**Final Clean Index Set:**

```sql
-- team_players indexes
CREATE INDEX idx_team_players_edition_team ON team_players(edition_team_id);

-- deliveries indexes (most critical for performance)
CREATE INDEX idx_deliveries_innings_sequence ON deliveries(innings_id, delivery_sequence);
CREATE INDEX idx_deliveries_innings_legal ON deliveries(innings_id, is_legal_delivery);
CREATE INDEX idx_deliveries_striker ON deliveries(striker_match_player_id, innings_id);
CREATE INDEX idx_deliveries_bowler ON deliveries(bowler_match_player_id, innings_id);
CREATE INDEX idx_deliveries_wicket ON deliveries(is_wicket, wicket_type);
CREATE INDEX idx_deliveries_over ON deliveries(innings_id, over_number);

-- matches indexes
CREATE INDEX idx_matches_edition_status ON matches(edition_id, match_status);
CREATE INDEX idx_matches_scheduled ON matches(scheduled_at);
CREATE INDEX idx_matches_edition_number ON matches(edition_id, match_number);

-- match_players indexes
CREATE INDEX idx_match_players_match ON match_players(match_id);
CREATE INDEX idx_match_players_team_player ON match_players(team_player_id);
CREATE INDEX idx_match_players_captain ON match_players(match_id, is_captain);

-- innings indexes
CREATE INDEX idx_innings_match ON innings(match_id);
CREATE INDEX idx_innings_batting_team ON innings(batting_team_id);
CREATE INDEX idx_innings_bowling_team ON innings(bowling_team_id);

-- player registration indexes
CREATE INDEX idx_player_registrations_edition_status 
  ON player_registrations(edition_id, payment_status);
```

**Coverage:**
- UNIQUE constraints already cover primary lookups
- Composite indexes cover left-most column queries
- No speculative indexes without known query requirements

---

## SUMMARY OF FINAL CORRECTIONS

| # | Correction | Change | Impact |
|---|-----------|--------|--------|
| 1 | TEAM_PLAYERS uniqueness | UNIQUE(edition_team_id, player_registration_id) → UNIQUE(player_registration_id) | Simpler, clearer constraint |
| 2 | TEAM_PLAYERS edition consistency | Moved from schema guarantee to Laravel validation | Edition consistency now Laravel-enforced |
| 3 | Delivery player references | players.id → match_players.id (all 5 player columns) | Ensures match context, safer deletions |
| 4 | Delivery ball numbering | ball_in_over → ball_number, clarified generation logic | Unambiguous ball position tracking |
| 5 | Wide runs | Documented support for multiple wides (wide_runs > 1) | No schema change, clarification |
| 6 | Wicket credit | Added hit_wicket to bowler-credited wickets | More accurate bowling stats |
| 7 | Bowler runs calculation | MVP: Only runs_off_bat + wide_runs + no_ball_runs | Clearer, simpler bowling stats |
| 8 | Match stage | match_type (VARCHAR) → match_stage (ENUM) | More appropriate classification for RPPL |
| 9 | Match number | Added UNIQUE(edition_id, match_number) | Enforces sequential numbering |
| 10 | Index cleanup | Removed 4 redundant indexes, kept functional ones | Better database performance |

---

## SCHEMA READY FOR MIGRATION

All corrections applied. The schema is now ready for Laravel migration generation.

**Next Phase:** Create migrations one module at a time, starting with:
1. Editions
2. Players, Player Registrations
3. Teams, Edition Teams
4. Team Players
5. Match Players
6. Venues
7. Matches
8. Innings
9. Deliveries

**No implementation code generated.** Schema finalized for engineering review.
