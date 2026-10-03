<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The registrations of a season, opened from inside the season: the list
 * with its filters, and the bulk "Add to team".
 */
class SeasonRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $scorer;

    private Edition $edition;

    private EditionTeam $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['name' => 'Admin', 'slug' => 'admin'])->id]);
        $this->scorer = User::factory()->create(['role_id' => Role::create(['name' => 'Scorer', 'slug' => 'scorer'])->id]);
        $this->edition = Edition::factory()->create(['status' => 'upcoming']);
        $this->team = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
    }

    private function registration(array $overrides = [], array $player = []): PlayerRegistration
    {
        return PlayerRegistration::factory()->create(array_merge([
            'edition_id' => $this->edition->id,
            'player_id' => Player::factory()->create($player)->id,
            'payment_status' => 'paid',
        ], $overrides));
    }

    private function indexUrl(array $query = []): string
    {
        return route('admin.editions.registrations.index', ['edition' => $this->edition->id] + $query);
    }

    private function addUrl(): string
    {
        return route('admin.editions.registrations.add-to-team', $this->edition);
    }

    // ----- The list -----

    public function test_the_list_shows_only_this_seasons_registrations_with_their_team(): void
    {
        $inTeam = $this->registration([], ['name' => 'Bought Player']);
        TeamPlayer::factory()->create(['edition_team_id' => $this->team->id, 'player_registration_id' => $inTeam->id]);
        $this->registration(['village' => 'Sendriya'], ['name' => 'Free Player']);
        PlayerRegistration::factory()->create(['player_id' => Player::factory()->create(['name' => 'Other Season Player'])->id]);

        $html = $this->actingAs($this->admin)->get($this->indexUrl())->assertOk()->getContent();

        $this->assertStringContainsString('Bought Player', $html);
        $this->assertStringContainsString($this->team->team->name, $html);
        $this->assertStringContainsString('Free Player', $html);
        $this->assertStringContainsString('Sendriya', $html);
        $this->assertStringNotContainsString('Other Season Player', $html);
        $this->assertStringContainsString(route('admin.player-registrations.show', $inTeam), $html);
        // Only the player who is in no team can be ticked.
        $this->assertStringContainsString('aria-label="Select Free Player"', $html);
        $this->assertStringNotContainsString('aria-label="Select Bought Player"', $html);
    }

    public function test_the_list_can_be_searched_and_filtered_by_payment_status_and_team(): void
    {
        $otherTeam = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
        $alice = $this->registration(['payment_status' => 'pending'], ['name' => 'Alice Pending', 'phone' => '9000000001']);
        $bob = $this->registration(['payment_status' => 'paid'], ['name' => 'Bob InTeam', 'phone' => '9000000002']);
        $carol = $this->registration(['payment_status' => 'paid'], ['name' => 'Carol OtherTeam', 'phone' => '9000000003']);
        TeamPlayer::factory()->create(['edition_team_id' => $this->team->id, 'player_registration_id' => $bob->id]);
        TeamPlayer::factory()->create(['edition_team_id' => $otherTeam->id, 'player_registration_id' => $carol->id]);

        $names = fn (array $query) => collect($this->actingAs($this->admin)->get($this->indexUrl($query))->assertOk()->viewData('registrations')->items())
            ->map(fn ($registration) => $registration->player->name)->sort()->values()->all();

        $this->assertSame(['Alice Pending'], $names(['search' => '9000000001']));
        $this->assertSame(['Bob InTeam'], $names(['search' => $bob->registration_number]));
        $this->assertSame(['Alice Pending'], $names(['payment_status' => 'pending']));
        $this->assertSame(['Alice Pending'], $names(['team' => 'none']));
        $this->assertSame(['Bob InTeam'], $names(['team' => (string) $this->team->id]));
        $this->assertSame(['Carol OtherTeam'], $names(['team' => (string) $otherTeam->id]));
        $this->assertSame(['Carol OtherTeam'], $names(['team' => (string) $otherTeam->id, 'payment_status' => 'paid']));
        $this->assertSame([], $names(['team' => 'none', 'payment_status' => 'paid']));
        $this->assertSame(['Alice Pending', 'Bob InTeam', 'Carol OtherTeam'], $names([]));
    }

    public function test_the_page_links_to_review_pending_and_export_for_this_season(): void
    {
        $html = $this->actingAs($this->admin)->get($this->indexUrl(['payment_status' => 'pending']))->assertOk()->getContent();

        $this->assertStringContainsString(e(route('admin.player-registrations.review-pending', ['edition_id' => $this->edition->id])), $html);
        $this->assertStringContainsString(e(route('admin.player-registrations.export', ['edition_id' => $this->edition->id, 'payment_status' => 'pending'])), $html);
    }

    public function test_only_an_admin_can_use_the_registration_pages(): void
    {
        $registration = $this->registration();

        foreach ([
            fn () => $this->get($this->indexUrl()),
            fn () => $this->post($this->addUrl(), ['selected' => [$registration->id], 'edition_team_id' => $this->team->id]),
        ] as $request) {
            $this->actingAs($this->scorer);
            $request()->assertForbidden();
        }

        $this->assertSame(0, TeamPlayer::count());
    }

    // ----- Add to team -----

    public function test_ticked_players_are_added_to_the_team_without_a_sold_amount_and_unfit_ones_are_skipped(): void
    {
        $one = $this->registration();
        $two = $this->registration();
        $already = $this->registration();
        $otherTeam = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
        TeamPlayer::factory()->create(['edition_team_id' => $otherTeam->id, 'player_registration_id' => $already->id, 'sold_amount' => 900]);
        $inactive = $this->registration([], ['is_active' => false]);
        $foreign = PlayerRegistration::factory()->create(); // another season

        $this->actingAs($this->admin)
            ->post($this->addUrl(), [
                'selected' => [$one->id, $two->id, $already->id, $inactive->id, $foreign->id],
                'edition_team_id' => $this->team->id,
                'team' => 'none',
                'payment_status' => 'paid',
            ])
            ->assertRedirect($this->indexUrl(['payment_status' => 'paid', 'team' => 'none']))
            ->assertSessionHas('success', "2 players added to {$this->team->team->name}. 3 were skipped (already in a team, inactive, or not registered for this season).");

        $rows = TeamPlayer::where('edition_team_id', $this->team->id)->get()->keyBy('player_registration_id');
        $this->assertSame([$one->id, $two->id], $rows->keys()->sort()->values()->all());
        $this->assertNull($rows[$one->id]->sold_amount);
        $this->assertNull($rows[$two->id]->sold_amount);
        // The player who was already bought is untouched.
        $this->assertSame($otherTeam->id, $already->fresh()->teamPlayer->edition_team_id);
        $this->assertSame('900.00', $already->fresh()->teamPlayer->sold_amount);
        $this->assertNull($foreign->fresh()->teamPlayer);
    }

    public function test_nothing_gets_added_when_every_ticked_player_is_unfit(): void
    {
        $already = $this->registration();
        TeamPlayer::factory()->create(['edition_team_id' => $this->team->id, 'player_registration_id' => $already->id]);

        $this->actingAs($this->admin)
            ->post($this->addUrl(), ['selected' => [$already->id], 'edition_team_id' => $this->team->id])
            ->assertSessionHas('error');

        $this->assertSame(1, TeamPlayer::count());
    }

    public function test_a_team_of_another_season_an_empty_selection_and_a_missing_team_are_refused(): void
    {
        $registration = $this->registration();
        $foreignTeam = EditionTeam::factory()->create();

        $this->actingAs($this->admin)
            ->post($this->addUrl(), ['selected' => [$registration->id], 'edition_team_id' => $foreignTeam->id])
            ->assertNotFound();
        $this->actingAs($this->admin)
            ->post($this->addUrl(), ['edition_team_id' => $this->team->id])
            ->assertSessionHas('error', 'Tick at least one player to add.');
        $this->actingAs($this->admin)
            ->post($this->addUrl(), ['selected' => [$registration->id]])
            ->assertSessionHas('error', 'Choose the team to add the players to.');

        $this->assertSame(0, TeamPlayer::count());
    }

    public function test_a_completed_season_cannot_get_players_added_and_hides_the_tick_boxes(): void
    {
        $registration = $this->registration([], ['name' => 'Late Player']);
        $this->edition->update(['status' => 'completed']);

        $html = $this->actingAs($this->admin)->get($this->indexUrl())->assertOk()->getContent();
        $this->assertStringContainsString('Late Player', $html);
        $this->assertStringNotContainsString('name="selected[]"', $html);
        $this->assertStringContainsString('This season is completed', $html);

        $this->actingAs($this->admin)
            ->post($this->addUrl(), ['selected' => [$registration->id], 'edition_team_id' => $this->team->id])
            ->assertSessionHas('error');

        $this->assertSame(0, TeamPlayer::count());
    }
}
