<?php

namespace Tests\Feature\Admin;

use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\MatchPlayer\MatchPlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InningsManagementTest extends TestCase
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
     * A GameMatch with both participating teams having at least one
     * selected MatchPlayer, in the given status (default 'live') with a
     * recorded toss (default: Team A won, chose to bat).
     */
    private function matchWithSquadsAndToss(array $matchAttributes = []): GameMatch
    {
        $match = GameMatch::factory()->create(array_merge([
            'match_status' => 'live',
            'started_at' => now(),
        ], $matchAttributes));

        // Unless the caller explicitly passed a toss_winner_team_id
        // (even null, e.g. to set it up manually afterward), default to
        // a recorded toss: Team A won and chose to bat.
        if (! array_key_exists('toss_winner_team_id', $matchAttributes)) {
            $match->update(['toss_winner_team_id' => $match->edition_team_a_id, 'toss_decision' => 'bat']);
        }

        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_a_id])->id,
        ]);
        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_b_id])->id,
        ]);

        return $match->fresh();
    }

    /**
     * legal_balls is deliberately non-zero here (not the migration's
     * default of 0): InningsService::canCompleteInnings() requires at
     * least one legal delivery before an innings may be manually
     * completed (see its own docblock — this floor prevents an admin
     * from completing a 0/0, zero-ball innings). These are lifecycle/
     * authorization tests, not scoring-correctness tests, so setting
     * legal_balls directly is enough — no full DeliveryService call is
     * needed to represent "at least one ball has been bowled."
     */
    private function matchWithFirstInningsLive(): GameMatch
    {
        $match = $this->matchWithSquadsAndToss();

        Innings::create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $match->edition_team_a_id,
            'bowling_team_id' => $match->edition_team_b_id,
            'status' => 'live',
            'legal_balls' => 1,
        ]);

        return $match->fresh();
    }

    private function matchWithFirstInningsCompleted(): GameMatch
    {
        $match = $this->matchWithFirstInningsLive();
        $match->firstInnings->update(['status' => 'completed']);

        return $match->fresh();
    }

    // ----- Authorization -----

    public function test_guest_is_blocked(): void
    {
        $match = $this->matchWithSquadsAndToss();

        $this->post(route('admin.matches.innings.first.start', $match))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_manage_innings(): void
    {
        $match = $this->matchWithSquadsAndToss();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.first.start', $match))
            ->assertRedirect(route('admin.matches.show', $match));

        $this->assertNotNull($match->fresh()->firstInnings);
    }

    public function test_scorer_can_manage_innings(): void
    {
        $match = $this->matchWithSquadsAndToss();

        $this->actingAs($this->scorer())
            ->post(route('admin.matches.innings.first.start', $match))
            ->assertRedirect(route('admin.matches.show', $match));

        $this->assertNotNull($match->fresh()->firstInnings);
    }

    public function test_scorer_still_cannot_edit_fixture_through_game_match_crud(): void
    {
        $match = GameMatch::factory()->create();

        $this->actingAs($this->scorer())
            ->put(route('admin.matches.update', $match), [
                'edition_id' => $match->edition_id,
                'edition_team_a_id' => $match->edition_team_a_id,
                'edition_team_b_id' => $match->edition_team_b_id,
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->assertForbidden();
    }

    // ----- First innings -----

    public function test_cannot_start_first_innings_while_scheduled(): void
    {
        $match = $this->matchWithSquadsAndToss(['match_status' => 'scheduled', 'started_at' => null]);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.first.start', $match))
            ->assertSessionHas('error');

        $this->assertNull($match->fresh()->firstInnings);
    }

    public function test_cannot_start_first_innings_while_toss(): void
    {
        $match = $this->matchWithSquadsAndToss(['match_status' => 'toss', 'started_at' => null]);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.first.start', $match))
            ->assertSessionHas('error');

        $this->assertNull($match->fresh()->firstInnings);
    }

    public function test_live_match_with_valid_toss_can_start_first_innings(): void
    {
        $match = $this->matchWithSquadsAndToss();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.first.start', $match))
            ->assertRedirect(route('admin.matches.show', $match));

        $this->assertNotNull($match->fresh()->firstInnings);
    }

    public function test_toss_winner_choosing_bat_derives_correct_teams(): void
    {
        $match = $this->matchWithSquadsAndToss([
            'toss_winner_team_id' => null,
            'toss_decision' => null,
        ]);
        $match->update(['toss_winner_team_id' => $match->edition_team_a_id, 'toss_decision' => 'bat']);

        $this->actingAs($this->admin())->post(route('admin.matches.innings.first.start', $match));

        $innings = $match->fresh()->firstInnings;
        $this->assertSame($match->edition_team_a_id, $innings->batting_team_id);
        $this->assertSame($match->edition_team_b_id, $innings->bowling_team_id);
    }

    public function test_toss_winner_choosing_bowl_derives_correct_teams(): void
    {
        $match = $this->matchWithSquadsAndToss([
            'toss_winner_team_id' => null,
            'toss_decision' => null,
        ]);
        $match->update(['toss_winner_team_id' => $match->edition_team_a_id, 'toss_decision' => 'bowl']);

        $this->actingAs($this->admin())->post(route('admin.matches.innings.first.start', $match));

        $innings = $match->fresh()->firstInnings;
        $this->assertSame($match->edition_team_b_id, $innings->batting_team_id);
        $this->assertSame($match->edition_team_a_id, $innings->bowling_team_id);
    }

    public function test_first_innings_number_is_one_and_starts_live_with_zero_totals(): void
    {
        $match = $this->matchWithSquadsAndToss();

        $this->actingAs($this->admin())->post(route('admin.matches.innings.first.start', $match));

        $innings = $match->fresh()->firstInnings;
        $this->assertSame(1, $innings->innings_number);
        $this->assertSame('live', $innings->status);
        $this->assertSame(0, $innings->legal_balls);
        $this->assertSame(0, $innings->total_runs);
        $this->assertSame(0, $innings->total_wickets);
        $this->assertSame(0, $innings->extras);
    }

    public function test_duplicate_first_innings_blocked(): void
    {
        $match = $this->matchWithFirstInningsLive();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.first.start', $match))
            ->assertSessionHas('error');

        $this->assertSame(1, Innings::where('match_id', $match->id)->count());
    }

    public function test_missing_playing_xi_side_blocks_first_innings(): void
    {
        $match = $this->matchWithSquadsAndToss();
        MatchPlayer::where('match_id', $match->id)
            ->whereHas('teamPlayer', fn ($query) => $query->where('edition_team_id', $match->edition_team_b_id))
            ->delete();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.first.start', $match))
            ->assertSessionHas('error');

        $this->assertNull($match->fresh()->firstInnings);
    }

    public function test_completed_match_cannot_start_first_innings(): void
    {
        $match = $this->matchWithSquadsAndToss(['match_status' => 'completed']);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.first.start', $match))
            ->assertSessionHas('error');

        $this->assertNull($match->fresh()->firstInnings);
    }

    public function test_abandoned_match_cannot_start_first_innings(): void
    {
        $match = $this->matchWithSquadsAndToss(['match_status' => 'abandoned']);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.first.start', $match))
            ->assertSessionHas('error');

        $this->assertNull($match->fresh()->firstInnings);
    }

    public function test_cancelled_match_cannot_start_first_innings(): void
    {
        $match = $this->matchWithSquadsAndToss(['match_status' => 'cancelled']);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.first.start', $match))
            ->assertSessionHas('error');

        $this->assertNull($match->fresh()->firstInnings);
    }

    // ----- Completion -----

    public function test_live_innings_can_be_completed(): void
    {
        $match = $this->matchWithFirstInningsLive();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.complete', [$match, $match->firstInnings]))
            ->assertRedirect(route('admin.matches.show', $match));

        $this->assertSame('completed', $match->firstInnings->fresh()->status);
    }

    public function test_completed_innings_cannot_be_completed_again(): void
    {
        $match = $this->matchWithFirstInningsCompleted();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.complete', [$match, $match->firstInnings]))
            ->assertSessionHas('error');

        $this->assertSame('completed', $match->firstInnings->fresh()->status);
    }

    public function test_innings_belonging_to_another_match_cannot_be_completed_through_this_match_route(): void
    {
        $matchA = $this->matchWithFirstInningsLive();
        $matchB = $this->matchWithFirstInningsLive();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.complete', [$matchA, $matchB->firstInnings]))
            ->assertNotFound();

        $this->assertSame('live', $matchB->firstInnings->fresh()->status);
    }

    /**
     * Pre-UAT audit fix: an innings with zero legal deliveries recorded
     * must not be manually completable — otherwise an admin could
     * produce an impossible 0/0, zero-ball "completed" innings (and, if
     * done to both innings, a nonsensical completed/tied match with no
     * scoring at all). See InningsService::canCompleteInnings().
     */
    public function test_innings_with_zero_legal_balls_cannot_be_completed(): void
    {
        $match = $this->matchWithSquadsAndToss();
        Innings::create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $match->edition_team_a_id,
            'bowling_team_id' => $match->edition_team_b_id,
            'status' => 'live',
            'legal_balls' => 0,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.complete', [$match, $match->firstInnings]))
            ->assertSessionHas('error');

        $this->assertSame('live', $match->firstInnings->fresh()->status);
    }

    public function test_completion_does_not_modify_match_result_or_status(): void
    {
        $match = $this->matchWithFirstInningsLive();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.complete', [$match, $match->firstInnings]));

        $fresh = $match->fresh();
        $this->assertSame('live', $fresh->match_status);
        $this->assertNull($fresh->winner_team_id);
        $this->assertNull($fresh->match_result);
        $this->assertNull($fresh->completed_at);
    }

    // ----- Second innings -----

    public function test_cannot_start_second_innings_before_first_innings_exists(): void
    {
        $match = $this->matchWithSquadsAndToss();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.second.start', $match))
            ->assertSessionHas('error');

        $this->assertNull($match->fresh()->secondInnings);
    }

    public function test_cannot_start_second_innings_while_first_innings_live(): void
    {
        $match = $this->matchWithFirstInningsLive();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.second.start', $match))
            ->assertSessionHas('error');

        $this->assertNull($match->fresh()->secondInnings);
    }

    public function test_can_start_second_innings_after_first_innings_completed(): void
    {
        $match = $this->matchWithFirstInningsCompleted();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.second.start', $match))
            ->assertRedirect(route('admin.matches.show', $match));

        $this->assertNotNull($match->fresh()->secondInnings);
    }

    public function test_second_innings_teams_reverse_from_first_innings(): void
    {
        $match = $this->matchWithFirstInningsCompleted();
        $firstInnings = $match->firstInnings;

        $this->actingAs($this->admin())->post(route('admin.matches.innings.second.start', $match));

        $secondInnings = $match->fresh()->secondInnings;
        $this->assertSame($firstInnings->bowling_team_id, $secondInnings->batting_team_id);
        $this->assertSame($firstInnings->batting_team_id, $secondInnings->bowling_team_id);
    }

    public function test_second_innings_number_is_two_and_starts_live_with_zero_totals(): void
    {
        $match = $this->matchWithFirstInningsCompleted();

        $this->actingAs($this->admin())->post(route('admin.matches.innings.second.start', $match));

        $secondInnings = $match->fresh()->secondInnings;
        $this->assertSame(2, $secondInnings->innings_number);
        $this->assertSame('live', $secondInnings->status);
        $this->assertSame(0, $secondInnings->total_runs);
        $this->assertSame(0, $secondInnings->total_wickets);
    }

    public function test_duplicate_second_innings_blocked(): void
    {
        $match = $this->matchWithFirstInningsCompleted();
        $this->actingAs($this->admin())->post(route('admin.matches.innings.second.start', $match));

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.second.start', $match))
            ->assertSessionHas('error');

        $this->assertSame(2, Innings::where('match_id', $match->id)->count());
    }

    public function test_no_third_innings_path_exists(): void
    {
        $match = $this->matchWithFirstInningsCompleted();
        $this->actingAs($this->admin())->post(route('admin.matches.innings.second.start', $match));
        $match->fresh()->secondInnings->update(['status' => 'completed']);

        // Attempting to start a "second innings" again after both are
        // completed must still be rejected — no third-innings path.
        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.second.start', $match))
            ->assertSessionHas('error');

        $this->assertSame(2, Innings::where('match_id', $match->id)->count());
    }

    // ----- History / no delete/edit -----

    public function test_no_delete_route_exists_for_innings(): void
    {
        $match = $this->matchWithFirstInningsLive();

        $this->actingAs($this->admin())
            ->delete("/admin/matches/{$match->id}/innings/{$match->firstInnings->id}")
            ->assertStatus(404);
    }

    public function test_playing_xi_remains_locked_while_innings_in_progress(): void
    {
        $match = $this->matchWithFirstInningsLive();

        $this->assertFalse(app(MatchPlayerService::class)->canModifyPlayingXI($match));
    }

    // ----- UI -----

    public function test_before_innings_shows_start_first_innings_when_eligible(): void
    {
        $match = $this->matchWithSquadsAndToss();

        $response = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));

        $response->assertOk();
        $response->assertSee('Start First Innings');
    }

    public function test_first_innings_live_shows_complete_innings(): void
    {
        $match = $this->matchWithFirstInningsLive();

        $response = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));

        $response->assertOk();
        $response->assertSee('Complete Innings');
    }

    public function test_first_innings_completed_shows_start_second_innings(): void
    {
        $match = $this->matchWithFirstInningsCompleted();

        $response = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));

        $response->assertOk();
        $response->assertSee('Start Second Innings');
    }

    public function test_second_innings_live_shows_complete_innings(): void
    {
        $match = $this->matchWithFirstInningsCompleted();
        $this->actingAs($this->admin())->post(route('admin.matches.innings.second.start', $match));

        $response = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));

        $response->assertOk();
        $response->assertSee('Complete Innings');
    }

    /**
     * As of Phase 3.15, once both innings are completed the page shows
     * a derived result preview + "Finalize Match" (here, both innings
     * sit at their default 0/0 totals, which is a legitimate — if
     * trivial — tie) rather than the old static "Match result pending."
     * placeholder. This test predates that feature; only the "no more
     * Start Second Innings" part of its original intent still applies
     * as written.
     */
    public function test_both_innings_completed_shows_result_preview_instead_of_start_second_innings(): void
    {
        $match = $this->matchWithFirstInningsCompleted();
        $this->actingAs($this->admin())->post(route('admin.matches.innings.second.start', $match));
        $match->fresh()->secondInnings->update(['status' => 'completed']);

        $response = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));

        $response->assertOk();
        $response->assertSee('Expected result:');
        $response->assertSee('Finalize Match');
        $response->assertDontSee('Start Second Innings');
    }

    public function test_scorer_sees_innings_workflow_controls(): void
    {
        $match = $this->matchWithSquadsAndToss();

        $response = $this->actingAs($this->scorer())->get(route('admin.matches.show', $match));

        $response->assertOk();
        $response->assertSee('Start First Innings');
    }
}
