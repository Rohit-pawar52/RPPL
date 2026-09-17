<?php

namespace Tests\Feature\Admin;

use App\Models\Delivery;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\Scoring\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchCancellationTest extends TestCase
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

    private function matchWithSelectedPlayers(array $matchAttributes = []): GameMatch
    {
        $match = GameMatch::factory()->create(array_merge(['match_status' => 'scheduled'], $matchAttributes));

        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_a_id])->id,
        ]);
        MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_b_id])->id,
        ]);

        return $match->fresh();
    }

    /**
     * A live match with one Innings and one scored Delivery, to prove
     * abandonment preserves scoring history untouched.
     *
     * @return array{0: GameMatch, 1: Innings}
     */
    private function liveMatchWithScoring(): array
    {
        $match = $this->matchWithSelectedPlayers(['match_status' => 'live', 'started_at' => now()]);
        $match->update(['toss_winner_team_id' => $match->edition_team_a_id, 'toss_decision' => 'bat']);

        $innings = Innings::create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $match->edition_team_a_id,
            'bowling_team_id' => $match->edition_team_b_id,
            'status' => 'live',
        ]);

        $striker = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_a_id])->id,
        ]);
        $nonStriker = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_a_id])->id,
        ]);
        $bowler = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_b_id])->id,
        ]);

        app(DeliveryService::class)->recordDelivery($match->fresh(), $innings->fresh(), [
            'striker_match_player_id' => $striker->id,
            'non_striker_match_player_id' => $nonStriker->id,
            'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 4,
        ]);

        return [$match->fresh(), $innings->fresh()];
    }

    // ----- Cancel -----

    public function test_admin_can_cancel_scheduled_match_with_no_innings(): void
    {
        $match = $this->matchWithSelectedPlayers();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.cancel', $match))
            ->assertRedirect(route('admin.matches.show', $match));

        $match->refresh();
        $this->assertSame('cancelled', $match->match_status);
        $this->assertSame('Match cancelled', $match->match_result);
        $this->assertNull($match->winner_team_id);
        $this->assertNull($match->result_type);
        $this->assertNull($match->win_margin_type);
        $this->assertNull($match->win_margin);
        $this->assertNull($match->completed_at);
    }

    public function test_scorer_cannot_cancel_match(): void
    {
        $match = $this->matchWithSelectedPlayers();

        $this->actingAs($this->scorer())
            ->post(route('admin.matches.cancel', $match))
            ->assertForbidden();

        $this->assertSame('scheduled', $match->fresh()->match_status);
    }

    public function test_match_with_innings_cannot_be_cancelled(): void
    {
        $match = $this->matchWithSelectedPlayers();
        Innings::factory()->create([
            'match_id' => $match->id,
            'batting_team_id' => $match->edition_team_a_id,
            'bowling_team_id' => $match->edition_team_b_id,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.cancel', $match))
            ->assertSessionHas('error');

        $this->assertSame('scheduled', $match->fresh()->match_status);
    }

    public function test_repeated_cancel_fails_safely(): void
    {
        $match = $this->matchWithSelectedPlayers();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.matches.cancel', $match))->assertRedirect();
        $this->actingAs($admin)
            ->post(route('admin.matches.cancel', $match))
            ->assertSessionHas('error');

        $this->assertSame('cancelled', $match->fresh()->match_status);
    }

    public function test_cancelled_match_blocks_further_workflow(): void
    {
        $match = $this->matchWithSelectedPlayers();
        $this->actingAs($this->admin())->post(route('admin.matches.cancel', $match));

        $this->actingAs($this->admin())
            ->post(route('admin.matches.start-toss', $match))
            ->assertSessionHas('error');

        $this->assertSame('cancelled', $match->fresh()->match_status);
    }

    // ----- Abandon -----

    public function test_admin_and_scorer_can_abandon_toss_or_live_match(): void
    {
        $tossMatch = $this->matchWithSelectedPlayers(['match_status' => 'toss']);
        $liveMatch = $this->matchWithSelectedPlayers(['match_status' => 'live', 'started_at' => now()]);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.abandon', $tossMatch))
            ->assertRedirect(route('admin.matches.show', $tossMatch));
        $this->assertSame('abandoned', $tossMatch->fresh()->match_status);

        $this->actingAs($this->scorer())
            ->post(route('admin.matches.abandon', $liveMatch))
            ->assertRedirect(route('admin.matches.show', $liveMatch));

        $liveMatch->refresh();
        $this->assertSame('abandoned', $liveMatch->match_status);
        $this->assertSame('abandoned', $liveMatch->result_type);
        $this->assertSame('Match abandoned', $liveMatch->match_result);
        $this->assertNull($liveMatch->winner_team_id);
        $this->assertNull($liveMatch->win_margin_type);
        $this->assertNull($liveMatch->win_margin);
        $this->assertNull($liveMatch->completed_at);
    }

    public function test_scheduled_completed_and_cancelled_matches_cannot_be_abandoned(): void
    {
        $admin = $this->admin();

        $scheduled = $this->matchWithSelectedPlayers();
        $this->actingAs($admin)->post(route('admin.matches.abandon', $scheduled))->assertSessionHas('error');
        $this->assertSame('scheduled', $scheduled->fresh()->match_status);

        $completed = $this->matchWithSelectedPlayers(['match_status' => 'completed', 'started_at' => now(), 'completed_at' => now()]);
        $this->actingAs($admin)->post(route('admin.matches.abandon', $completed))->assertSessionHas('error');
        $this->assertSame('completed', $completed->fresh()->match_status);

        $cancelled = $this->matchWithSelectedPlayers(['match_status' => 'cancelled']);
        $this->actingAs($admin)->post(route('admin.matches.abandon', $cancelled))->assertSessionHas('error');
        $this->assertSame('cancelled', $cancelled->fresh()->match_status);
    }

    public function test_repeated_abandon_fails_safely(): void
    {
        $match = $this->matchWithSelectedPlayers(['match_status' => 'live', 'started_at' => now()]);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.matches.abandon', $match))->assertRedirect();
        $this->actingAs($admin)
            ->post(route('admin.matches.abandon', $match))
            ->assertSessionHas('error');

        $this->assertSame('abandoned', $match->fresh()->match_status);
    }

    public function test_abandonment_preserves_innings_and_delivery_history_and_blocks_further_scoring(): void
    {
        [$match, $innings] = $this->liveMatchWithScoring();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.abandon', $match))
            ->assertRedirect(route('admin.matches.show', $match));

        $match->refresh();
        $innings->refresh();

        $this->assertSame('abandoned', $match->match_status);
        $this->assertNotNull($innings); // Innings row still exists
        $this->assertSame(4, $innings->total_runs); // cached totals untouched
        $this->assertSame(1, Delivery::where('innings_id', $innings->id)->count());

        // Further scoring must now be blocked by the existing
        // match-status guard in DeliveryService — no change needed there.
        $this->assertFalse(app(DeliveryService::class)->canRecordDelivery($match, $innings));
    }

    // ----- UI -----

    public function test_scheduled_match_ui_shows_cancel_to_admin_but_not_scorer(): void
    {
        $match = $this->matchWithSelectedPlayers();

        $adminResponse = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));
        $adminResponse->assertOk()->assertSee('Cancel Match');

        $scorerResponse = $this->actingAs($this->scorer())->get(route('admin.matches.show', $match));
        $scorerResponse->assertOk()->assertDontSee('Cancel Match');
    }

    public function test_toss_and_live_match_ui_shows_abandon_and_terminal_matches_show_no_controls(): void
    {
        $tossMatch = $this->matchWithSelectedPlayers(['match_status' => 'toss']);
        $this->actingAs($this->scorer())
            ->get(route('admin.matches.show', $tossMatch))
            ->assertOk()
            ->assertSee('Abandon Match');

        $liveMatch = $this->matchWithSelectedPlayers(['match_status' => 'live', 'started_at' => now()]);
        $this->actingAs($this->admin())
            ->get(route('admin.matches.show', $liveMatch))
            ->assertOk()
            ->assertSee('Abandon Match');

        $abandoned = $this->matchWithSelectedPlayers(['match_status' => 'abandoned', 'result_type' => 'abandoned', 'match_result' => 'Match abandoned']);
        $this->actingAs($this->admin())
            ->get(route('admin.matches.show', $abandoned))
            ->assertOk()
            ->assertDontSee('Cancel Match')
            ->assertDontSee('Abandon Match');

        $cancelled = $this->matchWithSelectedPlayers(['match_status' => 'cancelled', 'match_result' => 'Match cancelled']);
        $this->actingAs($this->admin())
            ->get(route('admin.matches.show', $cancelled))
            ->assertOk()
            ->assertDontSee('Cancel Match')
            ->assertDontSee('Abandon Match');
    }
}
