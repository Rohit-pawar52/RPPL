<?php

namespace Tests\Feature\Admin;

use App\Models\CommitteeMember;
use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionContribution;
use App\Models\EditionTeam;
use App\Models\EditionTransaction;
use App\Models\GameMatch;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    public function test_dashboard_prefers_active_edition_over_upcoming_and_completed(): void
    {
        Edition::factory()->create(['name' => 'RPPL Completed', 'status' => 'completed', 'year' => 2023]);
        Edition::factory()->create(['name' => 'RPPL Upcoming', 'status' => 'upcoming', 'year' => 2026]);
        $active = Edition::factory()->create(['name' => 'RPPL Active', 'status' => 'active', 'year' => 2025]);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee($active->name);
        $response->assertDontSee('RPPL Upcoming');
        $response->assertDontSee('RPPL Completed');
    }

    public function test_dashboard_falls_back_to_upcoming_then_completed_edition(): void
    {
        $completed = Edition::factory()->create(['name' => 'RPPL Old', 'status' => 'completed', 'year' => 2022]);

        // Only a completed edition exists — that's the fallback.
        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));
        $response->assertOk()->assertSee($completed->name);

        $upcoming = Edition::factory()->create(['name' => 'RPPL Next', 'status' => 'upcoming', 'year' => 2027]);

        // Upcoming now outranks the completed edition.
        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));
        $response->assertOk()->assertSee($upcoming->name)->assertDontSee('RPPL Old');
    }

    public function test_no_editions_shows_empty_state(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('No editions available yet');
    }

    public function test_summary_counts_are_scoped_to_the_selected_edition(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $otherEdition = Edition::factory()->create(['status' => 'completed', 'year' => 2020]);

        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $paidRegistration = PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'paid']);
        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'pending']);
        TeamPlayer::factory()->create(['edition_team_id' => $teamA->id, 'player_registration_id' => $paidRegistration->id]);
        GameMatch::factory()->create(['edition_id' => $edition->id, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id, 'match_status' => 'scheduled']);

        // Data belonging to a different edition must never bleed into these counts.
        $otherTeamA = EditionTeam::factory()->create(['edition_id' => $otherEdition->id]);
        $otherTeamB = EditionTeam::factory()->create(['edition_id' => $otherEdition->id]);
        PlayerRegistration::factory()->count(5)->create(['edition_id' => $otherEdition->id, 'payment_status' => 'paid']);
        GameMatch::factory()->count(3)->create(['edition_id' => $otherEdition->id, 'edition_team_a_id' => $otherTeamA->id, 'edition_team_b_id' => $otherTeamB->id, 'match_status' => 'completed']);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('registeredPlayers', 2);
        $response->assertViewHas('paidRegistrations', 1);
        $response->assertViewHas('pendingRegistrations', 1);
        $response->assertViewHas('teamsCount', 2);
        $response->assertViewHas('squadPlayersCount', 1);
        $response->assertViewHas('matchesCount', 1);
        $response->assertViewHas('scheduledMatchesCount', 1);
    }

    public function test_matches_needing_attention_prioritizes_live_toss_then_scheduled_and_is_bounded(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        $live = GameMatch::factory()->create(['edition_id' => $edition->id, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id, 'match_status' => 'live', 'started_at' => now()]);
        $toss = GameMatch::factory()->create(['edition_id' => $edition->id, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id, 'match_status' => 'toss']);

        // 8 scheduled matches — the section is bounded to 8 rows total,
        // so with a live and a toss match already present, only 6 of
        // these scheduled matches should make the cut.
        foreach (range(1, 8) as $i) {
            GameMatch::factory()->create([
                'edition_id' => $edition->id, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id,
                'match_status' => 'scheduled', 'scheduled_at' => now()->addDays($i),
            ]);
        }

        GameMatch::factory()->create(['edition_id' => $edition->id, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id, 'match_status' => 'completed', 'match_result' => 'Result']);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $attention = $response->viewData('matchesNeedingAttention');

        $this->assertCount(8, $attention);
        $this->assertSame($live->id, $attention->first()->id);
        $this->assertSame($toss->id, $attention->get(1)->id);
        $this->assertTrue($attention->pluck('match_status')->doesntContain('completed'));
    }

    public function test_recent_results_show_stored_result_for_completed_matches_only(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        $completed = GameMatch::factory()->create([
            'edition_id' => $edition->id, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id,
            'match_status' => 'completed', 'match_result' => $teamA->team->name.' won by 42 runs',
        ]);
        GameMatch::factory()->create(['edition_id' => $edition->id, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id, 'match_status' => 'scheduled']);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee($teamA->team->name.' won by 42 runs');

        $recentResults = $response->viewData('recentResults');
        $this->assertCount(1, $recentResults);
        $this->assertSame($completed->id, $recentResults->first()->id);
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    /**
     * Existing dashboard authorization (accessible to anyone who can
     * enter the admin panel — admin or scorer, per
     * AppServiceProvider's access-admin-panel gate) is unchanged by
     * Phase 3.42; this proves it explicitly rather than assuming.
     */
    public function test_scorer_can_still_access_dashboard(): void
    {
        $this->actingAs($this->scorer())->get(route('admin.dashboard'))->assertOk();
    }

    // ----- Payment/finance/contribution metrics (Phase 3.42) -----

    /**
     * Covers: edition scoping, pending-verification count, paid amount
     * computed from stored registration_fee on PAID rows only (never
     * edition->registration_fee x count, never inflated by pending/
     * failed/refunded rows), finance income/expense/balance matching
     * the same semantics EditionTransaction::summaryForEdition() (also
     * used by the Finance ledger page) produces, and contribution
     * total/count — all in one scenario, with a second "noise" edition
     * proving nothing leaks across editions.
     */
    public function test_payment_finance_and_contribution_metrics_are_correct_and_edition_scoped(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);

        // Paid rows with DIFFERENT stored fees — proves the total is a
        // sum of actual stored values, not edition fee x paid count.
        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'paid', 'registration_fee' => 400]);
        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'paid', 'registration_fee' => 250.50]);
        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'pending', 'registration_fee' => 400]);
        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'failed', 'registration_fee' => 400]);
        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'refunded', 'registration_fee' => 400]);

        EditionTransaction::factory()->create(['edition_id' => $edition->id, 'type' => 'income', 'amount' => 10000]);
        EditionTransaction::factory()->create(['edition_id' => $edition->id, 'type' => 'expense', 'amount' => 3000]);

        $member = CommitteeMember::factory()->create();
        EditionContribution::factory()->create(['edition_id' => $edition->id, 'committee_member_id' => $member->id, 'amount' => 1500]);
        $contributor = Contributor::factory()->create();
        EditionContribution::factory()->create(['edition_id' => $edition->id, 'committee_member_id' => null, 'contributor_id' => $contributor->id, 'amount' => 500]);

        // Noise in a completely different edition — must never bleed in.
        $otherEdition = Edition::factory()->create(['status' => 'completed', 'year' => 2020]);
        PlayerRegistration::factory()->create(['edition_id' => $otherEdition->id, 'payment_status' => 'paid', 'registration_fee' => 99999]);
        EditionTransaction::factory()->create(['edition_id' => $otherEdition->id, 'type' => 'income', 'amount' => 99999]);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('paidRegistrations', 2);
        $response->assertViewHas('pendingRegistrations', 1);
        $response->assertViewHas('failedRegistrations', 1);
        $response->assertViewHas('refundedRegistrations', 1);
        $response->assertViewHas('paidRegistrationAmount', 650.5);

        // 10000 explicit + 1500 + 500 auto-created by the two
        // EditionContribution factories (each contribution owns its own
        // matching income transaction, exactly like the real
        // EditionContributionService) — proves contribution income is
        // already counted here, not something to add again separately.
        $financeSummary = $response->viewData('financeSummary');
        $this->assertSame(12000.0, $financeSummary['income']);
        $this->assertSame(3000.0, $financeSummary['expense']);
        $this->assertSame(9000.0, $financeSummary['balance']);

        $response->assertViewHas('contributionTotal', 2000.0);
        $response->assertViewHas('contributionCount', 2);
        $response->assertViewHas('recognizedContributorsCount', 2);
    }

    /**
     * The pending-verification count and the "Pending Verification" /
     * "View all" links must point at the EXISTING PlayerRegistration
     * index with the exact same edition_id/payment_status query
     * parameters that page's own filters already use — never a new
     * page. Same for the Finance/Contributions links.
     */
    public function test_dashboard_action_links_use_existing_routes_with_correct_edition_and_status_filters(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'pending']);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        // Blade's {{ }} HTML-escapes the href, turning "&" into "&amp;" —
        // compare against that same escaped form rather than the raw
        // route() string.
        $response->assertOk();
        $response->assertSee(
            e(route('admin.player-registrations.index', ['edition_id' => $edition->id, 'payment_status' => 'pending'])),
            false
        );
        $response->assertSee(e(route('admin.player-registrations.index', ['edition_id' => $edition->id])), false);
        $response->assertSee(e(route('admin.edition-transactions.index', ['edition_id' => $edition->id])), false);
        $response->assertSee(e(route('admin.edition-contributions.index', ['edition_id' => $edition->id])), false);
    }

    public function test_zero_data_edition_renders_clean_zero_values_without_errors(): void
    {
        Edition::factory()->create(['status' => 'active']);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('paidRegistrationAmount', 0.0);
        $financeSummary = $response->viewData('financeSummary');
        $this->assertSame(0.0, $financeSummary['income']);
        $this->assertSame(0.0, $financeSummary['expense']);
        $this->assertSame(0.0, $financeSummary['balance']);
        $response->assertViewHas('contributionTotal', 0.0);
        $response->assertViewHas('contributionCount', 0);
        $response->assertSee('&#8377;0.00', false);
    }

    /**
     * Phase 3.41 removed the permanently-disabled "Scoring"/"Reports"/
     * "Settings" sidebar placeholders that misleadingly suggested
     * unfinished features. Phase 3.43 gave Reports a real route, and
     * Phase 3.44B2 gave Settings one too — both are back as actual
     * links, not dead stubs — while Scoring (still no real top-level
     * page) must remain absent.
     */
    public function test_sidebar_shows_real_reports_and_settings_links_but_not_the_scoring_stub(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Scoring');
        $response->assertSee('Reports');
        $response->assertSee(route('admin.reports.index'), false);
        $response->assertSee('Settings');
        $response->assertSee(route('admin.settings.index'), false);
    }
}
