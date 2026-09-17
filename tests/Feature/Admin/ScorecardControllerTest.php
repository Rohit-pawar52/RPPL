<?php

namespace Tests\Feature\Admin;

use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\Scoring\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScorecardControllerTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $scorerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->scorerRole = Role::create(['name' => 'Scorer', 'slug' => 'scorer']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    private function scorer(): User
    {
        return User::factory()->create(['role_id' => $this->scorerRole->id]);
    }

    /**
     * A live match with a live Innings #1 containing one scored
     * delivery, ready to render a non-empty scorecard.
     */
    private function matchWithScoredInnings(): array
    {
        $match = GameMatch::factory()->create(['match_status' => 'live', 'started_at' => now()]);
        $match->update(['toss_winner_team_id' => $match->edition_team_a_id, 'toss_decision' => 'bat']);

        $striker = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_a_id])->id,
        ]);
        $nonStriker = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_a_id])->id,
        ]);
        $bowler = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_b_id])->id,
        ]);

        $innings = Innings::create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $match->edition_team_a_id,
            'bowling_team_id' => $match->edition_team_b_id,
            'status' => 'live',
        ]);

        app(DeliveryService::class)->recordDelivery($match->fresh(), $innings->fresh(), [
            'striker_match_player_id' => $striker->id,
            'non_striker_match_player_id' => $nonStriker->id,
            'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 4,
        ]);

        return [$match->fresh(), $striker];
    }

    public function test_guest_cannot_access_scorecard(): void
    {
        [$match] = $this->matchWithScoredInnings();

        $this->get(route('admin.matches.scorecard', $match))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_scorecard(): void
    {
        [$match, $striker] = $this->matchWithScoredInnings();

        $response = $this->actingAs($this->admin())->get(route('admin.matches.scorecard', $match));

        $response->assertOk();
        $response->assertSee($striker->teamPlayer->playerRegistration->player->name);
    }

    public function test_scorer_can_view_scorecard(): void
    {
        [$match] = $this->matchWithScoredInnings();

        $this->actingAs($this->scorer())
            ->get(route('admin.matches.scorecard', $match))
            ->assertOk();
    }

    public function test_scorecard_renders_for_match_with_no_innings_yet(): void
    {
        $match = GameMatch::factory()->create();

        $response = $this->actingAs($this->admin())->get(route('admin.matches.scorecard', $match));

        $response->assertOk();
        $response->assertSee('No innings started yet.');
    }

    public function test_view_scorecard_link_appears_on_match_show_page_once_innings_exist(): void
    {
        [$match] = $this->matchWithScoredInnings();

        $response = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));

        $response->assertOk();
        $response->assertSee(route('admin.matches.scorecard', $match), false);
    }

    public function test_view_scorecard_link_hidden_before_any_innings(): void
    {
        $match = GameMatch::factory()->create();

        $response = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));

        $response->assertOk();
        $response->assertDontSee(route('admin.matches.scorecard', $match), false);
    }
}
