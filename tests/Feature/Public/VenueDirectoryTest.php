<?php

namespace Tests\Feature\Public;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_shows_active_venues_and_hides_inactive_ones(): void
    {
        $active = Venue::factory()->create(['name' => 'Wankhede Ground', 'is_active' => true]);
        $inactive = Venue::factory()->create(['name' => 'Retired Stadium', 'is_active' => false]);

        $response = $this->get(route('public.venues.index'));

        $response->assertOk();
        $response->assertSee($active->name);
        $response->assertDontSee($inactive->name);
    }

    public function test_search_filters_by_name_or_city(): void
    {
        Venue::factory()->create(['name' => 'Chinnaswamy Stadium', 'city' => 'Bengaluru', 'is_active' => true]);
        Venue::factory()->create(['name' => 'Eden Gardens', 'city' => 'Kolkata', 'is_active' => true]);

        $response = $this->get(route('public.venues.index', ['search' => 'Bengaluru']));

        $response->assertOk();
        $response->assertSee('Chinnaswamy Stadium');
        $response->assertDontSee('Eden Gardens');
    }

    public function test_inactive_venue_profile_remains_accessible(): void
    {
        $venue = Venue::factory()->create(['is_active' => false]);

        $response = $this->get(route('public.venues.show', $venue));

        $response->assertOk();
        $response->assertSee($venue->name);
    }

    public function test_venue_profile_shows_relevant_matches_with_public_links(): void
    {
        $venue = Venue::factory()->create();
        $edition = Edition::factory()->create();
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        $upcoming = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'venue_id' => $venue->id,
            'match_status' => 'scheduled',
        ]);

        $completed = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'venue_id' => $venue->id,
            'match_status' => 'completed',
            'result_type' => 'won',
            'winner_team_id' => $teamA->id,
            'match_result' => $teamA->team->name.' won by 20 runs',
        ]);

        // A match at a different venue must never appear here.
        $otherVenue = Venue::factory()->create();
        $elsewhere = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'venue_id' => $otherVenue->id,
            'match_status' => 'scheduled',
        ]);

        $response = $this->get(route('public.venues.show', $venue));

        $response->assertOk();
        $response->assertSee(route('public.matches.show', $upcoming), false);
        $response->assertSee(route('public.matches.show', $completed), false);
        $response->assertSee($teamA->team->name.' won by 20 runs');
        $response->assertDontSee(route('public.matches.show', $elsewhere), false);
    }

    public function test_venue_with_no_matches_shows_empty_states(): void
    {
        $venue = Venue::factory()->create();

        $response = $this->get(route('public.venues.show', $venue));

        $response->assertOk();
        $response->assertSee('No upcoming matches at this venue.');
        $response->assertSee('No completed matches at this venue yet.');
    }
}
