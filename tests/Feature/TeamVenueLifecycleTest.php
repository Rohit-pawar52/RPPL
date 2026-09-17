<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Team and Venue have no CRUD/UI yet — these are pure model/scope tests
 * confirming the is_active lifecycle foundation added in Phase 3.3.5
 * behaves correctly ahead of their future admin modules.
 */
class TeamVenueLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_team_defaults_active(): void
    {
        $team = Team::factory()->create();

        $this->assertTrue($team->fresh()->is_active);
    }

    public function test_new_venue_defaults_active(): void
    {
        $venue = Venue::factory()->create();

        $this->assertTrue($venue->fresh()->is_active);
    }

    public function test_team_active_scope_returns_only_active_teams(): void
    {
        Team::factory()->count(2)->create();
        Team::factory()->count(3)->create(['is_active' => false]);

        $this->assertSame(2, Team::active()->count());
    }

    public function test_plain_team_query_still_includes_inactive_teams(): void
    {
        Team::factory()->count(2)->create();
        Team::factory()->count(3)->create(['is_active' => false]);

        $this->assertSame(5, Team::query()->count());
    }

    public function test_venue_active_scope_returns_only_active_venues(): void
    {
        Venue::factory()->count(2)->create();
        Venue::factory()->count(3)->create(['is_active' => false]);

        $this->assertSame(2, Venue::active()->count());
    }

    public function test_plain_venue_query_still_includes_inactive_venues(): void
    {
        Venue::factory()->count(2)->create();
        Venue::factory()->count(3)->create(['is_active' => false]);

        $this->assertSame(5, Venue::query()->count());
    }
}
