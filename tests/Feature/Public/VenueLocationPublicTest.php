<?php

namespace Tests\Feature\Public;

use App\Models\GameMatch;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueLocationPublicTest extends TestCase
{
    use RefreshDatabase;

    private function matchAt(Venue $venue): GameMatch
    {
        return GameMatch::factory()->create(['venue_id' => $venue->id, 'match_status' => 'scheduled']);
    }

    public function test_match_info_shows_location_map_and_exact_directions_link_when_pinned(): void
    {
        $venue = Venue::factory()->create([
            'name' => 'Chicholi Maidan',
            'village' => 'Chicholi',
            'tehsil' => 'Betul',
            'district' => 'Betul',
            'latitude' => 21.9012345,
            'longitude' => 77.9,
        ]);

        $response = $this->get(route('public.matches.show', $this->matchAt($venue)));

        $response->assertOk();
        $response->assertSee('Chicholi, Betul, Betul');
        $response->assertSee('data-venue-map', false);
        $response->assertSee('data-lat="21.9012345"', false);
        // The link carries the exact pin and opens safely in a new tab.
        $response->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination=21.9012345,77.9"', false);
        $response->assertSee('target="_blank"', false);
        $response->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_match_info_shows_no_map_stuff_without_coordinates(): void
    {
        $venue = Venue::factory()->create(['village' => 'Chicholi', 'latitude' => null, 'longitude' => null]);

        $response = $this->get(route('public.matches.show', $this->matchAt($venue)));

        $response->assertOk();
        $response->assertSee('Chicholi');
        $response->assertDontSee('data-venue-map', false);
        $response->assertDontSee('google.com/maps', false);
        $response->assertDontSee('Get directions');
    }

    public function test_old_venue_with_only_city_and_country_still_renders(): void
    {
        $venue = Venue::factory()->create(['city' => 'Nagpur', 'country' => 'India']);

        $this->get(route('public.matches.show', $this->matchAt($venue)))
            ->assertOk()
            ->assertSee('Nagpur, India');

        $this->get(route('public.venues.show', $venue))
            ->assertOk()
            ->assertSee('Nagpur, India')
            ->assertDontSee('data-venue-map', false);
    }

    public function test_venue_page_shows_map_and_directions_when_pinned(): void
    {
        $venue = Venue::factory()->create(['village' => 'Chicholi', 'latitude' => 21.9, 'longitude' => 77.9]);

        $this->get(route('public.venues.show', $venue))
            ->assertOk()
            ->assertSee('Chicholi')
            ->assertSee('data-venue-map', false)
            ->assertSee('destination=21.9,77.9"', false)
            ->assertSee('Get directions');
    }

    public function test_directions_label_is_translated_to_hindi(): void
    {
        $venue = Venue::factory()->create(['latitude' => 21.9, 'longitude' => 77.9]);

        $this->withCookie('rppl_locale', 'hi')->get(route('public.venues.show', $venue))
            ->assertOk()
            ->assertSee('रास्ता देखें')
            ->assertDontSee('Get directions');
    }
}
