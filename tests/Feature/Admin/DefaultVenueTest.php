<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One venue can be the default: new matches start with it selected, and the
 * admin can still pick another for any match.
 */
class DefaultVenueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['name' => 'Admin', 'slug' => 'admin'])->id]);
    }

    public function test_choosing_a_default_venue_clears_the_previous_one(): void
    {
        $first = Venue::factory()->create(['is_default' => true]);

        $this->actingAs($this->admin)
            ->post(route('admin.venues.store'), ['name' => 'Sendriya Ground', 'is_default' => '1'])
            ->assertSessionHasNoErrors();

        $second = Venue::firstWhere('name', 'Sendriya Ground');
        $this->assertTrue($second->is_default);
        $this->assertFalse($first->fresh()->is_default);
        $this->assertSame(1, Venue::where('is_default', true)->count());

        // Editing a venue without ticking the box does not touch the default.
        $this->actingAs($this->admin)
            ->put(route('admin.venues.update', $first), ['name' => $first->name, 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($first->fresh()->is_default);
    }

    public function test_unticking_removes_the_default_and_an_inactive_venue_cannot_be_the_default(): void
    {
        $venue = Venue::factory()->create(['is_default' => true]);

        $this->actingAs($this->admin)
            ->put(route('admin.venues.update', $venue), ['name' => $venue->name, 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($venue->fresh()->is_default);

        $this->actingAs($this->admin)
            ->put(route('admin.venues.update', $venue), ['name' => $venue->name, 'is_active' => '0', 'is_default' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($venue->fresh()->is_default);
    }

    public function test_new_match_forms_start_with_the_default_venue_even_over_the_last_matchs_venue(): void
    {
        $default = Venue::factory()->create(['name' => 'Default Ground', 'is_default' => true]);
        $other = Venue::factory()->create(['name' => 'Other Ground']);
        $edition = Edition::factory()->create(['status' => 'active']);
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        GameMatch::factory()->create(['edition_id' => $edition->id, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id, 'venue_id' => $other->id]);

        foreach ([route('admin.matches.create'), route('admin.editions.matches.create', $edition)] as $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            $this->assertMatchesRegularExpression('#<option value="'.$default->id.'"\s+selected#', $html, $url);
            $this->assertDoesNotMatchRegularExpression('#<option value="'.$other->id.'"\s+selected#', $html, $url);
            // The admin can still choose another venue.
            $this->assertStringContainsString('value="'.$other->id.'"', $html, $url);
        }
    }

    public function test_without_a_default_the_last_matchs_venue_is_still_used_and_an_inactive_default_is_ignored(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $last = Venue::factory()->create();
        GameMatch::factory()->create(['edition_id' => $edition->id, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id, 'venue_id' => $last->id]);
        Venue::factory()->create(['is_default' => true, 'is_active' => false]);

        $html = $this->actingAs($this->admin)->get(route('admin.matches.create'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<option value="'.$last->id.'"\s+selected#', $html);
    }

    public function test_the_venues_list_marks_the_default_venue(): void
    {
        Venue::factory()->create(['name' => 'Marked Ground', 'is_default' => true]);

        $this->actingAs($this->admin)->get(route('admin.venues.index'))->assertOk()->assertSee('Default');
    }
}
