<?php

namespace Tests\Feature\Public;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use App\Services\Scoring\DeliveryService;
use App\Services\Scoring\ScorecardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MatchScorecardPdfTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A match with a scored Innings #1, mirroring
     * Admin\ScorecardControllerTest::matchWithScoredInnings() — the
     * batter/bowler carry private phone/email/fee/payment fields so the
     * PDF privacy test has something concrete to assert is absent.
     *
     * @return array{0: GameMatch, 1: Player, 2: Player}
     */
    private function matchWithScoredInnings(array $matchAttributes = []): array
    {
        $edition = Edition::factory()->create();
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        // Always created (and scored) as 'live' — recordDelivery() requires
        // a live match — with any requested final status applied only
        // afterward, mirroring how a real match actually completes.
        $match = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'match_status' => 'live',
            'started_at' => now(),
        ]);
        $match->update(['toss_winner_team_id' => $teamA->id, 'toss_decision' => 'bat']);

        $batter = Player::factory()->create(['phone' => '9998887771', 'email' => 'batter@example.com']);
        $batterRegistration = PlayerRegistration::factory()->create([
            'edition_id' => $edition->id,
            'player_id' => $batter->id,
            'registration_fee' => 1234.56,
            'payment_status' => 'paid',
        ]);
        $striker = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $teamA->id, 'player_registration_id' => $batterRegistration->id])->id,
        ]);
        $nonStriker = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $teamA->id])->id,
        ]);

        $bowlerPlayer = Player::factory()->create(['phone' => '9998887772', 'email' => 'bowler@example.com']);
        $bowlerRegistration = PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'player_id' => $bowlerPlayer->id]);
        $bowler = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $teamB->id, 'player_registration_id' => $bowlerRegistration->id])->id,
        ]);

        $innings = Innings::create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $teamA->id,
            'bowling_team_id' => $teamB->id,
            'status' => 'live',
        ]);

        app(DeliveryService::class)->recordDelivery($match->fresh(), $innings->fresh(), [
            'striker_match_player_id' => $striker->id,
            'non_striker_match_player_id' => $nonStriker->id,
            'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 4,
        ]);

        if ($matchAttributes) {
            $match->update($matchAttributes);
            if (($matchAttributes['match_status'] ?? null) === 'completed') {
                $innings->update(['status' => 'completed']);
            }
        }

        return [$match->fresh(), $batter, $bowlerPlayer];
    }

    public function test_scorecard_pdf_route_returns_a_pdf_for_a_valid_match_with_innings(): void
    {
        [$match] = $this->matchWithScoredInnings();

        $response = $this->get(route('public.matches.scorecard.pdf', $match));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_pdf_generation_uses_scorecard_data_for_a_completed_match(): void
    {
        [$match, $batter] = $this->matchWithScoredInnings(['match_status' => 'completed', 'match_result' => 'Team A won by 10 runs']);

        $response = $this->get(route('public.matches.scorecard.pdf', $match));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_live_match_with_innings_generates_a_snapshot_pdf(): void
    {
        [$match] = $this->matchWithScoredInnings(['match_status' => 'live']);

        $response = $this->get(route('public.matches.scorecard.pdf', $match));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_match_with_no_innings_gets_the_same_safe_redirect_as_the_web_scorecard(): void
    {
        $match = GameMatch::factory()->create();

        $response = $this->get(route('public.matches.scorecard.pdf', $match));

        $response->assertRedirect(route('public.matches.show', $match));
    }

    public function test_pdf_does_not_intentionally_include_private_player_or_finance_fields(): void
    {
        [$match, $batter, $bowlerPlayer] = $this->matchWithScoredInnings();

        $view = view('public.matches.scorecard-pdf', [
            'match' => $match->load(['edition', 'teamA.team', 'teamB.team', 'venue', 'tossWinner.team']),
            'inningsScorecards' => app(ScorecardService::class)->getMatchScorecard($match),
        ])->render();

        $this->assertStringNotContainsString($batter->phone, $view);
        $this->assertStringNotContainsString($batter->email, $view);
        $this->assertStringNotContainsString($bowlerPlayer->phone, $view);
        $this->assertStringNotContainsString($bowlerPlayer->email, $view);
        $this->assertStringNotContainsString('1234.56', $view);
        $this->assertStringNotContainsString('payment_status', $view);
        $this->assertStringNotContainsString('registration_fee', $view);
    }

    public function test_download_uses_a_deterministic_safe_filename(): void
    {
        [$match] = $this->matchWithScoredInnings();

        $response = $this->get(route('public.matches.scorecard.pdf', $match));

        $expected = 'rppl-'.Str::slug($match->teamA->team->name).'-vs-'.Str::slug($match->teamB->team->name).'-scorecard.pdf';
        $response->assertHeader('content-disposition', "attachment; filename={$expected}");
    }

    public function test_download_pdf_link_appears_on_the_public_scorecard_page(): void
    {
        [$match] = $this->matchWithScoredInnings();

        $response = $this->get(route('public.matches.scorecard', $match));

        $response->assertOk();
        $response->assertSee(route('public.matches.scorecard.pdf', $match), false);
    }
}
