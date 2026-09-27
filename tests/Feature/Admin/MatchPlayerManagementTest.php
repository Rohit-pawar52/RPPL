<?php

namespace Tests\Feature\Admin;

use App\Models\Delivery;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\MatchPlayer\MatchPlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Bulk Playing XI selection (replaces the old one-player-at-a-time Add/
 * Remove workflow — see MatchPlayerController::sync()/MatchPlayerService::
 * syncPlayingXi()). Captain/wicket-keeper assignment (update()) is
 * unchanged by this pass and keeps its existing coverage.
 */
class MatchPlayerManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $scorerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->scorerRole = Role::create(['name' => 'Scorer', 'slug' => 'scorer']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    private function scorer(): User
    {
        return User::factory()->create(['role_id' => $this->scorerRole->id]);
    }

    /**
     * A scheduled GameMatch with its two participating EditionTeams
     * under the same Edition, exactly as GameMatchFactory builds it.
     *
     * @return array{0: GameMatch, 1: EditionTeam, 2: EditionTeam}
     */
    private function matchWithTeams(array $matchAttributes = []): array
    {
        $match = GameMatch::factory()->create(array_merge(['match_status' => 'scheduled'], $matchAttributes));

        return [$match, $match->teamA, $match->teamB];
    }

    /**
     * A TeamPlayer belonging to the given EditionTeam, with an active
     * underlying Player.
     */
    private function teamPlayerFor(EditionTeam $editionTeam): TeamPlayer
    {
        return TeamPlayer::factory()->create(['edition_team_id' => $editionTeam->id]);
    }

    /**
     * 11 fresh, active TeamPlayers squadded to the given EditionTeam —
     * a minimal valid Playing XI submission for that team.
     *
     * @return Collection<int, TeamPlayer>
     */
    private function elevenTeamPlayersFor(EditionTeam $editionTeam): Collection
    {
        return collect(range(1, 11))->map(fn () => $this->teamPlayerFor($editionTeam));
    }

    private function sync(GameMatch $match, EditionTeam $editionTeam, Collection|array $teamPlayers, ?User $user = null)
    {
        $ids = $teamPlayers instanceof Collection ? $teamPlayers->pluck('id')->all() : $teamPlayers;

        return $this->actingAs($user ?? $this->admin())->post(route('admin.matches.players.sync', $match), [
            'edition_team_id' => $editionTeam->id,
            'team_player_ids' => $ids,
        ]);
    }

    // ----- Authorization -----

    public function test_guest_is_blocked(): void
    {
        [$match] = $this->matchWithTeams();

        $this->get(route('admin.matches.players.index', $match))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_and_scorer_both_have_full_management_access(): void
    {
        [$match, $teamA] = $this->matchWithTeams();

        foreach ([$this->admin(), $this->scorer()] as $user) {
            $this->actingAs($user)
                ->get(route('admin.matches.players.index', $match))
                ->assertOk();

            $eleven = $this->elevenTeamPlayersFor($teamA);

            $this->sync($match, $teamA, $eleven, $user)
                ->assertRedirect(route('admin.matches.players.index', $match))
                ->assertSessionDoesntHaveErrors();

            $matchPlayer = MatchPlayer::where('match_id', $match->id)->where('team_player_id', $eleven->first()->id)->firstOrFail();

            $this->actingAs($user)
                ->patch(route('admin.matches.players.update', [$match, $matchPlayer]), ['designation' => 'captain'])
                ->assertRedirect(route('admin.matches.players.index', $match));
        }
    }

    // ----- Exactly-11 bulk validation -----

    public function test_exactly_eleven_is_accepted(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $eleven = $this->elevenTeamPlayersFor($teamA);

        $this->sync($match, $teamA, $eleven)
            ->assertRedirect(route('admin.matches.players.index', $match))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(11, MatchPlayer::where('match_id', $match->id)->count());

        foreach ($eleven as $teamPlayer) {
            $this->assertDatabaseHas('match_players', ['match_id' => $match->id, 'team_player_id' => $teamPlayer->id]);
        }
    }

    public function test_fewer_than_eleven_is_rejected(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $ten = collect(range(1, 10))->map(fn () => $this->teamPlayerFor($teamA));

        $this->sync($match, $teamA, $ten)->assertSessionHasErrors('team_player_ids');

        $this->assertSame(0, MatchPlayer::where('match_id', $match->id)->count());
    }

    public function test_more_than_eleven_is_rejected(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $twelve = collect(range(1, 12))->map(fn () => $this->teamPlayerFor($teamA));

        $this->sync($match, $teamA, $twelve)->assertSessionHasErrors('team_player_ids');

        $this->assertSame(0, MatchPlayer::where('match_id', $match->id)->count());
    }

    public function test_duplicate_player_in_the_same_submission_is_rejected(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $ten = collect(range(1, 10))->map(fn () => $this->teamPlayerFor($teamA));
        $ids = $ten->pluck('id')->push($ten->first()->id)->all(); // 11 entries, one duplicated

        $this->sync($match, $teamA, $ids)->assertSessionHasErrors('team_player_ids.10');

        $this->assertSame(0, MatchPlayer::where('match_id', $match->id)->count());
    }

    public function test_player_outside_the_squad_is_rejected(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $ten = $this->elevenTeamPlayersFor($teamA)->take(10);
        $outsider = TeamPlayer::factory()->create(); // a different edition_team entirely

        $ids = $ten->pluck('id')->push($outsider->id)->all();

        $this->sync($match, $teamA, $ids)->assertSessionHasErrors();

        $this->assertSame(0, MatchPlayer::where('match_id', $match->id)->count());
    }

    public function test_player_from_wrong_edition_is_rejected_even_if_team_names_collide(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $ten = $this->elevenTeamPlayersFor($teamA)->take(10);

        $otherEdition = Edition::factory()->create();
        $otherEditionTeam = EditionTeam::factory()->create([
            'edition_id' => $otherEdition->id,
            'team_id' => $teamA->team_id,
        ]);
        $wrongEditionPlayer = $this->teamPlayerFor($otherEditionTeam);

        $ids = $ten->pluck('id')->push($wrongEditionPlayer->id)->all();

        $this->sync($match, $teamA, $ids)->assertSessionHasErrors();

        $this->assertSame(0, MatchPlayer::where('match_id', $match->id)->count());
    }

    public function test_edition_team_not_belonging_to_this_match_is_rejected(): void
    {
        [$match] = $this->matchWithTeams();
        $unrelatedTeam = EditionTeam::factory()->create();
        $eleven = $this->elevenTeamPlayersFor($unrelatedTeam);

        $this->sync($match, $unrelatedTeam, $eleven)->assertSessionHasErrors('edition_team_id');

        $this->assertSame(0, MatchPlayer::where('match_id', $match->id)->count());
    }

    public function test_inactive_player_cannot_be_newly_selected(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $ten = $this->elevenTeamPlayersFor($teamA)->take(10);

        $inactivePlayer = Player::factory()->inactive()->create();
        $registration = PlayerRegistration::factory()->create(['edition_id' => $match->edition_id, 'player_id' => $inactivePlayer->id]);
        $inactiveTeamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $teamA->id, 'player_registration_id' => $registration->id]);

        $ids = $ten->pluck('id')->push($inactiveTeamPlayer->id)->all();

        $this->sync($match, $teamA, $ids)->assertSessionHasErrors();

        $this->assertSame(0, MatchPlayer::where('match_id', $match->id)->count());
    }

    /**
     * A player already selected keeps their place even if they became
     * inactive afterward — only a NEWLY added player must currently be
     * active. Re-submitting the exact same 11 (including the now-
     * inactive one) must succeed.
     */
    public function test_already_selected_player_can_stay_selected_after_becoming_inactive(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $eleven = $this->elevenTeamPlayersFor($teamA);
        $this->sync($match, $teamA, $eleven)->assertSessionDoesntHaveErrors();

        $playerToDeactivate = $eleven->first()->playerRegistration->player;
        $playerToDeactivate->update(['is_active' => false]);

        $this->sync($match, $teamA, $eleven)->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('match_players', ['match_id' => $match->id, 'team_player_id' => $eleven->first()->id]);
    }

    // ----- Team isolation -----

    public function test_saving_one_team_does_not_alter_the_other_team(): void
    {
        [$match, $teamA, $teamB] = $this->matchWithTeams();
        $elevenA = $this->elevenTeamPlayersFor($teamA);
        $elevenB = $this->elevenTeamPlayersFor($teamB);

        $this->sync($match, $teamA, $elevenA)->assertSessionDoesntHaveErrors();
        $this->sync($match, $teamB, $elevenB)->assertSessionDoesntHaveErrors();

        $this->assertSame(11, MatchPlayer::query()->whereHas('teamPlayer', fn ($q) => $q->where('edition_team_id', $teamA->id))->count());
        $this->assertSame(11, MatchPlayer::query()->whereHas('teamPlayer', fn ($q) => $q->where('edition_team_id', $teamB->id))->count());

        // Re-saving team A alone must never touch team B's rows.
        $newElevenA = $this->elevenTeamPlayersFor($teamA);
        $this->sync($match, $teamA, $newElevenA)->assertSessionDoesntHaveErrors();

        foreach ($elevenB as $teamPlayer) {
            $this->assertDatabaseHas('match_players', ['match_id' => $match->id, 'team_player_id' => $teamPlayer->id]);
        }
    }

    // ----- Editing an existing XI -----

    public function test_existing_xi_can_be_replaced_before_lock(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $original = $this->elevenTeamPlayersFor($teamA);
        $this->sync($match, $teamA, $original)->assertSessionDoesntHaveErrors();

        // Swap out 3 players for 3 new ones.
        $replacement = $original->slice(0, 8)->values()->merge($this->elevenTeamPlayersFor($teamA)->take(3));
        $this->sync($match, $teamA, $replacement)->assertSessionDoesntHaveErrors();

        $this->assertSame(11, MatchPlayer::query()->whereHas('teamPlayer', fn ($q) => $q->where('edition_team_id', $teamA->id))->count());

        foreach ($original->slice(8) as $droppedPlayer) {
            $this->assertDatabaseMissing('match_players', ['match_id' => $match->id, 'team_player_id' => $droppedPlayer->id]);
        }

        foreach ($replacement as $teamPlayer) {
            $this->assertDatabaseHas('match_players', ['match_id' => $match->id, 'team_player_id' => $teamPlayer->id]);
        }
    }

    /**
     * Re-saving a selection that keeps an already-selected player must
     * never disturb their captain/wicket-keeper designation — proves
     * syncPlayingXi() updates only the genuinely added/removed rows.
     */
    public function test_replacing_the_xi_preserves_captaincy_for_players_who_remain_selected(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $original = $this->elevenTeamPlayersFor($teamA);
        $this->sync($match, $teamA, $original)->assertSessionDoesntHaveErrors();

        $captainMatchPlayer = MatchPlayer::where('match_id', $match->id)->where('team_player_id', $original->first()->id)->firstOrFail();
        $this->actingAs($this->admin())->patch(route('admin.matches.players.update', [$match, $captainMatchPlayer]), ['designation' => 'captain']);

        // Replace only the last player; the captain stays in the selection.
        $replacement = $original->slice(0, 10)->values()->push($this->teamPlayerFor($teamA));
        $this->sync($match, $teamA, $replacement)->assertSessionDoesntHaveErrors();

        $this->assertTrue($captainMatchPlayer->fresh()->is_captain);
    }

    // ----- Lifecycle lock -----

    public function test_playing_xi_is_editable_while_scheduled(): void
    {
        [$match, $teamA] = $this->matchWithTeams(['match_status' => 'scheduled']);
        $eleven = $this->elevenTeamPlayersFor($teamA);

        $this->sync($match, $teamA, $eleven)->assertSessionDoesntHaveErrors();

        $this->assertSame(11, MatchPlayer::where('match_id', $match->id)->count());
    }

    public function test_playing_xi_is_editable_while_toss(): void
    {
        [$match, $teamA] = $this->matchWithTeams(['match_status' => 'toss']);
        $eleven = $this->elevenTeamPlayersFor($teamA);

        $this->sync($match, $teamA, $eleven)->assertSessionDoesntHaveErrors();

        $this->assertSame(11, MatchPlayer::where('match_id', $match->id)->count());
    }

    public function test_playing_xi_is_locked_once_live(): void
    {
        [$match, $teamA] = $this->matchWithTeams(['match_status' => 'live']);
        $eleven = $this->elevenTeamPlayersFor($teamA);

        $this->sync($match, $teamA, $eleven)
            ->assertRedirect(route('admin.matches.players.index', $match))
            ->assertSessionHas('error');

        $this->assertSame(0, MatchPlayer::where('match_id', $match->id)->count());
    }

    public function test_playing_xi_is_locked_once_completed(): void
    {
        [$match, $teamA] = $this->matchWithTeams(['match_status' => 'completed']);
        $eleven = $this->elevenTeamPlayersFor($teamA);

        $this->sync($match, $teamA, $eleven);

        $this->assertSame(0, MatchPlayer::where('match_id', $match->id)->count());
    }

    public function test_playing_xi_is_locked_for_abandoned_and_cancelled_matches(): void
    {
        foreach (['abandoned', 'cancelled'] as $status) {
            [$match, $teamA] = $this->matchWithTeams(['match_status' => $status]);
            $eleven = $this->elevenTeamPlayersFor($teamA);

            $this->sync($match, $teamA, $eleven);

            $this->assertSame(0, MatchPlayer::where('match_id', $match->id)->count());
        }
    }

    public function test_playing_xi_stays_locked_when_innings_exist_despite_stale_scheduled_status(): void
    {
        [$match, $teamA, $teamB] = $this->matchWithTeams(['match_status' => 'scheduled']);
        Innings::factory()->create([
            'match_id' => $match->id,
            'batting_team_id' => $teamA->id,
            'bowling_team_id' => $teamB->id,
        ]);
        $eleven = $this->elevenTeamPlayersFor($teamA);

        $this->sync($match, $teamA, $eleven);

        $this->assertSame(0, MatchPlayer::where('match_id', $match->id)->count());
    }

    /**
     * A genuinely locked match must render the Playing XI read-only —
     * no selection controls exposed for a state that can't be saved
     * anyway.
     */
    public function test_locked_match_shows_no_selection_controls(): void
    {
        [$match, $teamA] = $this->matchWithTeams(['match_status' => 'live']);
        $matchPlayer = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamA)->id]);

        $response = $this->actingAs($this->admin())->get(route('admin.matches.players.index', $match));

        $response->assertOk();
        $response->assertDontSee('Save Playing XI');
        $response->assertDontSee('Auto Select 11');
        $response->assertSee($matchPlayer->teamPlayer->playerRegistration->player->name);
    }

    // ----- Atomicity -----

    /**
     * A submission that fails validation partway through (a duplicate
     * mixed in with otherwise-valid players) must leave NO trace — not
     * even the valid players from that same submission.
     */
    public function test_a_rejected_bulk_save_leaves_no_partial_xi(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $ten = $this->elevenTeamPlayersFor($teamA)->take(10);
        $ids = $ten->pluck('id')->push($ten->first()->id)->all();

        $this->sync($match, $teamA, $ids)->assertSessionHasErrors();

        $this->assertSame(0, MatchPlayer::where('match_id', $match->id)->count());
    }

    /**
     * The structural, service-level guard (not just the FormRequest)
     * also refuses anything other than exactly 11 — defense-in-depth
     * against a future caller skipping SyncMatchPlayersRequest.
     */
    public function test_service_rejects_a_non_eleven_count_even_bypassing_the_form_request(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $ten = $this->elevenTeamPlayersFor($teamA)->take(10);

        $this->expectException(ValidationException::class);

        app(MatchPlayerService::class)->syncPlayingXi($match, $teamA, $ten->pluck('id')->all());
    }

    // ----- Captain / wicketkeeper, team-isolated (unchanged by this pass) -----

    public function test_assigning_captain_unsets_previous_captain_on_same_team_only(): void
    {
        [$match, $teamA, $teamB] = $this->matchWithTeams();
        $playerA1 = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamA)->id, 'is_captain' => true]);
        $playerA2 = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamA)->id]);
        $playerB1 = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamB)->id, 'is_captain' => true]);

        $this->actingAs($this->admin())
            ->patch(route('admin.matches.players.update', [$match, $playerA2]), ['designation' => 'captain'])
            ->assertRedirect(route('admin.matches.players.index', $match));

        $this->assertFalse($playerA1->fresh()->is_captain);
        $this->assertTrue($playerA2->fresh()->is_captain);
        $this->assertTrue($playerB1->fresh()->is_captain, 'The other team\'s captain must be untouched.');
    }

    public function test_assigning_wicket_keeper_unsets_previous_wicket_keeper_on_same_team_only(): void
    {
        [$match, $teamA, $teamB] = $this->matchWithTeams();
        $playerA1 = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamA)->id, 'is_wicket_keeper' => true]);
        $playerA2 = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamA)->id]);
        $playerB1 = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamB)->id, 'is_wicket_keeper' => true]);

        $this->actingAs($this->admin())
            ->patch(route('admin.matches.players.update', [$match, $playerA2]), ['designation' => 'wicket_keeper'])
            ->assertRedirect(route('admin.matches.players.index', $match));

        $this->assertFalse($playerA1->fresh()->is_wicket_keeper);
        $this->assertTrue($playerA2->fresh()->is_wicket_keeper);
        $this->assertTrue($playerB1->fresh()->is_wicket_keeper, 'The other team\'s wicketkeeper must be untouched.');
    }

    public function test_a_player_can_be_both_captain_and_wicket_keeper(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $matchPlayer = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamA)->id]);

        $admin = $this->admin();
        $this->actingAs($admin)->patch(route('admin.matches.players.update', [$match, $matchPlayer]), ['designation' => 'captain']);
        $this->actingAs($admin)->patch(route('admin.matches.players.update', [$match, $matchPlayer]), ['designation' => 'wicket_keeper']);

        $matchPlayer->refresh();
        $this->assertTrue($matchPlayer->is_captain);
        $this->assertTrue($matchPlayer->is_wicket_keeper);
    }

    public function test_team_player_role_does_not_auto_assign_wicket_keeper_designation(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $teamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $teamA->id, 'role' => 'wicket_keeper']);

        $this->sync($match, $teamA, collect([$teamPlayer])->merge($this->elevenTeamPlayersFor($teamA)->take(10)));

        $matchPlayer = MatchPlayer::where('team_player_id', $teamPlayer->id)->firstOrFail();
        $this->assertFalse($matchPlayer->is_wicket_keeper);
    }

    // ----- Delivery history is never disturbed by a sync -----

    public function test_a_player_with_scoring_history_is_never_removed_by_a_sync(): void
    {
        [$match, $teamA, $teamB] = $this->matchWithTeams();
        $striker = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamA)->id]);
        $nonStriker = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamA)->id]);
        $bowler = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamB)->id]);

        $innings = Innings::factory()->create([
            'match_id' => $match->id,
            'batting_team_id' => $teamA->id,
            'bowling_team_id' => $teamB->id,
        ]);

        Delivery::create([
            'innings_id' => $innings->id,
            'delivery_sequence' => 1,
            'over_number' => 0,
            'striker_match_player_id' => $striker->id,
            'non_striker_match_player_id' => $nonStriker->id,
            'bowler_match_player_id' => $bowler->id,
        ]);

        // canModifyPlayingXI() is already false here because an Innings
        // exists — this proves the request is refused at that gate
        // before ever reaching the service's own deeper safety check.
        $eleven = $this->elevenTeamPlayersFor($teamA);
        $this->sync($match, $teamA, $eleven)->assertSessionHas('error');

        $this->assertDatabaseHas('match_players', ['id' => $striker->id]);
    }

    // ----- UI -----

    public function test_index_shows_selected_counts_and_no_contact_info(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $player = Player::factory()->create(['phone' => '9998887776', 'email' => 'secret@example.com']);
        $registration = PlayerRegistration::factory()->create(['edition_id' => $match->edition_id, 'player_id' => $player->id]);
        $teamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $teamA->id, 'player_registration_id' => $registration->id]);
        MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $teamPlayer->id]);

        $response = $this->actingAs($this->admin())->get(route('admin.matches.players.index', $match));

        $response->assertOk();
        $response->assertSee('1 / 11 selected');
        $response->assertDontSee('9998887776');
        $response->assertDontSee('secret@example.com');
    }

    /**
     * When a Playing XI already exists, the squad checklist must load
     * with exactly those players pre-checked — never the deterministic
     * auto-select fallback, which only applies when nothing is saved
     * yet.
     */
    public function test_existing_saved_xi_is_returned_selected_not_auto_selected(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        // 3 squad players that are NEVER selected — what a naive "first
        // 11" auto-select would have picked instead, if the page
        // ignored the existing saved XI.
        $unselected = collect(range(1, 3))->map(fn () => $this->teamPlayerFor($teamA));
        $saved = $this->elevenTeamPlayersFor($teamA);

        foreach ($saved as $teamPlayer) {
            MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $teamPlayer->id]);
        }

        $response = $this->actingAs($this->admin())->get(route('admin.matches.players.index', $match));
        $response->assertOk();

        foreach ($unselected as $teamPlayer) {
            $this->assertFalse($this->checkboxIsChecked($response->getContent(), $teamPlayer->id), "TeamPlayer {$teamPlayer->id} should not be pre-checked.");
        }

        foreach ($saved as $teamPlayer) {
            $this->assertTrue($this->checkboxIsChecked($response->getContent(), $teamPlayer->id), "TeamPlayer {$teamPlayer->id} should be pre-checked.");
        }
    }

    /**
     * Whether the rendered squad checkbox for this team_player_id is
     * checked — matched via regex rather than an exact-string
     * comparison, since the real markup spans multiple lines/attribute
     * order.
     */
    private function checkboxIsChecked(string $html, int $teamPlayerId): bool
    {
        if (! preg_match('/<input[^>]*value="'.$teamPlayerId.'"[^>]*\/>/s', $html, $matches)) {
            $this->fail("No checkbox found for team_player_id {$teamPlayerId}.");
        }

        return str_contains($matches[0], 'checked');
    }

    public function test_manage_playing_xi_link_visible_from_match_show_page(): void
    {
        [$match] = $this->matchWithTeams();

        $response = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));

        $response->assertOk();
        $response->assertSee(route('admin.matches.players.index', $match), false);
    }
}
