<?php

namespace Database\Seeders\Rppl2026;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\TeamPlayer;
use App\Models\Venue;
use App\Services\GameMatch\GameMatchService;
use App\Services\GameMatch\MatchFlowService;
use App\Services\GameMatch\MatchResultService;
use App\Services\Innings\InningsService;
use App\Services\MatchPlayer\MatchPlayerService;
use App\Services\Scoring\DeliveryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * The 6 RPPL 2026 league fixtures for the local UAT reset: 3 played to a
 * real, internally-consistent completed result through the actual
 * match/innings/scoring domain services (never a raw totals write), and
 * 3 left genuinely scheduled — with squads ready but no Playing XI/toss
 * yet — so manual UAT can exercise the full upcoming-match lifecycle
 * from a clean starting point.
 *
 * Every completed match is driven ball-by-ball from an explicit,
 * hand-written script (playScriptedInnings()) rather than a generic
 * formula — this is what lets each of the 3 completed matches tell a
 * genuinely different, deliberately varied story (a comfortable run
 * chase failure, a target reached mid-over, a No Ball + Free Hit + Wide
 * + byes sequence) while every number in this file is still exactly
 * what ends up in the database: nothing here writes to Innings/Delivery
 * directly, so scorecards/standings/FOW/partnerships all emerge from
 * the same engine the real scoring UI uses.
 *
 * The Final is deliberately NOT created — see the completion report:
 * with only 3 of 6 league matches played, the league-stage Top 2 is not
 * yet known, and this task's own instructions are explicit that a
 * placeholder-team Final must never be hacked into the schema.
 *
 * overs_per_innings = 5 for every seeded match (not this project's
 * default of 20) purely so a from-scratch manual UAT match takes a few
 * minutes, not the better part of an hour — an explicit, local-only demo
 * choice, never a production rule change.
 */
class Rppl2026FixtureSeeder extends Seeder
{
    private const OVERS_PER_INNINGS = 5;

    public function __construct(
        private readonly GameMatchService $matches,
        private readonly MatchPlayerService $matchPlayers,
        private readonly MatchFlowService $flow,
        private readonly InningsService $innings,
        private readonly DeliveryService $deliveries,
        private readonly MatchResultService $results,
    ) {}

    public function run(): void
    {
        $edition = Edition::where('year', Rppl2026EditionSeeder::YEAR)->firstOrFail();

        if (GameMatch::where('edition_id', $edition->id)->exists()) {
            return;
        }

        $mi = $this->editionTeam($edition, 'MI');
        $rcb = $this->editionTeam($edition, 'RCB');
        $csk = $this->editionTeam($edition, 'CSK');
        $kkr = $this->editionTeam($edition, 'KKR');

        $venues = Venue::orderBy('id')->get()->values();

        $this->playCompletedMatch($edition, $mi, $rcb, $venues[0], 1, now()->subDays(10), $this->miVsRcbMiInnings(), $this->miVsRcbRcbInnings());
        $this->playCompletedMatch($edition, $csk, $kkr, $venues[1], 2, now()->subDays(7), $this->cskVsKkrCskInnings(), $this->cskVsKkrKkrInnings());
        $this->playCompletedMatch($edition, $mi, $csk, $venues[0], 3, now()->subDays(4), $this->miVsCskMiInnings(), $this->miVsCskCskInnings());

        $this->createScheduledMatch($edition, $rcb, $kkr, $venues[1], 4, now()->addDays(3));
        $this->createScheduledMatch($edition, $mi, $kkr, $venues[0], 5, now()->addDays(6));
        $this->createScheduledMatch($edition, $rcb, $csk, $venues[1], 6, now()->addDays(9));
    }

    private function editionTeam(Edition $edition, string $shortName): EditionTeam
    {
        return EditionTeam::where('edition_id', $edition->id)
            ->whereHas('team', fn ($query) => $query->where('short_name', $shortName))
            ->firstOrFail();
    }

    // ----- Match scripts (frozen S02 rules apply exactly as they would to a real match) -----

    /**
     * @return list<array<string, mixed>>
     */
    private function miVsRcbMiInnings(): array
    {
        // 39/3 off 5 overs. Fours, a six, dots, singles, one Wide, three
        // wickets — the "straightforward" match, nothing exotic.
        return [
            ...$this->over(4, 1, 0, 6, 1, 0),
            ...$this->over(1, 1, 'W', 0, 1, 1),
            ['wide' => true], ...$this->over(0, 4, 1, 1, 0, 1),
            ...$this->over(1, 1, 4, 0, 'W', 1),
            ...$this->over(6, 1, 0, 1, 0, 'W'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function miVsRcbRcbInnings(): array
    {
        // 24/2 off 5 overs — well short of MI's 40 target: a clean,
        // comfortable "lost by runs" chase.
        return [
            ...$this->over(0, 1, 0, 4, 0, 1),
            ...$this->over(1, 0, 'W', 0, 1, 0),
            ...$this->over(0, 1, 1, 0, 4, 0),
            ...$this->over(1, 0, 0, 'W', 1, 0),
            ...$this->over(0, 1, 6, 0, 1, 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cskVsKkrCskInnings(): array
    {
        // 39/2 off 5 overs.
        return [
            ...$this->over(4, 0, 1, 1, 0, 6),
            ...$this->over(0, 1, 'W', 1, 0, 1),
            ...$this->over(1, 6, 0, 1, 1, 0),
            ...$this->over(0, 6, 1, 0, 'W', 1),
            ...$this->over(1, 0, 4, 1, 0, 1),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cskVsKkrKkrInnings(): array
    {
        // A successful chase that reaches the target (40) exactly on the
        // 12th legal ball — the innings auto-completes mid-over, well
        // before the 30-ball limit, on purpose: this is the scenario for
        // testing target/runs-needed/balls-remaining/RRR and target-
        // reached auto-completion.
        return [
            ...$this->over(6, 4, 6, 'W', 0, 4),
            ...$this->over(4, 6, 1, 4, 1, 4),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function miVsCskMiInnings(): array
    {
        // 48/3 off 5 overs — includes a No Ball immediately followed by
        // a Free Hit boundary, a Wide, and a bye, alongside the wickets.
        return [
            ...$this->over(1, 4, 0, 1, 6, 0),
            ['no_ball' => true, 'runs' => 1], ...$this->over(6, 0, 1, 'W', 0, 1),
            ['wide' => true], ...$this->over(1, 0, 1, 4, 0, 1),
            ...$this->over(0, 1, 'W', 1, 4, 0),
            ...$this->overWithBye(1, 6, 0, 'W', 4, 1),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function miVsCskCskInnings(): array
    {
        // 23/3 off 5 overs — well short of MI's 49 target.
        return [
            ...$this->over(0, 1, 0, 4, 0, 1),
            ...$this->over(1, 0, 'W', 1, 0, 1),
            ...$this->over(0, 4, 0, 1, 0, 1),
            ...$this->over(1, 0, 'W', 0, 1, 4),
            ...$this->over(0, 1, 0, 'W', 1, 0),
        ];
    }

    /**
     * One 6-legal-ball over from a compact shorthand: an int is bat
     * runs, the string 'W' is a wicket (bowled, 0 runs).
     *
     * @return list<array<string, mixed>>
     */
    private function over(int|string ...$balls): array
    {
        return array_map(fn ($ball) => $ball === 'W' ? ['wicket' => true] : ['runs' => $ball], $balls);
    }

    /**
     * Same shorthand as over(), but the LAST ball is a bye of the given
     * run count instead of bat runs.
     *
     * @return list<array<string, mixed>>
     */
    private function overWithBye(int|string ...$ballsThenByeRuns): array
    {
        $byeRuns = array_pop($ballsThenByeRuns);
        $spec = $this->over(...$ballsThenByeRuns);
        $spec[] = ['bye' => $byeRuns];

        return $spec;
    }

    // ----- Match lifecycle plumbing (mirrors Demo\DemoMatchSeeder's proven pattern) -----

    private function createMatch(Edition $edition, EditionTeam $teamA, EditionTeam $teamB, Venue $venue, int $matchNumber, \DateTimeInterface $scheduledAt): GameMatch
    {
        return $this->matches->createMatch([
            'edition_id' => $edition->id,
            'match_number' => $matchNumber,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'venue_id' => $venue->id,
            'match_stage' => 'league',
            'overs_per_innings' => self::OVERS_PER_INNINGS,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    private function createScheduledMatch(Edition $edition, EditionTeam $teamA, EditionTeam $teamB, Venue $venue, int $matchNumber, \DateTimeInterface $scheduledAt): void
    {
        $this->createMatch($edition, $teamA, $teamB, $venue, $matchNumber, $scheduledAt);
    }

    /**
     * @param  list<array<string, mixed>>  $firstInningsScript
     * @param  list<array<string, mixed>>  $secondInningsScript
     */
    private function playCompletedMatch(
        Edition $edition,
        EditionTeam $teamA,
        EditionTeam $teamB,
        Venue $venue,
        int $matchNumber,
        \DateTimeInterface $scheduledAt,
        array $firstInningsScript,
        array $secondInningsScript,
    ): void {
        $match = $this->createMatch($edition, $teamA, $teamB, $venue, $matchNumber, $scheduledAt);

        $xiA = $this->buildPlayingXi($match, $teamA);
        $xiB = $this->buildPlayingXi($match, $teamB);

        $this->assert($this->flow->startToss($match->fresh()), 'startToss', $match);
        $this->assert($this->flow->recordToss($match->fresh(), [
            'toss_winner_team_id' => $teamA->id,
            'toss_decision' => 'bat',
        ]), 'recordToss', $match);
        $this->assert($this->flow->startMatch($match->fresh()), 'startMatch', $match);

        $this->assert($this->innings->startFirstInnings($match->fresh()), 'startFirstInnings', $match);
        $firstInnings = Innings::where('match_id', $match->id)->where('innings_number', 1)->firstOrFail();
        $this->assert($this->innings->setUpOpeningState($match->fresh(), $firstInnings, $xiA[0]->id, $xiA[1]->id, $xiB[0]->id), 'setUpOpeningState(1)', $match);
        $this->playScriptedInnings($match->fresh(), $firstInnings->fresh(), $xiA, $xiB, $firstInningsScript);

        $this->assert($this->innings->canStartSecondInnings($match->fresh()), 'canStartSecondInnings', $match);
        $this->assert($this->innings->startSecondInnings($match->fresh()), 'startSecondInnings', $match);
        $secondInnings = Innings::where('match_id', $match->id)->where('innings_number', 2)->firstOrFail();
        $this->assert($this->innings->setUpOpeningState($match->fresh(), $secondInnings, $xiB[0]->id, $xiB[1]->id, $xiA[0]->id), 'setUpOpeningState(2)', $match);
        $this->playScriptedInnings($match->fresh(), $secondInnings->fresh(), $xiB, $xiA, $secondInningsScript);

        $this->assert($this->results->finalizeMatch($match->fresh()), 'finalizeMatch', $match);
    }

    /**
     * The first 11 squad members (by jersey number) become the Playing
     * XI for a completed demo match — the remaining 4 are the bench.
     * Names a captain and, when the XI has one, a wicket-keeper.
     *
     * @return Collection<int, MatchPlayer>
     */
    private function buildPlayingXi(GameMatch $match, EditionTeam $editionTeam): Collection
    {
        $teamPlayers = TeamPlayer::where('edition_team_id', $editionTeam->id)->orderBy('jersey_number')->limit(11)->get();

        $matchPlayers = $teamPlayers->map(fn (TeamPlayer $teamPlayer) => $this->matchPlayers->addPlayer($match, $teamPlayer));

        $keeper = $matchPlayers->first(fn (MatchPlayer $mp) => $mp->teamPlayer->role === 'wicket_keeper')
            ?? $matchPlayers->first();

        $this->matchPlayers->setCaptain($matchPlayers->first());
        $this->matchPlayers->setWicketKeeper($keeper);

        return $matchPlayers->values();
    }

    /**
     * Plays an explicit ball-outcome script through the real
     * DeliveryService, deriving striker/non-striker/bowler from
     * expectedBattingState() exactly like Demo\DemoMatchSeeder's own
     * playAutomatedInnings() — never computed independently here, so
     * this can never submit a combination the real scoring UI's own
     * validation would reject. Stops early (before the script is
     * exhausted) if the innings ends on its own — all out, overs
     * exhausted, or (second innings) the chase target reached — exactly
     * the scenario cskVsKkrKkrInnings() is written to trigger.
     *
     * $bowlingXi is rotated one-over-per-bowler (index 0 already bowled
     * over 0 via the opening setUpOpeningState() call, so this starts
     * over 1 at index 1) — a demo-data-only rule (see class docblock),
     * never a new production bowler-quota rule.
     *
     * @param  Collection<int, MatchPlayer>  $battingXi
     * @param  Collection<int, MatchPlayer>  $bowlingXi
     * @param  list<array<string, mixed>>  $script
     */
    private function playScriptedInnings(GameMatch $match, Innings $innings, Collection $battingXi, Collection $bowlingXi, array $script): void
    {
        $nextBatterIndex = 2;
        $bowlerRotationIndex = 1;
        $currentBowlerId = $bowlingXi[0]->id;

        foreach ($script as $spec) {
            $freshInnings = $innings->fresh();

            if (! $this->deliveries->canRecordDelivery($match, $freshInnings)) {
                break;
            }

            $state = $this->deliveries->expectedBattingState($freshInnings);

            if ($state['awaiting_new_over_bowler']) {
                $currentBowlerId = $bowlingXi[$bowlerRotationIndex % $bowlingXi->count()]->id;
                $bowlerRotationIndex++;
            }

            if ($state['first_ball']) {
                $strikerId = $battingXi[0]->id;
                $nonStrikerId = $battingXi[1]->id;
            } elseif ($state['requires_replacement']) {
                $newBatterId = $battingXi[$nextBatterIndex]->id;
                $nextBatterIndex++;

                if ($state['survivor_end'] === 'striker') {
                    $strikerId = $state['survivor_id'];
                    $nonStrikerId = $newBatterId;
                } else {
                    $nonStrikerId = $state['survivor_id'];
                    $strikerId = $newBatterId;
                }
            } else {
                $strikerId = $state['striker_id'];
                $nonStrikerId = $state['non_striker_id'];
            }

            $isWicket = $spec['wicket'] ?? false;

            $data = [
                'striker_match_player_id' => $strikerId,
                'non_striker_match_player_id' => $nonStrikerId,
                'bowler_match_player_id' => $currentBowlerId,
                'runs_off_bat' => $spec['runs'] ?? 0,
                'is_wide' => $spec['wide'] ?? false,
                'is_no_ball' => $spec['no_ball'] ?? false,
                'bye_runs' => $spec['bye'] ?? 0,
                'is_wicket' => $isWicket,
            ];

            if ($isWicket) {
                $data['wicket_type'] = 'bowled';
                $data['dismissed_match_player_id'] = $strikerId;
            }

            $this->deliveries->recordDelivery($match, $freshInnings, $data);
        }
    }

    private function assert(bool $ok, string $step, GameMatch $match): void
    {
        if (! $ok) {
            throw new RuntimeException("RPPL 2026 fixture seeding failed at step [{$step}] for match #{$match->id}.");
        }
    }
}
