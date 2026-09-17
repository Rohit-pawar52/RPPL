<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    public function test_dashboard_prefers_active_edition_over_upcoming_and_completed(): void
    {
        Edition::factory()->create(['name' => 'RPPL Completed', 'status' => 'completed', 'year' => 2023]);
        Edition::factory()->create(['name' => 'RPPL Upcoming', 'status' => 'upcoming', 'year' => 2026]);
        $active = Edition::factory()->create(['name' => 'RPPL Active', 'status' => 'active', 'year' => 2025]);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee($active->name);
        $response->assertDontSee('RPPL Upcoming');
        $response->assertDontSee('RPPL Completed');
    }

    public function test_dashboard_falls_back_to_upcoming_then_completed_edition(): void
    {
        $completed = Edition::factory()->create(['name' => 'RPPL Old', 'status' => 'completed', 'year' => 2022]);

        // Only a completed edition exists — that's the fallback.
        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));
        $response->assertOk()->assertSee($completed->name);

        $upcoming = Edition::factory()->create(['name' => 'RPPL Next', 'status' => 'upcoming', 'year' => 2027]);

        // Upcoming now outranks the completed edition.
        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));
        $response->assertOk()->assertSee($upcoming->name)->assertDontSee('RPPL Old');
    }

    public function test_no_editions_shows_empty_state(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('No editions available yet');
    }

    public function test_summary_counts_are_scoped_to_the_selected_edition(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $otherEdition = Edition::factory()->create(['status' => 'completed', 'year' => 2020]);

        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $paidRegistration = PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'paid']);
        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'pending']);
        TeamPlayer::factory()->create(['edition_team_id' => $teamA->id, 'player_registration_id' => $paidRegistration->id]);
        GameMatch::factory()->create(['edition_id' => $edition->id, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id, 'match_status' => 'scheduled']);

        // Data belonging to a different edition must never bleed into these counts.
        $otherTeamA = EditionTeam::factory()->create(['edition_id' => $otherEdition->id]);
        $otherTeamB = EditionTeam::factory()->create(['edition_id' => $otherEdition->id]);
        PlayerRegistration::factory()->count(5)->create(['edition_id' => $otherEdition->id, 'payment_status' => 'paid']);
        GameMatch::factory()->count(3)->create(['edition_id' => $otherEdition->id, 'edition_team_a_id' => $otherTeamA->id, 'edition_team_b_id' => $otherTeamB->id, 'match_status' => 'completed']);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('registeredPlayers', 2);
        $response->assertViewHas('paidRegistrations', 1);
        $response->assertViewHas('pendingRegistrations', 1);
        $response->assertViewHas('teamsCount', 2);
        $response->assertViewHas('squadPlayersCount', 1);
        $response->assertViewHas('matchesCount', 1);
        $response->assertViewHas('scheduledMatchesCount', 1);
    }

    public function test_matches_needing_attention_prioritizes_live_toss_then_scheduled_and_is_bounded(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        $live = GameMatch::factory()->create(['edition_id' => $edition->id, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id, 'match_status' => 'live', 'started_at' => now()]);
        $toss = GameMatch::factory()->create(['edition_id' => $edition->id, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id, 'match_status' => 'toss']);

        // 8 scheduled matches — the section is bounded to 8 rows total,
        // so with a live and a toss match already present, only 6 of
        // these scheduled matches should make the cut.
        foreach (range(1, 8) as $i) {
            GameMatch::factory()->create([
                'edition_id' => $edition->id, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id,
                'match_status' => 'scheduled', 'scheduled_at' => now()->addDays($i),
            ]);
        }

        GameMatch::factory()->create(['edition_id' => $edition->id, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id, 'match_status' => 'completed', 'match_result' => 'Result']);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $attention = $response->viewData('matchesNeedingAttention');

        $this->assertCount(8, $attention);
        $this->assertSame($live->id, $attention->first()->id);
        $this->assertSame($toss->id, $attention->get(1)->id);
        $this->assertTrue($attention->pluck('match_status')->doesntContain('completed'));
    }

    public function test_recent_results_show_stored_result_for_completed_matches_only(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        $completed = GameMatch::factory()->create([
            'edition_id' => $edition->id, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id,
            'match_status' => 'completed', 'match_result' => $teamA->team->name.' won by 42 runs',
        ]);
        GameMatch::factory()->create(['edition_id' => $edition->id, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id, 'match_status' => 'scheduled']);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee($teamA->team->name.' won by 42 runs');

        $recentResults = $response->viewData('recentResults');
        $this->assertCount(1, $recentResults);
        $this->assertSame($completed->id, $recentResults->first()->id);
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    /**
     * Phase 3.41 — the sidebar previously had permanently-disabled
     * "Scoring"/"Reports"/"Settings" placeholders that misleadingly
     * suggested unfinished features (scoring is fully built, just
     * accessed per-match rather than as a top-level page). Removed
     * outright rather than pointed at a route, per the audit.
     */
    public function test_sidebar_no_longer_shows_disabled_placeholder_items(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Scoring');
        $response->assertDontSee('Reports');
        $response->assertDontSee('Settings');
    }
}
