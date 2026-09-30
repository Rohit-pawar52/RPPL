<?php

namespace Database\Seeders\Rppl2025;

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
use App\Services\Statistics\StandingsService;
use Database\Seeders\Rppl2026\Rppl2026TeamAndVenueSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * The completed RPPL 2025 season: 6 league matches (every pair once) and a
 * Final between the league's top two, all played ball-by-ball through the
 * real match/innings/scoring services from hand-written scripts (nothing
 * writes innings or delivery totals directly). Every match is 5 overs a
 * side with April 2025 dates; toss winners/decisions vary, and bowlers
 * are drawn from bowler/all-rounder squad members, one over each.
 *
 * The scripts are designed so the league table has an unambiguous top two
 * (KKR 6 pts, MI 4 pts, CSK 2 pts, RCB 0 pts); a runtime guard throws if
 * the standings ever stop matching the pair the Final is scripted for.
 * Safe to re-run: does nothing once the edition has matches.
 */
class Rppl2025FixtureSeeder extends Seeder
{
    private const OVERS_PER_INNINGS = 5;

    public function __construct(
        private readonly GameMatchService $matches,
        private readonly MatchPlayerService $matchPlayers,
        private readonly MatchFlowService $flow,
        private readonly InningsService $innings,
        private readonly DeliveryService $deliveries,
        private readonly MatchResultService $results,
        private readonly StandingsService $standings,
    ) {}

    public function run(): void
    {
        $edition = Edition::where('year', Rppl2025EditionSeeder::YEAR)->firstOrFail();

        if (GameMatch::where('edition_id', $edition->id)->exists()) {
            return;
        }

        $venues = collect(Rppl2026TeamAndVenueSeeder::VENUES)
            ->map(fn (array $venue) => Venue::where('name', $venue['name'])->firstOrFail())
            ->values();

        foreach ($this->fixtures() as $number => $fixture) {
            if ($number === 7) {
                $this->assertFinalistsAreLeagueTopTwo($edition, $fixture);
            }

            $this->playMatch($edition, $venues[$fixture['venue']], $number, $fixture);
        }
    }

    /**
     * Team A/B, toss, date and both innings scripts. 'first' is the script
     * of the side batting first (the toss winner when choosing to bat,
     * otherwise the other side).
     *
     * @return array<int, array<string, mixed>>
     */
    private function fixtures(): array
    {
        return [
            // CSK 47/3, MI chase 48/2 inside the last over: a successful chase.
            1 => [
                'a' => 'CSK', 'b' => 'MI', 'toss' => 'CSK', 'decision' => 'bat', 'venue' => 0, 'at' => [5, 17, 30], 'stage' => 'league',
                'first' => [
                    ...$this->over(1, 4, 0, 1, 6, 1),
                    ...$this->over(0, 1, 'W', 1, 4, 0),
                    ['wide' => true], ...$this->over(2, 1, 0, 6, 1, 1),
                    ...$this->over(0, 1, 1, 'W', 4, 1),
                    ...$this->over(1, 'W', 2, 0, 4, 2),
                ],
                'second' => [
                    ...$this->over(1, 0, 4, 1, 0, 6),
                    ...$this->over(1, 1, 'W', 4, 0, 1),
                    ...$this->over(0, 6, 1, 1, 4, 1),
                    ['no_ball' => true], ...$this->over(2, 1, 'W', 1, 0, 1),
                    ...$this->over(1, 4, 1, 4),
                ],
            ],
            // KKR 52/2 defended: RCB chase falls short.
            2 => [
                'a' => 'RCB', 'b' => 'KKR', 'toss' => 'RCB', 'decision' => 'bowl', 'venue' => 1, 'at' => [6, 17, 30], 'stage' => 'league',
                'first' => [
                    ...$this->over(4, 1, 1, 0, 6, 1),
                    ...$this->over(1, 0, 4, 1, 'W', 1),
                    ['wide' => true], ...$this->over(6, 1, 0, 4, 1, 1),
                    ...$this->over(1, 0, 6, 1, 1, 'W'),
                    ...$this->over(1, 4, 0, 1, 1, 2),
                ],
                'second' => [
                    ...$this->over(0, 1, 0, 4, 1, 'W'),
                    ...$this->over(1, 0, 1, 0, 'W', 1),
                    ...$this->over(4, 0, 1, 'W', 1, 0),
                    ...$this->over(1, 6, 0, 1, 'W', 4),
                    ...$this->over(1, 'W', 4, 1, 6, 'W'),
                ],
            ],
            // Low-scoring: MI 29/5 beat RCB 19/6.
            3 => [
                'a' => 'MI', 'b' => 'RCB', 'toss' => 'MI', 'decision' => 'bat', 'venue' => 0, 'at' => [12, 17, 30], 'stage' => 'league',
                'first' => [
                    ...$this->over(0, 1, 0, 'W', 1, 0),
                    ...$this->over(1, 0, 4, 1, 0, 'W'),
                    ...$this->over(0, 1, 1, 0, 'W', 2),
                    ...$this->over(1, 0, 4, 1, 'W', 1),
                    ...$this->over(1, 6, 1, 'W', 2, 0),
                ],
                'second' => [
                    ...$this->over(0, 1, 'W', 0, 1, 0),
                    ...$this->over(1, 0, 1, 'W', 0, 4),
                    ...$this->over(0, 1, 0, 1, 'W', 0),
                    ...$this->over(1, 0, 'W', 1, 4, 0),
                    ...$this->over(1, 'W', 0, 2, 'W', 0),
                ],
            ],
            // High-scoring: KKR 61/2, CSK reply 49/4.
            4 => [
                'a' => 'KKR', 'b' => 'CSK', 'toss' => 'CSK', 'decision' => 'bowl', 'venue' => 1, 'at' => [13, 17, 30], 'stage' => 'league',
                'first' => [
                    ...$this->over(6, 4, 1, 0, 6, 1),
                    ...$this->over(4, 1, 1, 6, 0, 'W'),
                    ['no_ball' => true], ...$this->over(1, 6, 4, 1, 1, 0),
                    ...$this->over(0, 1, 'W', 6, 1, 1),
                    ...$this->over(1, 0, 4, 1, 2, 0),
                ],
                'second' => [
                    ...$this->over(6, 0, 1, 4, 1, 'W'),
                    ...$this->over(1, 1, 0, 6, 1, 'W'),
                    ['wide' => true], ...$this->over(4, 0, 1, 1, 'W', 2),
                    ...$this->over(1, 6, 0, 1, 4, 1),
                    ...$this->over(1, 0, 'W', 2, 1, 2),
                ],
            ],
            // Close: CSK need 39 and get there off the very last ball.
            5 => [
                'a' => 'CSK', 'b' => 'RCB', 'toss' => 'RCB', 'decision' => 'bat', 'venue' => 0, 'at' => [19, 17, 30], 'stage' => 'league',
                'first' => [
                    ...$this->over(1, 0, 4, 1, 0, 1),
                    ...$this->over(0, 1, 'W', 4, 1, 0),
                    ...$this->over(6, 1, 0, 1, 'W', 1),
                    ...$this->over(1, 0, 1, 'W', 4, 0),
                    ['wide' => true], ...$this->over(1, 0, 6, 1, 'W', 1),
                ],
                'second' => [
                    ...$this->over(0, 4, 1, 1, 0, 1),
                    ...$this->over(1, 0, 'W', 1, 4, 0),
                    ...$this->over(0, 1, 1, 0, 1, 'W'),
                    ['wide' => true], ...$this->over(4, 0, 1, 'W', 1, 1),
                    ...$this->over(6, 1, 'W', 4, 2, 2),
                ],
            ],
            // Comfortable: KKR 58/1 against MI 35/5.
            6 => [
                'a' => 'MI', 'b' => 'KKR', 'toss' => 'MI', 'decision' => 'bowl', 'venue' => 1, 'at' => [20, 17, 30], 'stage' => 'league',
                'first' => [
                    ...$this->over(4, 1, 6, 0, 1, 4),
                    ...$this->over(1, 1, 4, 0, 6, 1),
                    ...$this->over(0, 1, 'W', 6, 4, 1),
                    ...$this->overWithBye(1, 4, 0, 6, 1, 2),
                    ...$this->over(1, 0, 1, 0, 1, 0),
                ],
                'second' => [
                    ...$this->over(1, 0, 0, 4, 1, 'W'),
                    ...$this->over(0, 1, 1, 0, 'W', 1),
                    ...$this->over(4, 0, 1, 1, 'W', 0),
                    ...$this->over(1, 0, 6, 1, 0, 'W'),
                    ...$this->over(1, 4, 'W', 1, 4, 2),
                ],
            ],
            // Final: MI 47/4 defend against KKR 41/6.
            7 => [
                'a' => 'KKR', 'b' => 'MI', 'toss' => 'KKR', 'decision' => 'bowl', 'venue' => 0, 'at' => [27, 16, 0], 'stage' => 'final',
                'first' => [
                    ...$this->over(1, 0, 4, 1, 0, 1),
                    ...$this->over(4, 1, 'W', 1, 6, 0),
                    ['wide' => true], ...$this->over(1, 0, 1, 4, 'W', 1),
                    ...$this->over(6, 1, 0, 1, 1, 'W'),
                    ['no_ball' => true], ...$this->over(4, 1, 1, 0, 'W', 4),
                ],
                'second' => [
                    ...$this->over(1, 0, 4, 0, 1, 1),
                    ...$this->over(0, 1, 'W', 6, 1, 1),
                    ...$this->over(1, 4, 0, 1, 'W', 1),
                    ...$this->over(2, 1, 0, 'W', 6, 1),
                    ...$this->over(2, 'W', 4, 'W', 2, 'W'),
                ],
            ],
        ];
    }

    /**
     * The Final must be between the league's top two, in table order.
     * Only league matches exist when this runs.
     *
     * @param  array<string, mixed>  $final
     */
    private function assertFinalistsAreLeagueTopTwo(Edition $edition, array $final): void
    {
        $rows = $this->standings->getEditionStandings($edition)['standings'];

        if (count($rows) < 3 || $rows[1]['points'] <= $rows[2]['points']) {
            $points = implode(',', array_map(fn ($row) => $row['points'], $rows));

            throw new RuntimeException("RPPL 2025 league table has no unambiguous top two (points: {$points}).");
        }

        $actual = [$rows[0]['edition_team']->team->short_name, $rows[1]['edition_team']->team->short_name];
        $expected = [$final['a'], $final['b']];

        if ($actual !== $expected) {
            throw new RuntimeException('RPPL 2025 Final is scripted as '.implode(' v ', $expected).' but the league top two are '.implode(' and ', $actual).'.');
        }
    }

    /**
     * One 6-legal-ball over: an int is bat runs, 'W' a wicket (bowled).
     *
     * @return list<array<string, mixed>>
     */
    private function over(int|string ...$balls): array
    {
        return array_map(fn ($ball) => $ball === 'W' ? ['wicket' => true] : ['runs' => $ball], $balls);
    }

    /**
     * Like over(), but the last argument is byes taken off the final ball.
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

    private function editionTeam(Edition $edition, string $shortName): EditionTeam
    {
        return EditionTeam::where('edition_id', $edition->id)
            ->whereHas('team', fn ($query) => $query->where('short_name', $shortName))
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function playMatch(Edition $edition, Venue $venue, int $matchNumber, array $fixture): void
    {
        $teamA = $this->editionTeam($edition, $fixture['a']);
        $teamB = $this->editionTeam($edition, $fixture['b']);
        $tossWinner = $fixture['toss'] === $fixture['a'] ? $teamA : $teamB;
        $tossLoser = $tossWinner->is($teamA) ? $teamB : $teamA;
        $battingFirst = $fixture['decision'] === 'bat' ? $tossWinner : $tossLoser;
        $battingSecond = $battingFirst->is($teamA) ? $teamB : $teamA;

        [$day, $hour, $minute] = $fixture['at'];

        $match = $this->matches->createMatch([
            'edition_id' => $edition->id,
            'match_number' => $matchNumber,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'venue_id' => $venue->id,
            'match_stage' => $fixture['stage'],
            'overs_per_innings' => self::OVERS_PER_INNINGS,
            'scheduled_at' => Carbon::create(2025, 4, $day, $hour, $minute, 0, 'Asia/Kolkata')->utc(),
        ]);

        $xi = [
            $teamA->id => $this->buildPlayingXi($match, $teamA),
            $teamB->id => $this->buildPlayingXi($match, $teamB),
        ];

        $this->assert($this->flow->startToss($match->fresh()), 'startToss', $match);
        $this->assert($this->flow->recordToss($match->fresh(), [
            'toss_winner_team_id' => $tossWinner->id,
            'toss_decision' => $fixture['decision'],
        ]), 'recordToss', $match);
        $this->assert($this->flow->startMatch($match->fresh()), 'startMatch', $match);

        $battingXi1 = $xi[$battingFirst->id];
        $bowlingXi1 = $this->bowlingOrder($xi[$battingSecond->id]);
        $this->assert($this->innings->startFirstInnings($match->fresh()), 'startFirstInnings', $match);
        $first = Innings::where('match_id', $match->id)->where('innings_number', 1)->firstOrFail();
        $this->assert($this->innings->setUpOpeningState($match->fresh(), $first, $battingXi1[0]->id, $battingXi1[1]->id, $bowlingXi1[0]->id), 'setUpOpeningState(1)', $match);
        $this->playScriptedInnings($match->fresh(), $first->fresh(), $battingXi1, $bowlingXi1, $fixture['first']);

        $battingXi2 = $xi[$battingSecond->id];
        $bowlingXi2 = $this->bowlingOrder($xi[$battingFirst->id]);
        $this->assert($this->innings->canStartSecondInnings($match->fresh()), 'canStartSecondInnings', $match);
        $this->assert($this->innings->startSecondInnings($match->fresh()), 'startSecondInnings', $match);
        $second = Innings::where('match_id', $match->id)->where('innings_number', 2)->firstOrFail();
        $this->assert($this->innings->setUpOpeningState($match->fresh(), $second, $battingXi2[0]->id, $battingXi2[1]->id, $bowlingXi2[0]->id), 'setUpOpeningState(2)', $match);
        $this->playScriptedInnings($match->fresh(), $second->fresh(), $battingXi2, $bowlingXi2, $fixture['second']);

        $this->assert($this->results->finalizeMatch($match->fresh()), 'finalizeMatch', $match);
    }

    /**
     * The first 11 squad members by jersey are the Playing XI; the first
     * is captain and the XI's wicket-keeper (or else the first) keeps.
     *
     * @return Collection<int, MatchPlayer>
     */
    private function buildPlayingXi(GameMatch $match, EditionTeam $editionTeam): Collection
    {
        $teamPlayers = TeamPlayer::where('edition_team_id', $editionTeam->id)->orderBy('jersey_number')->limit(11)->get();
        $matchPlayers = $teamPlayers->map(fn (TeamPlayer $teamPlayer) => $this->matchPlayers->addPlayer($match, $teamPlayer));

        $keeper = $matchPlayers->first(fn (MatchPlayer $mp) => $mp->teamPlayer->role === 'wicket_keeper') ?? $matchPlayers->first();

        $this->matchPlayers->setCaptain($matchPlayers->first());
        $this->matchPlayers->setWicketKeeper($keeper);

        return $matchPlayers->values();
    }

    /**
     * Bowling rotation: bowlers and all-rounders first (jersey order),
     * everyone else after, so top-order batters only bowl if the XI has
     * fewer than five real bowling options.
     *
     * @param  Collection<int, MatchPlayer>  $xi
     * @return Collection<int, MatchPlayer>
     */
    private function bowlingOrder(Collection $xi): Collection
    {
        $isBowler = fn (MatchPlayer $mp) => in_array($mp->teamPlayer->role, ['bowler', 'all_rounder'], true);

        return $xi->filter($isBowler)->merge($xi->reject($isBowler))->values();
    }

    /**
     * Plays a ball script through the real DeliveryService, taking
     * striker/non-striker from expectedBattingState() and rotating one
     * over per bowler (index 0 bowled over 1 via setUpOpeningState()).
     * Stops early when the innings ends by itself (all out, overs done,
     * chase target reached).
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
            throw new RuntimeException("RPPL 2025 fixture seeding failed at step [{$step}] for match #{$match->id}.");
        }
    }
}
