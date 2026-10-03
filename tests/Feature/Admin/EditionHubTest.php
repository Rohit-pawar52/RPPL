<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Edition hub (summary cards that open each section of a season) and
 * the Teams section inside a season.
 */
class EditionHubTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $scorer = Role::create(['name' => 'Scorer', 'slug' => 'scorer']);
        $this->admin = User::factory()->create(['role_id' => $admin->id]);
        $this->scorer = User::factory()->create(['role_id' => $scorer->id]);
    }

    private User $scorer;

    // ----- The hub -----

    public function test_the_hub_shows_quick_counts_and_each_card_opens_its_section(): void
    {
        $edition = Edition::factory()->create(['name' => 'RPPL Season 3', 'status' => 'active']);
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'pending']);
        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'pending']);
        $inTeam = PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'paid']);
        TeamPlayer::factory()->create(['edition_team_id' => $teamA->id, 'player_registration_id' => $inTeam->id]);

        foreach (['completed', 'completed', 'scheduled', 'live', 'abandoned'] as $status) {
            GameMatch::factory()->create([
                'edition_id' => $edition->id,
                'edition_team_a_id' => $teamA->id,
                'edition_team_b_id' => $teamB->id,
                'match_status' => $status,
            ]);
        }

        $html = $this->actingAs($this->admin)->get(route('admin.editions.show', $edition))->assertOk()->getContent();

        foreach (['Registrations', 'Teams', 'Squads', 'Matches', 'Finance'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertStringContainsString('2 pending', $html);
        $this->assertStringContainsString('2 without a team', $html);
        $this->assertStringContainsString('2 played · 2 to play', $html);

        // The cards are the way in.
        foreach ([
            route('admin.editions.registrations.index', $edition),
            route('admin.editions.teams.index', $edition),
            route('admin.editions.squads.index', $edition),
            route('admin.editions.matches.index', $edition),
            route('admin.edition-transactions.index', ['edition_id' => $edition->id]),
            route('admin.edition-contributions.index', ['edition_id' => $edition->id]),
        ] as $url) {
            $this->assertStringContainsString('href="'.$url.'"', $html);
        }
    }

    public function test_a_scorer_cannot_open_the_hub_or_the_teams_section(): void
    {
        $edition = Edition::factory()->create();

        $this->actingAs($this->scorer)->get(route('admin.editions.show', $edition))->assertForbidden();
        $this->actingAs($this->scorer)->get(route('admin.editions.teams.index', $edition))->assertForbidden();
        $this->actingAs($this->scorer)->post(route('admin.editions.teams.store', $edition), ['team_ids' => [1]])->assertForbidden();
    }

    // ----- Teams in a season -----

    public function test_the_teams_page_lists_this_seasons_teams_and_offers_only_teams_that_can_be_added(): void
    {
        $season = Edition::factory()->create(['name' => 'RPPL Season 3', 'status' => 'active']);
        $other = Edition::factory()->create(['status' => 'upcoming']);

        $in = EditionTeam::factory()->create(['edition_id' => $season->id, 'team_id' => Team::factory()->create(['name' => 'Already In'])->id]);
        EditionTeam::factory()->create(['edition_id' => $other->id, 'team_id' => Team::factory()->create(['name' => 'Other Season Only'])->id]);
        Team::factory()->create(['name' => 'Dormant Team', 'is_active' => false]);

        $html = $this->actingAs($this->admin)->get(route('admin.editions.teams.index', $season))->assertOk()->getContent();

        // In the table and not offered again.
        $this->assertStringContainsString('Already In', $html);
        $this->assertDoesNotMatchRegularExpression('#name="team_ids\[\]" value="'.$in->team_id.'"#', $html);
        // A team from another season is offered; an inactive one is not.
        $this->assertStringContainsString('Other Season Only', $html);
        $this->assertStringNotContainsString('Dormant Team', $html);
        // Breadcrumb back to the hub.
        $this->assertStringContainsString('href="'.route('admin.editions.show', $season).'"', $html);
    }

    public function test_several_teams_are_added_in_one_go_skipping_ones_that_cannot_join(): void
    {
        $season = Edition::factory()->create(['status' => 'upcoming']);
        [$one, $two, $three] = Team::factory()->count(3)->create();
        $inactive = Team::factory()->create(['is_active' => false]);
        EditionTeam::factory()->create(['edition_id' => $season->id, 'team_id' => $three->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.editions.teams.store', $season), ['team_ids' => [$one->id, $two->id, $three->id, $inactive->id, 999999]])
            ->assertRedirect(route('admin.editions.teams.index', $season))
            ->assertSessionHas('success', '2 teams added to the season.');

        $this->assertEqualsCanonicalizing(
            [$one->id, $two->id, $three->id],
            EditionTeam::where('edition_id', $season->id)->pluck('team_id')->all(),
        );
    }

    public function test_adding_with_nothing_ticked_is_rejected(): void
    {
        $season = Edition::factory()->create(['status' => 'upcoming']);

        $this->actingAs($this->admin)
            ->post(route('admin.editions.teams.store', $season), [])
            ->assertSessionHasErrors(['team_ids' => 'Tick at least one team to add.']);
    }

    public function test_a_new_team_can_be_created_and_added_in_one_step(): void
    {
        Storage::fake('public');
        $season = Edition::factory()->create(['status' => 'upcoming']);

        $this->actingAs($this->admin)
            ->post(route('admin.editions.teams.store-new', $season), [
                'name' => 'Sendriya Strikers',
                'short_name' => 'SDS',
                'logo' => UploadedFile::fake()->create('logo.png', 40, 'image/png'),
            ])
            ->assertRedirect(route('admin.editions.teams.index', $season))
            ->assertSessionHas('success');

        $team = Team::firstWhere('name', 'Sendriya Strikers');
        $this->assertNotNull($team);
        $this->assertSame('SDS', $team->short_name);
        Storage::disk('public')->assertExists($team->logo_path);
        $this->assertDatabaseHas('edition_teams', ['edition_id' => $season->id, 'team_id' => $team->id]);
    }

    public function test_a_new_team_with_a_taken_name_fails_in_its_own_error_bag_and_creates_nothing(): void
    {
        $season = Edition::factory()->create(['status' => 'upcoming']);
        Team::factory()->create(['name' => 'Taken Name']);

        $this->actingAs($this->admin)
            ->post(route('admin.editions.teams.store-new', $season), ['name' => 'Taken Name'])
            ->assertSessionHasErrorsIn('newTeam', 'name');

        $this->assertSame(0, EditionTeam::where('edition_id', $season->id)->count());
    }

    public function test_a_completed_season_cannot_get_new_teams_but_can_still_be_corrected_by_removal(): void
    {
        $season = Edition::factory()->create(['status' => 'completed']);
        $team = Team::factory()->create();
        $existing = EditionTeam::factory()->create(['edition_id' => $season->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.editions.teams.store', $season), ['team_ids' => [$team->id]])
            ->assertSessionHas('error');
        $this->actingAs($this->admin)
            ->post(route('admin.editions.teams.store-new', $season), ['name' => 'Latecomers'])
            ->assertSessionHas('error');
        $this->assertDatabaseMissing('teams', ['name' => 'Latecomers']);
        $this->assertSame(1, EditionTeam::where('edition_id', $season->id)->count());

        $this->actingAs($this->admin)
            ->delete(route('admin.editions.teams.destroy', [$season, $existing]))
            ->assertSessionHas('success');
    }

    public function test_removing_a_team_takes_its_squad_but_keeps_registrations_and_the_team(): void
    {
        $season = Edition::factory()->create(['status' => 'upcoming']);
        $editionTeam = EditionTeam::factory()->create(['edition_id' => $season->id]);
        $registration = PlayerRegistration::factory()->create(['edition_id' => $season->id]);
        TeamPlayer::factory()->create(['edition_team_id' => $editionTeam->id, 'player_registration_id' => $registration->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.editions.teams.destroy', [$season, $editionTeam]))
            ->assertRedirect(route('admin.editions.teams.index', $season))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('edition_teams', ['id' => $editionTeam->id]);
        $this->assertDatabaseMissing('team_players', ['player_registration_id' => $registration->id]);
        $this->assertDatabaseHas('player_registrations', ['id' => $registration->id]);
        $this->assertDatabaseHas('teams', ['id' => $editionTeam->team_id]);
    }

    public function test_a_team_with_matches_cannot_be_removed_and_the_page_shows_no_remove_button(): void
    {
        $season = Edition::factory()->create(['status' => 'active']);
        $teamA = EditionTeam::factory()->create(['edition_id' => $season->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $season->id]);
        GameMatch::factory()->create(['edition_id' => $season->id, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id]);

        $page = $this->actingAs($this->admin)->get(route('admin.editions.teams.index', $season))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('admin.editions.teams.destroy', [$season, $teamA]), $page);
        $this->assertStringContainsString('Has matches', $page);

        $this->actingAs($this->admin)
            ->delete(route('admin.editions.teams.destroy', [$season, $teamA]))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('edition_teams', ['id' => $teamA->id]);
    }

    public function test_a_team_of_another_season_cannot_be_removed_through_this_seasons_url(): void
    {
        $season = Edition::factory()->create(['status' => 'upcoming']);
        $other = Edition::factory()->create(['status' => 'upcoming']);
        $foreign = EditionTeam::factory()->create(['edition_id' => $other->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.editions.teams.destroy', [$season, $foreign]))
            ->assertNotFound();

        $this->assertDatabaseHas('edition_teams', ['id' => $foreign->id]);
    }
}
