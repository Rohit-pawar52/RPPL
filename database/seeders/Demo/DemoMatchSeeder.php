<?php

namespace Database\Seeders\Demo;

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
 * The match lifecycle + ball-by-ball scoring dataset. Every state change
 * (Playing XI, toss, live, deliveries, innings/match completion,
 * cancellation, abandonment) goes through the real domain services — the
 * exact same ones the admin scoring UI calls — never a raw totals write,
 * so scorecards/statistics/standings emerge naturally and stay internally
 * consistent, per this phase's explicit instruction.
 *
 * Every seeded match uses overs_per_innings = 2 (12 legal balls) purely
 * to keep the Delivery dataset small and fast to seed — nothing about the
 * scoring engine changes for a short match.
 *
 * Every seeded, played match fixes the toss winner as edition_team_a_id
 * choosing to 'bat' — a deliberate simplification so which of the two
 * Playing XIs is "batting first" never has to be re-derived per match;
 * InningsService::determineFirstInningsTeams() still performs the real
 * derivation, this just keeps its input constant.
 */
class DemoMatchSeeder extends Seeder
{
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
        $historical = Edition::where('year', DemoEditionSeeder::HISTORICAL_YEAR)->firstOrFail();
        $active = Edition::where('year', DemoEditionSeeder::ACTIVE_YEAR)->firstOrFail();
        $upcoming = Edition::where('year', DemoEditionSeeder::UPCOMING_YEAR)->firstOrFail();

        $venues = Venue::orderBy('id')->get()->values();

        $this->seedHistoricalMatches($historical, $venues);
        $this->seedActiveMatches($active, $venues);
        $this->seedUpcomingMatches($upcoming, $venues);
    }

    /**
     * 2 completed matches among the 4 historical teams — enough for
     * standings/statistics to have real, non-trivial completed-match data
     * for a wrapped-up season.
     */
    private function seedHistoricalMatches(Edition $edition, Collection $venues): void
    {
        $teams = EditionTeam::where('edition_id', $edition->id)->orderBy('id')->get()->values();

        if (GameMatch::where('edition_id', $edition->id)->exists()) {
            return;
        }

        $this->playCompletedMatch($edition, $teams[0], $teams[1], $venues[0], matchNumber: 1, scheduledAt: now()->subMonths(13), offset: 0);
        $this->playCompletedMatch($edition, $teams[2], $teams[3], $venues[1], matchNumber: 2, scheduledAt: now()->subMonths(13)->addDays(3), offset: 6);
    }

    /**
     * The full lifecycle demonstration edition: scheduled, toss, live,
     * two completed matches, one cancelled, one abandoned (with its
     * partial delivery history preserved) — exercising every match_status
     * this schema supports except 'toss' being reused twice below.
     */
    private function seedActiveMatches(Edition $edition, Collection $venues): void
    {
        $teams = EditionTeam::where('edition_id', $edition->id)->orderBy('id')->get()->values();

        if (GameMatch::where('edition_id', $edition->id)->exists()) {
            return;
        }

        $this->playCompletedMatch($edition, $teams[0], $teams[1], $venues[0], matchNumber: 1, scheduledAt: now()->subDays(20), offset: 1);
        $this->playCompletedMatch($edition, $teams[2], $teams[3], $venues[1], matchNumber: 2, scheduledAt: now()->subDays(15), offset: 9);
        $this->playLiveMatch($edition, $teams[4], $teams[5], $venues[2], matchNumber: 3, scheduledAt: now()->subHours(2));
        $this->playTossStageMatch($edition, $teams[0], $teams[2], $venues[0], matchNumber: 4, scheduledAt: now()->addHours(4));
        $this->playAbandonedMatch($edition, $teams[1], $teams[3], $venues[1], matchNumber: 5, scheduledAt: now()->subDays(2));
        $this->playCancelledMatch($edition, $teams[4], $teams[1], $venues[2], matchNumber: 6, scheduledAt: now()->subDays(1));
        $this->createScheduledMatch($edition, $teams[5], $teams[0], $venues[0], matchNumber: 7, scheduledAt: now()->addDays(5));
    }

    /**
     * Mostly plain scheduled fixtures — a draft edition that hasn't
     * started yet has nothing to score.
     */
    private function seedUpcomingMatches(Edition $edition, Collection $venues): void
    {
        $teams = EditionTeam::where('edition_id', $edition->id)->orderBy('id')->get()->values();

        if (GameMatch::where('edition_id', $edition->id)->exists()) {
            return;
        }

        $this->createScheduledMatch($edition, $teams[0], $teams[1], $venues[0], matchNumber: 1, scheduledAt: now()->addMonths(3));
        $this->createScheduledMatch($edition, $teams[1], $teams[0], $venues[1], matchNumber: 2, scheduledAt: now()->addMonths(3)->addDays(2));
    }

    private function createMatch(Edition $edition, EditionTeam $teamA, EditionTeam $teamB, Venue $venue, int $matchNumber, \DateTimeInterface $scheduledAt): GameMatch
    {
        return $this->matches->createMatch([
            'edition_id' => $edition->id,
            'match_number' => $matchNumber,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'venue_id' => $venue->id,
            'match_stage' => 'league',
            'overs_per_innings' => 2,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    private function createScheduledMatch(Edition $edition, EditionTeam $teamA, EditionTeam $teamB, Venue $venue, int $matchNumber, \DateTimeInterface $scheduledAt): GameMatch
    {
        return $this->createMatch($edition, $teamA, $teamB, $venue, $matchNumber, $scheduledAt);
    }

    private function playCancelledMatch(Edition $edition, EditionTeam $teamA, EditionTeam $teamB, Venue $venue, int $matchNumber, \DateTimeInterface $scheduledAt): void
    {
        $match = $this->createMatch($edition, $teamA, $teamB, $venue, $matchNumber, $scheduledAt);

        $this->assert($this->flow->cancelMatch($match), 'cancelMatch', $match);
    }

    private function playTossStageMatch(Edition $edition, EditionTeam $teamA, EditionTeam $teamB, Venue $venue, int $matchNumber, \DateTimeInterface $scheduledAt): void
    {
        $match = $this->createMatch($edition, $teamA, $teamB, $venue, $matchNumber, $scheduledAt);

        $this->buildPlayingXi($match, $teamA);
        $this->buildPlayingXi($match, $teamB);

        $this->assert($this->flow->startToss($match->fresh()), 'startToss', $match);
        $this->assert($this->flow->recordToss($match->fresh(), [
            'toss_winner_team_id' => $teamA->id,
            'toss_decision' => 'bat',
        ]), 'recordToss', $match);
    }

    private function playLiveMatch(Edition $edition, EditionTeam $teamA, EditionTeam $teamB, Venue $venue, int $matchNumber, \DateTimeInterface $scheduledAt): void
    {
        [$match, $xiA, $xiB] = $this->setUpAndStartMatch($edition, $teamA, $teamB, $venue, $matchNumber, $scheduledAt);

        $firstInnings = $this->startFirstInnings($match);

        // Only a handful of deliveries — this is the ONE match left
        // genuinely "in progress" for the live scoring / public live page
        // demo, deliberately never completed or finalized.
        $this->playAutomatedInnings($match->fresh(), $firstInnings, $xiA, $xiB->first(), maxWicketsToFall: 1, offset: 0, maxDeliveries: 5);
    }

    private function playAbandonedMatch(Edition $edition, EditionTeam $teamA, EditionTeam $teamB, Venue $venue, int $matchNumber, \DateTimeInterface $scheduledAt): void
    {
        [$match, $xiA, $xiB] = $this->setUpAndStartMatch($edition, $teamA, $teamB, $venue, $matchNumber, $scheduledAt);

        $firstInnings = $this->startFirstInnings($match);

        $this->playAutomatedInnings($match->fresh(), $firstInnings, $xiA, $xiB->first(), maxWicketsToFall: 1, offset: 2, maxDeliveries: 6);

        // Abandoned with partial delivery history — MatchFlowService never
        // touches Innings/Delivery rows, so that history is left exactly
        // as scored.
        $this->assert($this->flow->abandonMatch($match->fresh()), 'abandonMatch', $match);
    }

    private function playCompletedMatch(Edition $edition, EditionTeam $teamA, EditionTeam $teamB, Venue $venue, int $matchNumber, \DateTimeInterface $scheduledAt, int $offset): void
    {
        [$match, $xiA, $xiB] = $this->setUpAndStartMatch($edition, $teamA, $teamB, $venue, $matchNumber, $scheduledAt);

        $firstInnings = $this->startFirstInnings($match);
        $this->playAutomatedInnings($match->fresh(), $firstInnings, $xiA, $xiB->first(), maxWicketsToFall: 3, offset: $offset);

        $this->assert($this->innings->canStartSecondInnings($match->fresh()), 'canStartSecondInnings', $match);
        $this->assert($this->innings->startSecondInnings($match->fresh()), 'startSecondInnings', $match);

        $secondInnings = Innings::where('match_id', $match->id)->where('innings_number', 2)->firstOrFail();
        $this->playAutomatedInnings($match->fresh(), $secondInnings, $xiB, $xiA->first(), maxWicketsToFall: 3, offset: $offset + 20);

        $this->assert($this->results->finalizeMatch($match->fresh()), 'finalizeMatch', $match);
    }

    /**
     * @return array{0: GameMatch, 1: Collection<int, MatchPlayer>, 2: Collection<int, MatchPlayer>}
     */
    private function setUpAndStartMatch(Edition $edition, EditionTeam $teamA, EditionTeam $teamB, Venue $venue, int $matchNumber, \DateTimeInterface $scheduledAt): array
    {
        $match = $this->createMatch($edition, $teamA, $teamB, $venue, $matchNumber, $scheduledAt);

        $xiA = $this->buildPlayingXi($match, $teamA);
        $xiB = $this->buildPlayingXi($match, $teamB);

        $this->assert($this->flow->startToss($match->fresh()), 'startToss', $match);
        $this->assert($this->flow->recordToss($match->fresh(), [
            'toss_winner_team_id' => $teamA->id,
            'toss_decision' => 'bat',
        ]), 'recordToss', $match);
        $this->assert($this->flow->startMatch($match->fresh()), 'startMatch', $match);

        return [$match->fresh(), $xiA, $xiB];
    }

    private function startFirstInnings(GameMatch $match): Innings
    {
        $this->assert($this->innings->startFirstInnings($match->fresh()), 'startFirstInnings', $match);

        return Innings::where('match_id', $match->id)->where('innings_number', 1)->firstOrFail();
    }

    /**
     * Selects the whole squad as the Playing XI (no "exactly 11" rule
     * exists anywhere in this project — see MatchFlowService's docblock),
     * names a captain and, when the squad has one, a wicket-keeper.
     *
     * @return Collection<int, MatchPlayer> in squad (batting) order
     */
    private function buildPlayingXi(GameMatch $match, EditionTeam $editionTeam): Collection
    {
        $teamPlayers = TeamPlayer::where('edition_team_id', $editionTeam->id)->orderBy('jersey_number')->get();

        $matchPlayers = $teamPlayers->map(fn (TeamPlayer $teamPlayer) => $this->matchPlayers->addPlayer($match, $teamPlayer));

        $keeper = $matchPlayers->first(fn (MatchPlayer $mp) => $mp->teamPlayer->role === 'wicket_keeper')
            ?? $matchPlayers->get(1)
            ?? $matchPlayers->first();

        $this->matchPlayers->setCaptain($matchPlayers->first());
        $this->matchPlayers->setWicketKeeper($keeper);

        return $matchPlayers->values();
    }

    /**
     * Scores deliveries via the real DeliveryService until the innings
     * either auto-completes (all out / overs exhausted / target reached —
     * see DeliveryService::hasReachedAutomaticCompletion()) or
     * $maxDeliveries is reached (used only for the deliberately partial
     * live/abandoned matches). The next striker/non-striker is always
     * read back from DeliveryService::expectedBattingState() — never
     * computed independently here — so this can never submit a
     * combination the same validation the real scoring UI uses would
     * reject.
     *
     * $offset shifts where the deterministic run/extra/wicket pattern
     * starts, purely so different matches don't all produce an identical
     * score — every outcome this produces (won by runs, won by wickets,
     * tied) is a genuine, service-derived result, never chosen up front.
     *
     * @param  Collection<int, MatchPlayer>  $battingXi
     */
    private function playAutomatedInnings(GameMatch $match, Innings $innings, Collection $battingXi, MatchPlayer $bowler, int $maxWicketsToFall, int $offset, ?int $maxDeliveries = null): void
    {
        $nextBatterIndex = 2;
        $wicketsSoFar = 0;
        $delivered = 0;
        $ballCounter = $offset;

        while ($this->deliveries->canRecordDelivery($match, $innings->fresh())) {
            if ($maxDeliveries !== null && $delivered >= $maxDeliveries) {
                break;
            }

            $freshInnings = $innings->fresh();
            $state = $this->deliveries->expectedBattingState($freshInnings);
            $ballCounter++;

            if ($state['first_ball']) {
                $strikerId = $battingXi[0]->id;
                $nonStrikerId = $battingXi[1]->id;
            } elseif ($state['requires_replacement']) {
                if ($nextBatterIndex >= $battingXi->count()) {
                    // Ran out of scripted batters (short demo squads) —
                    // stop rather than force an invalid submission; the
                    // innings simply stays as scored so far.
                    break;
                }

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

            $isWicketBall = $wicketsSoFar < $maxWicketsToFall && $ballCounter % 5 === 0;
            $extraType = null;
            $extraAmount = 0;
            $runsOffBat = 0;

            if (! $isWicketBall) {
                if ($ballCounter % 7 === 0) {
                    $extraType = 'wide';
                    $extraAmount = 1;
                } elseif ($ballCounter % 11 === 0) {
                    $extraType = 'bye';
                    $extraAmount = 2;
                } elseif ($ballCounter % 13 === 0) {
                    $extraType = 'no_ball';
                    $runsOffBat = 1;
                } else {
                    $runsPattern = [1, 4, 0, 2, 6, 1, 0, 1, 3, 0];
                    $runsOffBat = $runsPattern[$ballCounter % count($runsPattern)];
                }
            }

            $data = [
                'striker_match_player_id' => $strikerId,
                'non_striker_match_player_id' => $nonStrikerId,
                'bowler_match_player_id' => $bowler->id,
                'runs_off_bat' => $runsOffBat,
                'extra_type' => $extraType,
                'extra_amount' => $extraAmount,
                'is_wicket' => $isWicketBall,
            ];

            if ($isWicketBall) {
                $data['wicket_type'] = 'bowled';
                $data['dismissed_match_player_id'] = $strikerId;
                $wicketsSoFar++;
            }

            $this->deliveries->recordDelivery($match, $freshInnings, $data);
            $delivered++;
        }
    }

    private function assert(bool $ok, string $step, GameMatch $match): void
    {
        if (! $ok) {
            throw new RuntimeException("Demo match seeding failed at step [{$step}] for match #{$match->id}.");
        }
    }
}
