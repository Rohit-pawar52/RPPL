<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The matches of a season, managed from inside the season: this season's
 * fixtures in one list, and scheduling a new one with the season fixed and
 * only its own teams on offer.
 */
class SeasonMatchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $scorer;

    private Edition $edition;

    private EditionTeam $teamA;

    private EditionTeam $teamB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['name' => 'Admin', 'slug' => 'admin'])->id]);
        $this->scorer = User::factory()->create(['role_id' => Role::create(['name' => 'Scorer', 'slug' => 'scorer'])->id]);
        $this->edition = Edition::factory()->create(['status' => 'active']);
        $this->teamA = EditionTeam::factory()->create(['edition_id' => $this->edition->id, 'team_id' => Team::factory()->create(['name' => 'Alpha Lions'])->id]);
        $this->teamB = EditionTeam::factory()->create(['edition_id' => $this->edition->id, 'team_id' => Team::factory()->create(['name' => 'Beta Tigers'])->id]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'edition_team_a_id' => $this->teamA->id,
            'edition_team_b_id' => $this->teamB->id,
            'scheduled_at' => '2026-06-10T19:00',
        ], $overrides);
    }

    private function seasonMatch(array $overrides = []): GameMatch
    {
        return GameMatch::factory()->create(array_merge([
            'edition_id' => $this->edition->id,
            'edition_team_a_id' => $this->teamA->id,
            'edition_team_b_id' => $this->teamB->id,
        ], $overrides));
    }

    // ----- Access -----

    public function test_a_scorer_may_look_at_the_list_but_only_an_admin_can_schedule(): void
    {
        $this->get(route('admin.editions.matches.index', $this->edition))->assertRedirect(route('admin.login'));
        $this->get(route('admin.editions.matches.create', $this->edition))->assertRedirect(route('admin.login'));

        $this->actingAs($this->scorer);
        // Same visibility as the standalone Matches list; no "Add match" for a scorer.
        $this->get(route('admin.editions.matches.index', $this->edition))->assertOk()->assertDontSee('+ Add match');
        $this->get(route('admin.editions.matches.create', $this->edition))->assertForbidden();
        $this->post(route('admin.editions.matches.store', $this->edition), $this->payload())->assertForbidden();

        $this->assertSame(0, GameMatch::count());
    }

    // ----- The list -----

    public function test_the_list_shows_only_this_seasons_matches_in_the_display_timezone(): void
    {
        // 2026-10-02 18:50 UTC is 03 Oct 2026, 12:20 AM in India: the date differs from UTC.
        $this->seasonMatch(['match_number' => 7, 'overs_per_innings' => 8, 'scheduled_at' => '2026-10-02 18:50:00']);
        $other = Edition::factory()->create(['status' => 'active']);
        $foreign = GameMatch::factory()->create(['edition_id' => $other->id]);

        $html = $this->actingAs($this->admin)->get(route('admin.editions.matches.index', $this->edition))
            ->assertOk()
            ->assertSee('Alpha Lions vs Beta Tigers')
            ->assertSee('03 Oct 2026, 12:20 AM')
            ->assertDontSee('02 Oct 2026')
            ->assertDontSee($foreign->teamA->team->name)
            ->getContent();

        $this->assertStringContainsString('Matches in '.$this->edition->name.' (1)', $html);
    }

    public function test_the_list_is_ordered_by_date_and_can_be_filtered_by_status(): void
    {
        $later = $this->seasonMatch(['match_status' => 'scheduled', 'scheduled_at' => '2026-07-02 10:00:00', 'match_number' => 1]);
        $earlier = $this->seasonMatch(['match_status' => 'scheduled', 'scheduled_at' => '2026-07-01 10:00:00', 'match_number' => 2]);
        $done = $this->seasonMatch(['match_status' => 'completed', 'scheduled_at' => '2026-06-01 10:00:00', 'match_number' => 3]);

        $this->actingAs($this->admin);

        $all = $this->get(route('admin.editions.matches.index', $this->edition))->assertOk()->getContent();
        $this->assertLessThan(
            strpos($all, route('admin.matches.show', $earlier)),
            strpos($all, route('admin.matches.show', $done))
        );
        $this->assertLessThan(
            strpos($all, route('admin.matches.show', $later)),
            strpos($all, route('admin.matches.show', $earlier))
        );

        $this->get(route('admin.editions.matches.index', [$this->edition, 'match_status' => 'completed']))
            ->assertOk()
            ->assertSee(route('admin.matches.show', $done))
            ->assertDontSee(route('admin.matches.show', $later));

        // An unknown status is ignored rather than filtering everything away.
        $this->get(route('admin.editions.matches.index', [$this->edition, 'match_status' => 'bogus']))
            ->assertOk()
            ->assertSee(route('admin.matches.show', $later));
    }

    // ----- Scheduling -----

    public function test_the_form_has_no_edition_dropdown_and_offers_only_this_seasons_teams_with_defaults(): void
    {
        EditionTeam::factory()->create(['team_id' => Team::factory()->create(['name' => 'Gamma Hawks'])->id]);
        $this->seasonMatch(['match_number' => 4, 'overs_per_innings' => 10, 'venue_id' => Venue::factory()->create()->id]);

        $this->actingAs($this->admin)->get(route('admin.editions.matches.create', $this->edition))
            ->assertOk()
            ->assertSee('Alpha Lions')
            ->assertSee('Beta Tigers')
            ->assertDontSee('Gamma Hawks')
            ->assertDontSee('name="edition_id"', false)
            ->assertSee('name="match_number"', false)
            ->assertSee('value="5"', false)
            ->assertSee('value="10"', false)
            ->assertSee('data-overs-picks', false);
    }

    public function test_a_match_is_stored_in_the_url_season_with_the_time_converted_to_utc(): void
    {
        $other = Edition::factory()->create(['status' => 'active']);

        $this->actingAs($this->admin)
            ->post(route('admin.editions.matches.store', $this->edition), $this->payload([
                'edition_id' => $other->id, // a posted season is ignored; the URL decides
                'match_number' => 3,
            ]))
            ->assertRedirect(route('admin.editions.matches.index', $this->edition))
            ->assertSessionHas('success');

        $match = GameMatch::sole();
        $this->assertSame($this->edition->id, $match->edition_id);
        $this->assertSame(3, $match->match_number);
        // 19:00 in India (UTC+5:30) is 13:30 UTC.
        $this->assertSame('2026-06-10 13:30:00', $match->scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_a_team_of_another_season_or_the_same_team_twice_is_rejected(): void
    {
        $outsider = EditionTeam::factory()->create();

        $this->actingAs($this->admin);

        $this->post(route('admin.editions.matches.store', $this->edition), $this->payload(['edition_team_b_id' => $outsider->id]))
            ->assertSessionHasErrors('edition_team_b_id');

        $this->post(route('admin.editions.matches.store', $this->edition), $this->payload(['edition_team_b_id' => $this->teamA->id]))
            ->assertSessionHasErrors('edition_team_a_id');

        $this->assertSame(0, GameMatch::count());
    }

    public function test_a_match_number_already_used_in_this_season_is_rejected_but_is_free_in_another(): void
    {
        $other = Edition::factory()->create(['status' => 'active']);
        GameMatch::factory()->create(['edition_id' => $other->id, 'match_number' => 1]);
        $this->seasonMatch(['match_number' => 2]);

        $this->actingAs($this->admin);

        $this->post(route('admin.editions.matches.store', $this->edition), $this->payload(['match_number' => 2]))
            ->assertSessionHasErrors('match_number');

        $this->post(route('admin.editions.matches.store', $this->edition), $this->payload(['match_number' => 1]))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(1, GameMatch::where('edition_id', $this->edition->id)->where('match_number', 1)->count());
    }

    public function test_a_completed_season_can_be_listed_but_nothing_new_can_be_scheduled(): void
    {
        $this->edition->update(['status' => 'completed']);
        $existing = $this->seasonMatch();

        $this->actingAs($this->admin);

        $this->get(route('admin.editions.matches.index', $this->edition))
            ->assertOk()
            ->assertSee(route('admin.matches.show', $existing))
            ->assertSee('cannot be scheduled')
            ->assertDontSee('+ Add match');

        $this->get(route('admin.editions.matches.create', $this->edition))
            ->assertRedirect(route('admin.editions.matches.index', $this->edition))
            ->assertSessionHas('error');

        $this->post(route('admin.editions.matches.store', $this->edition), $this->payload())
            ->assertRedirect(route('admin.editions.matches.index', $this->edition))
            ->assertSessionHas('error');

        $this->assertSame(1, GameMatch::count());
    }
}
