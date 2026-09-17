<?php

namespace Tests\Feature\Admin;

use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\MatchPlayer\MatchPlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchFlowTest extends TestCase
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
     * A GameMatch with its two participating EditionTeams (as
     * GameMatchFactory builds them by default), each with at least one
     * selected MatchPlayer — i.e. ready to have its toss started.
     */
    private function matchReadyForToss(array $matchAttributes = []): GameMatch
    {
        $match = GameMatch::factory()->create(array_merge(['match_status' => 'scheduled'], $matchAttributes));

        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_a_id])->id,
        ]);
        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_b_id])->id,
        ]);

        return $match;
    }

    /**
     * A match already in the 'toss' phase, both sides selected, with no
     * toss recorded yet.
     */
    private function matchInTossPhase(): GameMatch
    {
        return $this->matchReadyForToss(['match_status' => 'toss']);
    }

    /**
     * A match in the 'toss' phase with a valid toss already recorded —
     * i.e. ready for Start Match.
     */
    private function matchReadyToStart(): GameMatch
    {
        $match = $this->matchInTossPhase();
        $match->update([
            'toss_winner_team_id' => $match->edition_team_a_id,
            'toss_decision' => 'bat',
        ]);

        return $match->fresh();
    }

    // ----- Authorization -----

    public function test_guest_cannot_perform_flow_actions(): void
    {
        $match = $this->matchReadyForToss();

        $this->post(route('admin.matches.start-toss', $match))->assertRedirect(route('admin.login'));
        $this->put(route('admin.matches.toss.update', $match), [])->assertRedirect(route('admin.login'));
        $this->post(route('admin.matches.start', $match))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_perform_flow_actions(): void
    {
        $match = $this->matchReadyForToss();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.start-toss', $match))
            ->assertRedirect(route('admin.matches.show', $match));

        $this->assertSame('toss', $match->fresh()->match_status);
    }

    public function test_scorer_can_perform_flow_actions(): void
    {
        $match = $this->matchReadyForToss();

        $this->actingAs($this->scorer())
            ->post(route('admin.matches.start-toss', $match))
            ->assertRedirect(route('admin.matches.show', $match));

        $this->assertSame('toss', $match->fresh()->match_status);
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

    // ----- Start Toss -----

    public function test_scheduled_match_with_players_on_both_teams_can_enter_toss(): void
    {
        $match = $this->matchReadyForToss();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.start-toss', $match))
            ->assertRedirect(route('admin.matches.show', $match));

        $match->refresh();
        $this->assertSame('toss', $match->match_status);
        $this->assertNull($match->toss_winner_team_id);
        $this->assertNull($match->toss_decision);
    }

    public function test_missing_team_a_selection_blocks_start_toss(): void
    {
        $match = GameMatch::factory()->create(['match_status' => 'scheduled']);
        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_b_id])->id,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.start-toss', $match))
            ->assertRedirect(route('admin.matches.show', $match))
            ->assertSessionHas('error');

        $this->assertSame('scheduled', $match->fresh()->match_status);
    }

    public function test_missing_team_b_selection_blocks_start_toss(): void
    {
        $match = GameMatch::factory()->create(['match_status' => 'scheduled']);
        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_a_id])->id,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.start-toss', $match))
            ->assertSessionHas('error');

        $this->assertSame('scheduled', $match->fresh()->match_status);
    }

    public function test_non_scheduled_match_cannot_start_toss(): void
    {
        $match = $this->matchReadyForToss(['match_status' => 'live']);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.start-toss', $match))
            ->assertSessionHas('error');

        $this->assertSame('live', $match->fresh()->match_status);
    }

    public function test_innings_existence_blocks_start_toss(): void
    {
        $match = $this->matchReadyForToss();
        Innings::factory()->create([
            'match_id' => $match->id,
            'batting_team_id' => $match->edition_team_a_id,
            'bowling_team_id' => $match->edition_team_b_id,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.start-toss', $match))
            ->assertSessionHas('error');

        $this->assertSame('scheduled', $match->fresh()->match_status);
    }

    // ----- Record Toss -----

    public function test_valid_team_a_winner_accepted(): void
    {
        $match = $this->matchInTossPhase();

        $this->actingAs($this->admin())
            ->put(route('admin.matches.toss.update', $match), [
                'toss_winner_team_id' => $match->edition_team_a_id,
                'toss_decision' => 'bat',
            ])
            ->assertRedirect(route('admin.matches.show', $match));

        $match->refresh();
        $this->assertSame($match->edition_team_a_id, $match->toss_winner_team_id);
        $this->assertSame('bat', $match->toss_decision);
    }

    public function test_valid_team_b_winner_accepted(): void
    {
        $match = $this->matchInTossPhase();

        $this->actingAs($this->admin())
            ->put(route('admin.matches.toss.update', $match), [
                'toss_winner_team_id' => $match->edition_team_b_id,
                'toss_decision' => 'bowl',
            ])
            ->assertRedirect(route('admin.matches.show', $match));

        $match->refresh();
        $this->assertSame($match->edition_team_b_id, $match->toss_winner_team_id);
        $this->assertSame('bowl', $match->toss_decision);
    }

    public function test_unrelated_edition_team_rejected_as_toss_winner(): void
    {
        $match = $this->matchInTossPhase();
        $unrelatedEditionTeam = EditionTeam::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.matches.toss.update', $match), [
                'toss_winner_team_id' => $unrelatedEditionTeam->id,
                'toss_decision' => 'bat',
            ])
            ->assertSessionHasErrors('toss_winner_team_id');

        $this->assertNull($match->fresh()->toss_winner_team_id);
    }

    public function test_invalid_decision_rejected(): void
    {
        $match = $this->matchInTossPhase();

        $this->actingAs($this->admin())
            ->put(route('admin.matches.toss.update', $match), [
                'toss_winner_team_id' => $match->edition_team_a_id,
                'toss_decision' => 'field',
            ])
            ->assertSessionHasErrors('toss_decision');

        $this->assertNull($match->fresh()->toss_decision);
    }

    public function test_toss_can_be_corrected_while_still_in_toss_state(): void
    {
        $match = $this->matchReadyToStart(); // team A / bat

        $this->actingAs($this->admin())
            ->put(route('admin.matches.toss.update', $match), [
                'toss_winner_team_id' => $match->edition_team_b_id,
                'toss_decision' => 'bowl',
            ])
            ->assertRedirect(route('admin.matches.show', $match));

        $match->refresh();
        $this->assertSame($match->edition_team_b_id, $match->toss_winner_team_id);
        $this->assertSame('bowl', $match->toss_decision);
    }

    public function test_toss_cannot_be_changed_once_live(): void
    {
        $match = $this->matchReadyToStart();
        $match->update(['match_status' => 'live', 'started_at' => now()]);
        $originalWinner = $match->toss_winner_team_id;

        $this->actingAs($this->admin())
            ->put(route('admin.matches.toss.update', $match), [
                'toss_winner_team_id' => $match->edition_team_b_id,
                'toss_decision' => 'bowl',
            ])
            ->assertSessionHas('error');

        $this->assertSame($originalWinner, $match->fresh()->toss_winner_team_id);
    }

    public function test_innings_existence_blocks_toss_correction(): void
    {
        $match = $this->matchReadyToStart();
        Innings::factory()->create([
            'match_id' => $match->id,
            'batting_team_id' => $match->edition_team_a_id,
            'bowling_team_id' => $match->edition_team_b_id,
        ]);
        $originalDecision = $match->toss_decision;

        $this->actingAs($this->admin())
            ->put(route('admin.matches.toss.update', $match), [
                'toss_winner_team_id' => $match->edition_team_b_id,
                'toss_decision' => 'bowl',
            ])
            ->assertSessionHas('error');

        $this->assertSame($originalDecision, $match->fresh()->toss_decision);
    }

    // ----- Start Match -----

    public function test_cannot_start_before_toss_phase(): void
    {
        $match = $this->matchReadyForToss(); // still 'scheduled'

        $this->actingAs($this->admin())
            ->post(route('admin.matches.start', $match))
            ->assertSessionHas('error');

        $this->assertSame('scheduled', $match->fresh()->match_status);
    }

    public function test_cannot_start_without_toss_winner(): void
    {
        $match = $this->matchInTossPhase();
        $match->update(['toss_decision' => 'bat']);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.start', $match))
            ->assertSessionHas('error');

        $this->assertSame('toss', $match->fresh()->match_status);
    }

    public function test_cannot_start_without_toss_decision(): void
    {
        $match = $this->matchInTossPhase();
        $match->update(['toss_winner_team_id' => $match->edition_team_a_id]);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.start', $match))
            ->assertSessionHas('error');

        $this->assertSame('toss', $match->fresh()->match_status);
    }

    public function test_cannot_start_if_either_side_has_no_selected_players(): void
    {
        $match = $this->matchInTossPhase();
        $match->update(['toss_winner_team_id' => $match->edition_team_a_id, 'toss_decision' => 'bat']);
        // Remove Team B's only selected player.
        MatchPlayer::query()
            ->where('match_id', $match->id)
            ->whereHas('teamPlayer', fn ($query) => $query->where('edition_team_id', $match->edition_team_b_id))
            ->delete();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.start', $match))
            ->assertSessionHas('error');

        $this->assertSame('toss', $match->fresh()->match_status);
    }

    public function test_valid_start_sets_status_live_and_started_at(): void
    {
        $match = $this->matchReadyToStart();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.start', $match))
            ->assertRedirect(route('admin.matches.show', $match));

        $match->refresh();
        $this->assertSame('live', $match->match_status);
        $this->assertNotNull($match->started_at);
    }

    public function test_existing_innings_blocks_start(): void
    {
        $match = $this->matchReadyToStart();
        Innings::factory()->create([
            'match_id' => $match->id,
            'batting_team_id' => $match->edition_team_a_id,
            'bowling_team_id' => $match->edition_team_b_id,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.start', $match))
            ->assertSessionHas('error');

        $this->assertSame('toss', $match->fresh()->match_status);
    }

    public function test_repeated_start_attempt_fails_safely(): void
    {
        $match = $this->matchReadyToStart();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.matches.start', $match))->assertRedirect();
        $startedAt = $match->fresh()->started_at;

        $this->actingAs($admin)
            ->post(route('admin.matches.start', $match))
            ->assertSessionHas('error');

        $this->assertSame('live', $match->fresh()->match_status);
        $this->assertEquals($startedAt, $match->fresh()->started_at);
    }

    // ----- Playing XI integration -----

    public function test_starting_toss_still_allows_playing_xi_changes(): void
    {
        $match = $this->matchInTossPhase();

        $this->assertTrue(app(MatchPlayerService::class)->canModifyPlayingXI($match));
    }

    public function test_starting_match_locks_playing_xi(): void
    {
        $match = $this->matchReadyToStart();

        $this->actingAs($this->admin())->post(route('admin.matches.start', $match));

        $this->assertFalse(app(MatchPlayerService::class)->canModifyPlayingXI($match->fresh()));
    }

    // ----- UI -----

    public function test_scheduled_match_shows_start_toss_when_eligible(): void
    {
        $match = $this->matchReadyForToss();

        $response = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));

        $response->assertOk();
        $response->assertSee('Start Toss');
    }

    public function test_toss_state_shows_toss_form(): void
    {
        $match = $this->matchInTossPhase();

        $response = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));

        $response->assertOk();
        $response->assertSee('Save Toss');
    }

    public function test_live_state_shows_toss_information_but_no_edit_controls(): void
    {
        $match = $this->matchReadyToStart();
        $match->update(['match_status' => 'live', 'started_at' => now()]);

        $response = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));

        $response->assertOk();
        $response->assertSee('Playing XI locked.');
        $response->assertDontSee('Save Toss');
        $response->assertDontSee('Start Match');
    }

    public function test_scorer_sees_match_flow_controls(): void
    {
        $match = $this->matchReadyForToss();

        $response = $this->actingAs($this->scorer())->get(route('admin.matches.show', $match));

        $response->assertOk();
        $response->assertSee('Match Flow');
    }

    public function test_completed_match_shows_no_workflow_mutation_controls(): void
    {
        $match = $this->matchReadyToStart();
        $match->update(['match_status' => 'completed', 'started_at' => now(), 'completed_at' => now()]);

        $response = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));

        $response->assertOk();
        $response->assertDontSee('Start Toss');
        $response->assertDontSee('Save Toss');
        $response->assertDontSee('Start Match');
    }
}
