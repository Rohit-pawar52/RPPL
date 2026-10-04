<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The squads of a season, managed from inside the season: the overview,
 * a team's squad page, bulk add with sold amounts, in-list editing,
 * removal, and adding a player who registered offline.
 */
class SeasonSquadTest extends TestCase
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
        $this->edition = Edition::factory()->create(['status' => 'upcoming', 'registration_fee' => 300]);
        $this->team = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
    }

    private function registration(array $overrides = [], array $player = []): PlayerRegistration
    {
        return PlayerRegistration::factory()->create(array_merge([
            'edition_id' => $this->edition->id,
            'player_id' => Player::factory()->create($player)->id,
        ], $overrides));
    }

    private function url(string $name, array $extra = []): string
    {
        return route($name, array_merge([$this->edition, $this->team], $extra));
    }

    // ----- Overview and access -----

    public function test_the_overview_lists_each_team_with_its_players_and_total_spend_and_the_players_without_a_team(): void
    {
        $other = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
        TeamPlayer::factory()->create(['edition_team_id' => $this->team->id, 'player_registration_id' => $this->registration()->id, 'jersey_number' => 1, 'sold_amount' => 1500]);
        TeamPlayer::factory()->create(['edition_team_id' => $this->team->id, 'player_registration_id' => $this->registration()->id, 'jersey_number' => 2, 'sold_amount' => 2500]);
        $this->registration(); // not in any team
        $this->registration();

        $html = $this->actingAs($this->admin)->get(route('admin.editions.squads.index', $this->edition))->assertOk()->getContent();

        $this->assertStringContainsString($this->team->team->name, $html);
        $this->assertStringContainsString($other->team->name, $html);
        // Points, not rupees: a whole number, lakh grouping, no currency symbol.
        $this->assertStringContainsString('4,000 pts', $html);
        $this->assertStringNotContainsString('₹4,000', $html);
        $this->assertMatchesRegularExpression('#<span class="font-medium text-slate-700">2</span>\s+players of this season not in any team#', $html);
        $this->assertStringContainsString(route('admin.editions.squads.show', [$this->edition, $this->team]), $html);
    }

    public function test_only_an_admin_can_use_the_squad_pages(): void
    {
        $registration = $this->registration();

        foreach ([
            fn () => $this->get(route('admin.editions.squads.index', $this->edition)),
            fn () => $this->get($this->url('admin.editions.squads.show')),
            fn () => $this->post($this->url('admin.editions.squads.store'), ['add' => [$registration->id => 1]]),
            fn () => $this->put($this->url('admin.editions.squads.update'), ['players' => [1 => ['jersey_number' => 3]]]),
        ] as $request) {
            $this->actingAs($this->scorer);
            $request()->assertForbidden();
        }

        $this->assertSame(0, TeamPlayer::count());
    }

    public function test_a_team_of_another_season_is_not_found_through_this_seasons_url(): void
    {
        $foreign = EditionTeam::factory()->create(); // another edition

        $this->actingAs($this->admin)->get(route('admin.editions.squads.show', [$this->edition, $foreign]))->assertNotFound();
        $this->actingAs($this->admin)->post(route('admin.editions.squads.store', [$this->edition, $foreign]), ['add' => [1 => 1]])->assertNotFound();
    }

    // ----- Add players (the auction result) -----

    public function test_the_add_list_offers_only_this_seasons_players_who_are_not_in_a_team(): void
    {
        $free = $this->registration([], ['name' => 'Free Agent']);
        $taken = $this->registration([], ['name' => 'Already Bought']);
        TeamPlayer::factory()->create(['edition_team_id' => $this->team->id, 'player_registration_id' => $taken->id]);
        PlayerRegistration::factory()->create(['player_id' => Player::factory()->create(['name' => 'Other Season Player'])->id]);
        $this->registration([], ['name' => 'Inactive Person', 'is_active' => false]);

        $html = $this->actingAs($this->admin)->get($this->url('admin.editions.squads.show'))->assertOk()->getContent();

        $this->assertStringContainsString('name="add['.$free->id.']"', $html);
        $this->assertStringNotContainsString('name="add['.$taken->id.']"', $html);
        $this->assertStringNotContainsString('Other Season Player', $html);
        $this->assertStringNotContainsString('Inactive Person', $html);
        // Already in the squad table instead.
        $this->assertStringContainsString('Already Bought', $html);
    }

    public function test_many_players_are_added_at_once_with_optional_amounts_and_unfit_ones_are_skipped(): void
    {
        $one = $this->registration();
        $two = $this->registration();
        $three = $this->registration();
        $already = $this->registration();
        TeamPlayer::factory()->create(['edition_team_id' => EditionTeam::factory()->create(['edition_id' => $this->edition->id]), 'player_registration_id' => $already->id]);
        $foreign = PlayerRegistration::factory()->create(); // another season

        $this->actingAs($this->admin)
            ->post($this->url('admin.editions.squads.store'), [
                'add' => [$one->id => 1, $two->id => 1, $three->id => 1, $already->id => 1, $foreign->id => 1],
                'amount' => [$one->id => '1500', $two->id => '', $already->id => '999'],
            ])
            ->assertRedirect($this->url('admin.editions.squads.show'))
            ->assertSessionHas('success', '3 players added to the squad.');

        $rows = TeamPlayer::where('edition_team_id', $this->team->id)->get()->keyBy('player_registration_id');
        $this->assertCount(3, $rows);
        $this->assertSame('1500.00', $rows[$one->id]->sold_amount);
        $this->assertNull($rows[$two->id]->sold_amount);
        $this->assertNull($rows[$three->id]->sold_amount);
        $this->assertNull(TeamPlayer::where('player_registration_id', $foreign->id)->first());
    }

    public function test_nothing_ticked_or_a_bad_amount_is_rejected(): void
    {
        $registration = $this->registration();

        $this->actingAs($this->admin)->post($this->url('admin.editions.squads.store'), [])
            ->assertSessionHasErrors(['add' => 'Tick at least one player to add.']);
        $this->actingAs($this->admin)->post($this->url('admin.editions.squads.store'), ['add' => [$registration->id => 1], 'amount' => [$registration->id => 'lots']])
            ->assertSessionHasErrors('amount.'.$registration->id);
        $this->actingAs($this->admin)->post($this->url('admin.editions.squads.store'), ['add' => [$registration->id => 1], 'amount' => [$registration->id => '-5']])
            ->assertSessionHasErrors('amount.'.$registration->id);

        $this->assertSame(0, TeamPlayer::count());
    }

    public function test_a_completed_season_cannot_get_new_squad_players_but_can_still_be_corrected(): void
    {
        $this->edition->update(['status' => 'completed']);
        $registration = $this->registration();
        $inSquad = TeamPlayer::factory()->create(['edition_team_id' => $this->team->id, 'player_registration_id' => $this->registration()->id, 'jersey_number' => 4]);

        $this->actingAs($this->admin)->post($this->url('admin.editions.squads.store'), ['add' => [$registration->id => 1]])
            ->assertSessionHas('error');
        $this->actingAs($this->admin)->post($this->url('admin.editions.squads.store-offline'), ['name' => 'Late Joiner', 'phone' => '9876501234'])
            ->assertSessionHas('error');
        $this->assertSame(1, TeamPlayer::count());

        $this->actingAs($this->admin)->put($this->url('admin.editions.squads.update'), ['players' => [$inSquad->id => ['jersey_number' => 9, 'role' => 'bowler', 'sold_amount' => '700']]])
            ->assertSessionHas('success');
        $this->assertSame(9, $inSquad->fresh()->jersey_number);
    }

    // ----- Editing the squad in the list -----

    public function test_jersey_role_and_amount_are_saved_for_the_whole_squad_and_blank_means_none(): void
    {
        $a = TeamPlayer::factory()->create(['edition_team_id' => $this->team->id, 'player_registration_id' => $this->registration()->id, 'jersey_number' => 5, 'role' => 'batter', 'sold_amount' => 100]);
        $b = TeamPlayer::factory()->create(['edition_team_id' => $this->team->id, 'player_registration_id' => $this->registration()->id, 'jersey_number' => 6, 'role' => 'bowler', 'sold_amount' => null]);

        $this->actingAs($this->admin)->put($this->url('admin.editions.squads.update'), ['players' => [
            // A swap of two jersey numbers (they are unique within a team).
            $a->id => ['jersey_number' => '6', 'role' => 'all_rounder', 'sold_amount' => '2500.50'],
            $b->id => ['jersey_number' => '5', 'role' => '', 'sold_amount' => ''],
        ]])->assertRedirect($this->url('admin.editions.squads.show'))->assertSessionHas('success', 'Squad saved.');

        $a->refresh();
        $b->refresh();
        $this->assertSame([6, 'all_rounder', '2500.50'], [$a->jersey_number, $a->role, $a->sold_amount]);
        $this->assertSame([5, null, null], [$b->jersey_number, $b->role, $b->sold_amount]);
    }

    public function test_duplicate_jersey_numbers_a_bad_role_and_foreign_rows_are_refused(): void
    {
        $a = TeamPlayer::factory()->create(['edition_team_id' => $this->team->id, 'player_registration_id' => $this->registration()->id, 'jersey_number' => 5]);
        $b = TeamPlayer::factory()->create(['edition_team_id' => $this->team->id, 'player_registration_id' => $this->registration()->id, 'jersey_number' => 6]);
        $foreign = TeamPlayer::factory()->create(['jersey_number' => 77]);

        $this->actingAs($this->admin)->put($this->url('admin.editions.squads.update'), ['players' => [
            $a->id => ['jersey_number' => '8'], $b->id => ['jersey_number' => '8'],
        ]])->assertSessionHasErrors('players');

        $this->actingAs($this->admin)->put($this->url('admin.editions.squads.update'), ['players' => [$a->id => ['role' => 'umpire']]])
            ->assertSessionHasErrors('players.'.$a->id.'.role');

        // A row of another team is ignored, never edited through this team's page.
        $this->actingAs($this->admin)->put($this->url('admin.editions.squads.update'), ['players' => [$foreign->id => ['jersey_number' => '1']]])
            ->assertSessionHasNoErrors();

        $this->assertSame([5, 6, 77], [$a->fresh()->jersey_number, $b->fresh()->jersey_number, $foreign->fresh()->jersey_number]);
    }

    // ----- Removing a player -----

    public function test_a_player_can_be_removed_from_the_squad_but_not_once_they_have_played(): void
    {
        $registration = $this->registration();
        $removable = TeamPlayer::factory()->create(['edition_team_id' => $this->team->id, 'player_registration_id' => $registration->id]);

        $played = TeamPlayer::factory()->create(['edition_team_id' => $this->team->id, 'player_registration_id' => $this->registration()->id]);
        $opponent = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
        $match = GameMatch::factory()->create(['edition_id' => $this->edition->id, 'edition_team_a_id' => $this->team->id, 'edition_team_b_id' => $opponent->id]);
        MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $played->id]);

        $page = $this->actingAs($this->admin)->get($this->url('admin.editions.squads.show'))->getContent();
        $this->assertStringContainsString('remove-player-'.$removable->id, $page);
        $this->assertStringNotContainsString('remove-player-'.$played->id, $page);

        $this->actingAs($this->admin)->delete($this->url('admin.editions.squads.destroy', ['team_player' => $removable]))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('team_players', ['id' => $removable->id]);
        $this->assertDatabaseHas('player_registrations', ['id' => $registration->id]);

        $this->actingAs($this->admin)->delete($this->url('admin.editions.squads.destroy', ['team_player' => $played]))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('team_players', ['id' => $played->id]);
    }

    public function test_a_squad_row_of_another_team_cannot_be_removed_through_this_teams_url(): void
    {
        $foreign = TeamPlayer::factory()->create();

        $this->actingAs($this->admin)->delete($this->url('admin.editions.squads.destroy', ['team_player' => $foreign]))->assertNotFound();
        $this->assertDatabaseHas('team_players', ['id' => $foreign->id]);
    }

    // ----- A player who registered offline -----

    public function test_an_offline_player_gets_a_player_a_paid_registration_and_a_squad_place_in_one_step(): void
    {
        $this->actingAs($this->admin)->post($this->url('admin.editions.squads.store-offline'), [
            'name' => 'Walk-in Wicketkeeper', 'phone' => '+91 98765 01234', 'sold_amount' => '3000',
        ])->assertRedirect($this->url('admin.editions.squads.show'))->assertSessionHas('success');

        $player = Player::firstWhere('phone', '9876501234');
        $this->assertSame('Walk-in Wicketkeeper', $player->name);

        $registration = PlayerRegistration::where('player_id', $player->id)->sole();
        $this->assertSame($this->edition->id, $registration->edition_id);
        $this->assertSame('paid', $registration->payment_status);
        $this->assertSame('300.00', $registration->registration_fee);
        $this->assertMatchesRegularExpression('/^RPPL-\d{4}-\d{6}$/', $registration->registration_number);

        $squad = TeamPlayer::where('player_registration_id', $registration->id)->sole();
        $this->assertSame($this->team->id, $squad->edition_team_id);
        $this->assertSame('3000.00', $squad->sold_amount);
    }

    public function test_an_offline_player_who_already_has_a_player_record_reuses_it_without_editing_it(): void
    {
        $existing = Player::factory()->create(['name' => 'Old Name', 'phone' => '9876501234', 'primary_role' => 'bowler']);

        $this->actingAs($this->admin)->post($this->url('admin.editions.squads.store-offline'), ['name' => 'New Spelling', 'phone' => '9876501234', 'payment_status' => 'pending'])
            ->assertSessionHas('success');

        $this->assertSame(1, Player::where('phone', '9876501234')->count());
        $this->assertSame(['Old Name', 'bowler'], [$existing->fresh()->name, $existing->fresh()->primary_role]);
        $this->assertSame('pending', PlayerRegistration::where('player_id', $existing->id)->sole()->payment_status);
    }

    public function test_an_offline_player_already_registered_this_season_or_inactive_is_refused_in_its_own_error_bag(): void
    {
        $registered = $this->registration([], ['name' => 'Already Here', 'phone' => '9876501234']);
        $inactive = Player::factory()->create(['phone' => '9123456780', 'is_active' => false]);

        $this->actingAs($this->admin)->post($this->url('admin.editions.squads.store-offline'), ['name' => 'X', 'phone' => '9876501234'])
            ->assertSessionHasErrorsIn('offlinePlayer', 'phone');
        $this->actingAs($this->admin)->post($this->url('admin.editions.squads.store-offline'), ['name' => 'X', 'phone' => '9123456780'])
            ->assertSessionHasErrorsIn('offlinePlayer', 'phone');
        $this->actingAs($this->admin)->post($this->url('admin.editions.squads.store-offline'), ['name' => '', 'phone' => '12345'])
            ->assertSessionHasErrorsIn('offlinePlayer', ['name', 'phone']);

        $this->assertSame(0, TeamPlayer::count());
        $this->assertSame(1, PlayerRegistration::where('player_id', $registered->player_id)->count());
        $this->assertSame(0, PlayerRegistration::where('player_id', $inactive->id)->count());
    }
}
