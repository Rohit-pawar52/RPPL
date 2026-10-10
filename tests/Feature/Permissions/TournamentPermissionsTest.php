<?php

namespace Tests\Feature\Permissions;

use App\Models\Delivery;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Models\Venue;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The tournament-setup and match policies decide by the permissions stored for the user's role, not by
 * the role's name: a custom role gets exactly what it is granted and nothing else, while the built-in
 * admin/scorer/auctioneer keep the abilities they had before roles became editable.
 */
class TournamentPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private int $customRoles = 0;

    /**
     * A user whose custom (not built-in) role holds exactly these permissions, plus access to the
     * admin panel unless told otherwise, since every request below goes through it.
     *
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions, bool $panelAccess = true): User
    {
        $n = ++$this->customRoles;

        $role = Role::create(['name' => "Custom {$n}", 'slug' => "custom-{$n}"]);
        $role->syncPermissions($panelAccess ? ['panel.access', ...$permissions] : $permissions);

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * A user in one of the built-in roles. The migrations already create the auctioneer role (with
     * its grants); admin and scorer are created here, where the model applies their default grants.
     */
    private function builtIn(string $slug): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * A match with 11 selected players per side, ready to have its toss started.
     */
    private function matchReadyForToss(): GameMatch
    {
        $match = GameMatch::factory()->create(['match_status' => 'scheduled']);

        foreach ([$match->edition_team_a_id, $match->edition_team_b_id] as $editionTeamId) {
            for ($i = 0; $i < 11; $i++) {
                MatchPlayer::factory()->create([
                    'match_id' => $match->id,
                    'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $editionTeamId])->id,
                ]);
            }
        }

        return $match->fresh();
    }

    /**
     * A live match (Team A batting) with three selected players per side and a live first innings.
     *
     * @return array{0: GameMatch, 1: Innings, 2: Collection<int, MatchPlayer>, 3: Collection<int, MatchPlayer>}
     */
    private function liveMatchWithInnings(): array
    {
        $match = GameMatch::factory()->create(['match_status' => 'live', 'started_at' => now(), 'overs_per_innings' => 20]);
        $match->update(['toss_winner_team_id' => $match->edition_team_a_id, 'toss_decision' => 'bat']);

        $squad = fn (int $editionTeamId) => collect(range(1, 3))->map(fn () => MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $editionTeamId])->id,
        ]));

        $batting = $squad($match->edition_team_a_id);
        $bowling = $squad($match->edition_team_b_id);

        $innings = Innings::create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $match->edition_team_a_id,
            'bowling_team_id' => $match->edition_team_b_id,
            'status' => 'live',
        ]);

        return [$match->fresh(), $innings->fresh(), $batting, $bowling];
    }

    /**
     * @param  Collection<int, MatchPlayer>  $batting
     * @param  Collection<int, MatchPlayer>  $bowling
     * @return array<string, int>
     */
    private function deliveryPayload(Collection $batting, Collection $bowling): array
    {
        return [
            'striker_match_player_id' => $batting[0]->id,
            'non_striker_match_player_id' => $batting[1]->id,
            'bowler_match_player_id' => $bowling[0]->id,
            'runs_off_bat' => 1,
        ];
    }

    /**
     * Both innings completed (Team A 150/8, Team B 140/10), i.e. ready to have its result finalized.
     */
    private function matchReadyToFinalize(): GameMatch
    {
        $match = GameMatch::factory()->create(['match_status' => 'live', 'started_at' => now()]);

        foreach ([[1, $match->edition_team_a_id, $match->edition_team_b_id, 150, 8], [2, $match->edition_team_b_id, $match->edition_team_a_id, 140, 10]] as [$number, $batting, $bowling, $runs, $wickets]) {
            Innings::create([
                'match_id' => $match->id,
                'innings_number' => $number,
                'batting_team_id' => $batting,
                'bowling_team_id' => $bowling,
                'status' => 'completed',
                'total_runs' => $runs,
                'total_wickets' => $wickets,
            ]);
        }

        return $match->fresh();
    }

    private function finalizedMatch(): GameMatch
    {
        return GameMatch::factory()->create([
            'match_status' => 'completed',
            'result_type' => 'won',
            'match_result' => 'Team A won by 10 runs',
        ]);
    }

    /**
     * Every ability of the tournament-setup and match policies, with the one permission that grants it.
     *
     * @return list<array{0: class-string, 1: string, 2: string}>
     */
    private function abilities(): array
    {
        $crud = ['viewAny' => 'view', 'view' => 'view', 'create' => 'manage', 'update' => 'manage', 'delete' => 'manage'];

        $rows = [];

        foreach ([
            [Edition::class, 'editions', $crud],
            // A season's team has no editable attributes, so no update().
            [EditionTeam::class, 'editions', array_diff_key($crud, ['update' => 1])],
            [Team::class, 'teams', $crud],
            [TeamPlayer::class, 'teams', $crud],
            [Venue::class, 'venues', $crud],
            [Player::class, 'players', $crud],
            [PlayerRegistration::class, 'registrations', $crud],
        ] as [$model, $module, $abilities]) {
            foreach ($abilities as $ability => $level) {
                $rows[] = [$model, $ability, "{$module}.{$level}"];
            }
        }

        return [...$rows,
            [GameMatch::class, 'viewAny', 'matches.view'],
            [GameMatch::class, 'view', 'matches.view'],
            [GameMatch::class, 'create', 'matches.manage'],
            [GameMatch::class, 'update', 'matches.manage'],
            [GameMatch::class, 'delete', 'matches.manage'],
            [GameMatch::class, 'cancelMatch', 'matches.manage'],
            [GameMatch::class, 'manageMatchFlow', 'matches.run'],
            [GameMatch::class, 'manageInnings', 'matches.run'],
            [GameMatch::class, 'abandonMatch', 'matches.run'],
            [GameMatch::class, 'score', 'scoring.score'],
            [GameMatch::class, 'finalizeResult', 'matches.finalize'],
            [GameMatch::class, 'reopenResult', 'matches.reopen'],
            [MatchPlayer::class, 'viewAny', 'matches.view'],
            [MatchPlayer::class, 'create', 'matches.run'],
            [MatchPlayer::class, 'update', 'matches.run'],
        ];
    }

    /**
     * What a policy is asked about: the model class for the abilities that have no instance
     * (listing, creating), a blank instance for the rest.
     */
    private function subject(string $model, string $ability): string|object
    {
        return in_array($ability, ['viewAny', 'create'], true) ? $model : new $model;
    }

    // ----- Every ability maps to exactly one permission -----

    public function test_each_ability_is_granted_by_its_own_permission_and_by_no_other(): void
    {
        // One user per permission, holding only that one (`.manage` carries its `.view`).
        $holders = [];

        foreach (Permissions::keys(delegableOnly: true) as $held) {
            $holders[$held] = $this->userWith([$held], panelAccess: false);
        }

        foreach ($this->abilities() as [$model, $ability, $key]) {
            $this->assertTrue(Permissions::exists($key), "{$key} is not a permission in the catalog");

            foreach ($holders as $held => $user) {
                // What holding that one key really gives: itself, plus what it implies (`.manage` carries
                // its `.view`; running, scoring or finalizing a match carries looking at it).
                $effective = [$held, ...Permissions::implied($held)];
                $shouldAllow = in_array($key, $effective, true);

                $this->assertSame(
                    $shouldAllow,
                    $user->can($ability, $this->subject($model, $ability)),
                    class_basename($model)."::{$ability} should ".($shouldAllow ? '' : 'not ')."be allowed to a role holding only {$held}"
                );
            }
        }
    }

    public function test_built_in_roles_keep_exactly_the_abilities_they_had_before_roles_were_editable(): void
    {
        $admin = $this->builtIn('admin');
        $scorer = $this->builtIn('scorer');
        $auctioneer = $this->builtIn('auctioneer');
        $nobody = $this->userWith([], panelAccess: false);

        // The scorer used to be "admin or scorer" for exactly these and admin-only for the rest.
        $scorerMay = [
            GameMatch::class => ['viewAny', 'view', 'manageMatchFlow', 'manageInnings', 'abandonMatch', 'score', 'finalizeResult', 'reopenResult'],
            MatchPlayer::class => ['viewAny', 'create', 'update'],
        ];

        foreach ($this->abilities() as [$model, $ability]) {
            $subject = $this->subject($model, $ability);
            $name = class_basename($model)."::{$ability}";

            $this->assertTrue($admin->can($ability, $subject), "admin should be allowed {$name}");
            $this->assertSame(in_array($ability, $scorerMay[$model] ?? [], true), $scorer->can($ability, $subject), "scorer {$name}");
            $this->assertFalse($auctioneer->can($ability, $subject), "auctioneer should not be allowed {$name}");
            $this->assertFalse($nobody->can($ability, $subject), "a role without permissions should not be allowed {$name}");
        }
    }

    // ----- Through the real routes -----

    public function test_a_role_that_can_only_view_matches_sees_fixtures_but_cannot_schedule_edit_delete_or_cancel_them(): void
    {
        $viewer = $this->userWith(['matches.view']);
        $match = GameMatch::factory()->create(['match_status' => 'scheduled']);

        $this->actingAs($viewer)->get(route('admin.matches.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.matches.show', $match))->assertOk();

        $this->actingAs($viewer)->get(route('admin.matches.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.matches.edit', $match))->assertForbidden();
        // A valid payload, so it is the permission check that refuses it and not validation.
        $this->actingAs($viewer)
            ->put(route('admin.matches.update', $match), [
                'edition_id' => $match->edition_id,
                'edition_team_a_id' => $match->edition_team_a_id,
                'edition_team_b_id' => $match->edition_team_b_id,
                'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ])
            ->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.matches.destroy', $match))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.matches.cancel', $match))->assertForbidden();

        $this->assertModelExists($match);
        $this->assertSame('scheduled', $match->fresh()->match_status);
    }

    public function test_running_a_match_without_the_scoring_permission_drives_the_flow_but_is_refused_scoring(): void
    {
        $runner = $this->userWith(['matches.view', 'matches.run']);

        $scheduled = $this->matchReadyForToss();
        $this->actingAs($runner)
            ->post(route('admin.matches.start-toss', $scheduled))
            ->assertRedirect(route('admin.matches.show', $scheduled));
        $this->assertSame('toss', $scheduled->fresh()->match_status);

        [$match, $innings, $batting, $bowling] = $this->liveMatchWithInnings();

        $this->actingAs($runner)->get(route('admin.matches.innings.score', [$match, $innings]))->assertForbidden();
        $this->actingAs($runner)
            ->post(route('admin.matches.innings.deliveries.store', [$match, $innings]), $this->deliveryPayload($batting, $bowling))
            ->assertForbidden();
        $this->assertSame(0, Delivery::where('innings_id', $innings->id)->count());
    }

    public function test_scoring_alone_cannot_run_finalize_or_reopen_and_reopening_needs_its_own_permission(): void
    {
        $scoringOnly = $this->userWith(['scoring.score']);

        // It can score...
        [$live, $innings, $batting, $bowling] = $this->liveMatchWithInnings();
        $this->actingAs($scoringOnly)
            ->post(route('admin.matches.innings.deliveries.store', [$live, $innings]), $this->deliveryPayload($batting, $bowling))
            ->assertRedirect(route('admin.matches.innings.score', [$live, $innings]));
        $this->assertSame(1, Delivery::where('innings_id', $innings->id)->count());

        // ...but not drive the match, close out its result, or undo one.
        $scheduled = $this->matchReadyForToss();
        $this->actingAs($scoringOnly)->post(route('admin.matches.start-toss', $scheduled))->assertForbidden();
        $this->assertSame('scheduled', $scheduled->fresh()->match_status);

        $finishedInnings = $this->matchReadyToFinalize();
        $this->actingAs($scoringOnly)->post(route('admin.matches.finalize', $finishedInnings))->assertForbidden();
        $this->assertSame('live', $finishedInnings->fresh()->match_status);

        $finalized = $this->finalizedMatch();
        $this->actingAs($scoringOnly)
            ->post(route('admin.matches.reopen', $finalized), ['reason' => 'Scoring error found'])
            ->assertForbidden();
        $this->assertSame('completed', $finalized->fresh()->match_status);

        // Reopening is its own permission, and holding just that is enough to do it.
        $this->actingAs($this->userWith(['matches.reopen']))
            ->post(route('admin.matches.reopen', $finalized), ['reason' => 'Scoring error found after finalization'])
            ->assertRedirect(route('admin.matches.show', $finalized));
        $this->assertSame('live', $finalized->fresh()->match_status);
    }

    public function test_managing_teams_allows_creating_one_while_only_viewing_teams_does_not(): void
    {
        $viewer = $this->userWith(['teams.view']);
        $manager = $this->userWith(['teams.manage']);

        $this->actingAs($viewer)->get(route('admin.teams.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.teams.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.teams.store'), ['name' => 'Delhi Capitals'])->assertForbidden();
        $this->assertDatabaseCount('teams', 0);

        $this->actingAs($manager)
            ->post(route('admin.teams.store'), ['name' => 'Delhi Capitals'])
            ->assertRedirect(route('admin.teams.index'));
        $this->assertDatabaseHas('teams', ['name' => 'Delhi Capitals']);

        // Managing a module includes looking at it.
        $this->actingAs($manager)->get(route('admin.teams.index'))->assertOk();

        // Teams are not matches: neither role may touch fixtures.
        $this->actingAs($manager)->get(route('admin.matches.index'))->assertForbidden();
    }

    public function test_a_role_with_only_panel_access_is_refused_everywhere_in_tournament_setup_and_matches(): void
    {
        $user = $this->userWith([]);

        $edition = Edition::factory()->create();
        $team = Team::factory()->create();
        $match = GameMatch::factory()->create();
        $innings = Innings::create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $match->edition_team_a_id,
            'bowling_team_id' => $match->edition_team_b_id,
            'status' => 'live',
        ]);

        $requests = [
            ['get', route('admin.editions.index')],
            ['get', route('admin.edition-teams.index')],
            ['get', route('admin.teams.index')],
            ['get', route('admin.team-players.index')],
            ['get', route('admin.venues.index')],
            ['get', route('admin.players.index')],
            ['get', route('admin.player-registrations.index')],
            ['get', route('admin.matches.index')],
            ['get', route('admin.matches.show', $match)],
            ['get', route('admin.matches.scorecard', $match)],
            ['get', route('admin.matches.players.index', $match)],
            ['get', route('admin.editions.matches.index', $edition)],
            ['get', route('admin.editions.teams.index', $edition)],
            ['get', route('admin.editions.squads.index', $edition)],
            ['get', route('admin.editions.registrations.index', $edition)],
            ['get', route('admin.matches.innings.score', [$match, $innings])],
            ['delete', route('admin.teams.destroy', $team)],
            ['post', route('admin.matches.start-toss', $match)],
            ['post', route('admin.matches.cancel', $match)],
            ['post', route('admin.matches.abandon', $match)],
            ['post', route('admin.matches.finalize', $match)],
        ];

        foreach ($requests as [$method, $url]) {
            $this->assertSame(403, $this->actingAs($user)->{$method}($url)->status(), "{$method} {$url} should be refused");
        }

        $this->assertModelExists($team);
        $this->assertSame('scheduled', $match->fresh()->match_status);
    }

    public function test_the_built_in_scorer_can_score_finalize_and_reopen_but_not_schedule_matches_or_manage_teams(): void
    {
        $scorer = $this->builtIn('scorer');

        [$live, $innings, $batting, $bowling] = $this->liveMatchWithInnings();
        $this->actingAs($scorer)->get(route('admin.matches.index'))->assertOk();
        $this->actingAs($scorer)
            ->post(route('admin.matches.innings.deliveries.store', [$live, $innings]), $this->deliveryPayload($batting, $bowling))
            ->assertRedirect(route('admin.matches.innings.score', [$live, $innings]));
        $this->assertSame(1, Delivery::where('innings_id', $innings->id)->count());

        $finished = $this->matchReadyToFinalize();
        $this->actingAs($scorer)
            ->post(route('admin.matches.finalize', $finished))
            ->assertRedirect(route('admin.matches.show', $finished));
        $this->assertSame('completed', $finished->fresh()->match_status);

        $this->actingAs($scorer)->get(route('admin.matches.create'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.teams.index'))->assertForbidden();
        $this->actingAs($scorer)->post(route('admin.teams.store'), ['name' => 'Delhi Capitals'])->assertForbidden();
        // A wrong result is the scorer's to take back, at once and with no time limit.
        $this->actingAs($scorer)
            ->post(route('admin.matches.reopen', $finished), ['reason' => 'Scoring error found'])
            ->assertRedirect(route('admin.matches.show', $finished));
        $this->assertSame('live', $finished->fresh()->match_status);
    }
}
