<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Thin integration coverage for wiring StandingsService into the
 * existing admin.editions.show page. Calculation correctness itself is
 * covered by StandingsServiceTest.
 */
class EditionStandingsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'slug' => 'admin']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
    }

    public function test_edition_show_renders_points_table_alongside_existing_leaderboards_and_records(): void
    {
        $edition = Edition::factory()->create();
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'match_status' => 'completed',
            'result_type' => 'won',
            'winner_team_id' => $teamA->id,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.editions.show', $edition));

        $response->assertOk();
        $response->assertSee('Points Table');
        $response->assertSee($teamA->team->name);
        $response->assertSee($teamB->team->name);
        // Phase 3.16 sections must still render unchanged alongside it.
        $response->assertSee('Top Run Scorers');
        $response->assertSee('Top Wicket Takers');
        $response->assertSee('Records');
    }

    public function test_edition_show_renders_zero_row_standings_when_no_completed_matches_exist(): void
    {
        $edition = Edition::factory()->create();
        EditionTeam::factory()->count(2)->create(['edition_id' => $edition->id]);

        $response = $this->actingAs($this->admin())->get(route('admin.editions.show', $edition));

        $response->assertOk();
        $response->assertSee('Points Table');
        $response->assertDontSee('excluded because');
    }

    public function test_integrity_problem_does_not_crash_edition_show(): void
    {
        $edition = Edition::factory()->create();
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        // Same team on both sides of a "completed" match — malformed.
        GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamA->id,
            'match_status' => 'completed',
            'result_type' => 'won',
            'winner_team_id' => $teamA->id,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.editions.show', $edition));

        $response->assertOk();
        $response->assertSee('excluded because');
    }
}
