<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\GameMatch;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two small tournament-setup rules: only one edition may be active, and
 * the new-match form starts pre-filled so a fixture list goes in quickly.
 */
class TournamentSetupQuickWinsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->admin = User::factory()->create(['role_id' => $role->id]);
    }

    private function payload(Edition $edition, string $status): array
    {
        return ['name' => $edition->name, 'year' => $edition->year, 'status' => $status];
    }

    // ----- One active edition -----

    public function test_a_second_edition_cannot_be_made_active_until_the_first_is_closed(): void
    {
        $first = Edition::factory()->create(['name' => 'RPPL 2025', 'status' => 'active']);
        $second = Edition::factory()->create(['status' => 'upcoming']);

        $this->actingAs($this->admin)
            ->put(route('admin.editions.update', $second), $this->payload($second, 'active'))
            ->assertSessionHasErrors(['status' => '"RPPL 2025" is already the active edition. Mark it completed (or upcoming) first, then activate this one.']);
        $this->assertSame('upcoming', $second->fresh()->status);
        $this->assertSame('active', $first->fresh()->status, 'the other season is never changed silently');

        $this->actingAs($this->admin)->put(route('admin.editions.update', $first), $this->payload($first, 'completed'))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->put(route('admin.editions.update', $second), $this->payload($second, 'active'))->assertSessionHasNoErrors();

        $this->assertSame('active', $second->fresh()->status);
    }

    public function test_a_new_edition_cannot_start_active_while_another_is_active(): void
    {
        // A fixed year: the factory picks a random one (2020-2099) and, one run in
        // eighty, picked the 2099 this test then checks was never created.
        Edition::factory()->create(['status' => 'active', 'year' => 2026]);

        $this->actingAs($this->admin)
            ->post(route('admin.editions.store'), ['name' => 'RPPL 2099', 'year' => 2099, 'status' => 'active'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('editions', ['year' => 2099]);

        $this->actingAs($this->admin)
            ->post(route('admin.editions.store'), ['name' => 'RPPL 2099', 'year' => 2099, 'status' => 'upcoming'])
            ->assertSessionHasNoErrors();
    }

    public function test_resaving_an_already_active_edition_is_never_blocked(): void
    {
        // Older data may already have two active editions; editing one of
        // them (e.g. its name) must still work.
        $one = Edition::factory()->create(['status' => 'active']);
        Edition::factory()->create(['status' => 'active']);

        $this->actingAs($this->admin)
            ->put(route('admin.editions.update', $one), ['name' => 'Renamed', 'year' => $one->year, 'status' => 'active'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $one->fresh()->name);
    }

    // ----- New-match form defaults -----

    public function test_the_new_match_form_starts_with_the_current_season_next_number_and_last_overs_and_venue(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $venue = Venue::factory()->create(['name' => 'Sendriya Ground']);
        GameMatch::factory()->create(['edition_id' => $edition->id, 'match_number' => 7, 'overs_per_innings' => 8, 'venue_id' => $venue->id]);
        GameMatch::factory()->create(['edition_id' => $edition->id, 'match_number' => 12, 'overs_per_innings' => 8, 'venue_id' => $venue->id]);

        $html = $this->actingAs($this->admin)->get(route('admin.matches.create'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<option value="'.$edition->id.'"\s+selected#', $html);
        $this->assertMatchesRegularExpression('#<option value="'.$venue->id.'"\s+selected#', $html);
        $this->assertMatchesRegularExpression('#name="match_number"[^>]*value="13"#', $html);
        $this->assertMatchesRegularExpression('#name="overs_per_innings"[^>]*value="8"#', $html);
        // The quick picks.
        foreach ([6, 8, 10, 12, 15, 20] as $overs) {
            $this->assertStringContainsString('data-overs="'.$overs.'"', $html);
        }
    }

    public function test_the_new_match_form_defaults_when_there_is_no_season_or_match_yet(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.matches.create'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#name="overs_per_innings"[^>]*value="20"#', $html);
        $this->assertDoesNotMatchRegularExpression('#name="match_number"[^>]*value="\d#', $html);
    }

    public function test_the_edit_form_keeps_the_matchs_own_values_not_the_defaults(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        GameMatch::factory()->create(['edition_id' => $edition->id, 'match_number' => 30, 'overs_per_innings' => 15]);
        $match = GameMatch::factory()->create(['edition_id' => $edition->id, 'match_number' => 2, 'overs_per_innings' => 6]);

        $html = $this->actingAs($this->admin)->get(route('admin.matches.edit', $match))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#name="match_number"[^>]*value="2"#', $html);
        $this->assertMatchesRegularExpression('#name="overs_per_innings"[^>]*value="6"#', $html);
    }
}
