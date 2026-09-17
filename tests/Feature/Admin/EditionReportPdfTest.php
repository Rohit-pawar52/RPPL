<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\Statistics\PlayerStatisticsService;
use App\Services\Statistics\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EditionReportPdfTest extends TestCase
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
     * An edition with a completed match (so standings/leaderboards are
     * non-empty) plus a private registration to assert against for the
     * privacy test.
     */
    private function editionWithTournamentData(): array
    {
        $edition = Edition::factory()->create(['name' => 'Season One', 'year' => 2026]);
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        $player = Player::factory()->create(['phone' => '9998887771', 'email' => 'player@example.com']);
        $registration = PlayerRegistration::factory()->create([
            'edition_id' => $edition->id,
            'player_id' => $player->id,
            'payment_status' => 'paid',
            'registration_fee' => 500,
        ]);
        $matchPlayer = TeamPlayer::factory()->create([
            'edition_team_id' => $teamA->id,
            'player_registration_id' => $registration->id,
        ]);

        GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'match_status' => 'completed',
            'result_type' => 'won',
            'winner_team_id' => $teamA->id,
            'match_result' => $teamA->team->name.' won',
        ]);

        return [$edition, $player, $matchPlayer];
    }

    public function test_admin_can_download_edition_summary_pdf(): void
    {
        [$edition] = $this->editionWithTournamentData();

        $response = $this->actingAs($this->admin())->get(route('admin.editions.report.pdf', $edition));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_scorer_is_forbidden(): void
    {
        [$edition] = $this->editionWithTournamentData();

        $this->actingAs($this->scorer())
            ->get(route('admin.editions.report.pdf', $edition))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        [$edition] = $this->editionWithTournamentData();

        $this->get(route('admin.editions.report.pdf', $edition))
            ->assertRedirect(route('admin.login'));
    }

    public function test_draft_edition_with_no_data_still_produces_a_valid_pdf(): void
    {
        $edition = Edition::factory()->create(['status' => 'upcoming']);

        $response = $this->actingAs($this->admin())->get(route('admin.editions.report.pdf', $edition));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_standings_and_leaderboard_data_render_without_duplicated_calculation(): void
    {
        [$edition] = $this->editionWithTournamentData();

        $view = view('admin.editions.report-pdf', [
            'edition' => $edition->loadCount(['playerRegistrations', 'editionTeams', 'matches']),
            'registrationCounts' => collect(['paid' => 1]),
            'paidRegistrationFees' => 500.0,
            'matchStatusCounts' => collect(['completed' => 1]),
            'teams' => $edition->editionTeams()->with('team')->withCount('teamPlayers')->get(),
            'matches' => $edition->matches()->with(['teamA.team', 'teamB.team'])->get(),
            'standings' => app(StandingsService::class)->getEditionStandings($edition),
            'leaderboard' => app(PlayerStatisticsService::class)->getEditionLeaderboard($edition),
        ])->render();

        $this->assertStringContainsString('Standings', $view);
        $this->assertStringContainsString($edition->editionTeams->first()->team->name, $view);
    }

    public function test_download_uses_a_deterministic_safe_filename(): void
    {
        [$edition] = $this->editionWithTournamentData();

        $response = $this->actingAs($this->admin())->get(route('admin.editions.report.pdf', $edition));

        $expected = 'rppl-'.Str::slug($edition->name).'-'.$edition->year.'-summary.pdf';
        $response->assertHeader('content-disposition', "attachment; filename={$expected}");
    }

    public function test_pdf_does_not_intentionally_include_private_or_finance_fields(): void
    {
        [$edition, $player] = $this->editionWithTournamentData();

        $view = view('admin.editions.report-pdf', [
            'edition' => $edition->loadCount(['playerRegistrations', 'editionTeams', 'matches']),
            'registrationCounts' => collect(['paid' => 1]),
            'paidRegistrationFees' => 500.0,
            'matchStatusCounts' => collect(['completed' => 1]),
            'teams' => $edition->editionTeams()->with('team')->withCount('teamPlayers')->get(),
            'matches' => $edition->matches()->with(['teamA.team', 'teamB.team'])->get(),
            'standings' => app(StandingsService::class)->getEditionStandings($edition),
            'leaderboard' => app(PlayerStatisticsService::class)->getEditionLeaderboard($edition),
        ])->render();

        $this->assertStringNotContainsString($player->phone, $view);
        $this->assertStringNotContainsString($player->email, $view);
        $this->assertStringNotContainsString('committee', strtolower($view));
        $this->assertStringNotContainsString('payment_status', $view);
        $this->assertStringNotContainsString('registration_fee', $view);
    }

    public function test_download_pdf_link_appears_on_the_edition_show_page(): void
    {
        [$edition] = $this->editionWithTournamentData();

        $response = $this->actingAs($this->admin())->get(route('admin.editions.show', $edition));

        $response->assertOk();
        $response->assertSee(route('admin.editions.report.pdf', $edition), false);
    }
}
