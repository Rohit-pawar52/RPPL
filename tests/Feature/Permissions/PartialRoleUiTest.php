<?php

namespace Tests\Feature\Permissions;

use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionContribution;
use App\Models\EditionTeam;
use App\Models\EditionTransaction;
use App\Models\GameMatch;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * A custom role can hold any subset of permissions, so the pages that point into other modules must
 * not show what the role may not see (money) nor offer links its target would refuse (403). Each test
 * gives a role exactly the permissions under test (plus panel.access) and looks at what the page
 * contains; the built-in admin and scorer pages are checked to still carry every link they had.
 */
class PartialRoleUiTest extends TestCase
{
    use RefreshDatabase;

    private int $customRoles = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // money() reads the currency symbol from settings, and the array cache outlives RefreshDatabase.
        app(SettingsService::class)->flush();
    }

    /**
     * A user whose custom role holds panel.access plus exactly these permissions.
     */
    private function userWith(string ...$permissions): User
    {
        $this->customRoles++;

        $role = Role::create(['name' => 'Custom '.$this->customRoles, 'slug' => 'custom-'.$this->customRoles]);
        $role->syncPermissions(['panel.access', ...$permissions]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function builtIn(string $slug): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * The current season with distinctive money, so a leak shows up as one of these exact strings:
     * income 91,357 + a 6,543 contribution (which books its own income) = 97,900.00, expense 2,468.00,
     * balance 95,432.00, and a paid registration fee of 7,351.00.
     */
    private function seasonWithMoney(): Edition
    {
        $edition = Edition::factory()->create(['name' => 'RPPL Hidden Cup', 'status' => 'active', 'year' => 2060]);

        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'paid', 'registration_fee' => 7351]);
        EditionTransaction::factory()->create(['edition_id' => $edition->id, 'type' => 'income', 'amount' => 91357]);
        EditionTransaction::factory()->create(['edition_id' => $edition->id, 'type' => 'expense', 'amount' => 2468]);
        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'contributor_id' => Contributor::factory()->create()->id,
            'amount' => 6543,
        ]);

        return $edition;
    }

    /**
     * @return list<string>
     */
    private function money(): array
    {
        return ['97,900.00', '2,468.00', '95,432.00', '6,543.00', '7,351.00'];
    }

    /**
     * The season with two named teams, a live match and a finished one, plus a registration waiting
     * for payment verification (so the dashboard's "action required" banner is there for finance.view).
     *
     * @return array{0: Edition, 1: GameMatch, 2: GameMatch, 3: EditionTeam}
     */
    private function seasonWithMatches(): array
    {
        $edition = $this->seasonWithMoney();
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id, 'team_id' => Team::factory()->create(['name' => 'Alpha Strikers'])->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id, 'team_id' => Team::factory()->create(['name' => 'Beta Blasters'])->id]);
        $teams = ['edition_id' => $edition->id, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id];

        $live = GameMatch::factory()->create($teams + ['match_status' => 'live', 'started_at' => now()]);
        $done = GameMatch::factory()->create($teams + ['match_status' => 'completed', 'match_result' => 'Alpha Strikers won by 10 runs']);
        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'pending', 'registration_fee' => 500]);

        return [$edition, $live, $done, $teamA];
    }

    // ----- The Edition hub -----

    public function test_the_edition_hub_never_computes_or_shows_money_without_finance_view(): void
    {
        $edition = $this->seasonWithMoney();
        $financeLinks = [
            route('admin.edition-transactions.index', ['edition_id' => $edition->id]),
            route('admin.edition-contributions.index', ['edition_id' => $edition->id]),
        ];

        // editions.view opens the hub, and is not a way to read the books: no figures, no links, and
        // not even a query against the ledger or the contributions.
        DB::enableQueryLog();
        $response = $this->actingAs($this->userWith('editions.view'))->get(route('admin.editions.show', $edition))->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();

        $this->assertNull($response->viewData('financeSummary'));
        $this->assertNull($response->viewData('contributionSummary'));
        $this->assertStringNotContainsString('edition_transactions', $queries);
        $this->assertStringNotContainsString('edition_contributions', $queries);
        foreach ([...$this->money(), ...$financeLinks] as $leak) {
            $response->assertDontSee($leak, false);
        }

        // finance.view brings the card back, with its figures and both links.
        $response = $this->actingAs($this->userWith('editions.view', 'finance.view'))->get(route('admin.editions.show', $edition))->assertOk();

        $this->assertSame(95432.0, $response->viewData('financeSummary')['balance']);
        foreach (['95,432.00', '97,900.00', '2,468.00', '6,543.00', ...$financeLinks] as $shown) {
            $response->assertSee($shown, false);
        }
    }

    public function test_each_edition_hub_card_and_the_edit_button_need_the_permission_of_their_page(): void
    {
        $edition = $this->seasonWithMoney();
        $always = [
            'teams card' => route('admin.editions.teams.index', $edition),
            'summary pdf' => route('admin.editions.report.pdf', $edition),
        ];
        $needs = [
            'registrations.view' => route('admin.editions.registrations.index', $edition),
            'teams.view' => route('admin.editions.squads.index', $edition),
            'matches.view' => route('admin.editions.matches.index', $edition),
            'auction.run' => route('admin.auctions.show', $edition),
            'finance.view' => route('admin.edition-transactions.index', ['edition_id' => $edition->id]),
            'editions.manage' => route('admin.editions.edit', $edition),
        ];

        // editions.view alone: the teams card and the PDF, nothing else.
        $html = $this->actingAs($this->userWith('editions.view'))->get(route('admin.editions.show', $edition))->assertOk()->getContent();
        foreach ($always as $label => $link) {
            $this->assertStringContainsString($link, $html, "{$label} should be on the hub");
        }
        foreach ($needs as $permission => $link) {
            $this->assertStringNotContainsString($link, $html, "{$permission} is missing, so its link must not be drawn");
        }

        // Each extra permission brings exactly its own link.
        foreach ($needs as $permission => $link) {
            $html = $this->actingAs($this->userWith('editions.view', $permission))->get(route('admin.editions.show', $edition))->assertOk()->getContent();

            $this->assertStringContainsString($link, $html, "{$permission} should bring its link");
            foreach (array_diff($needs, [$link]) as $other => $otherLink) {
                $this->assertStringNotContainsString($otherLink, $html, "{$permission} must not bring the link of {$other}");
            }
        }
    }

    public function test_the_hub_of_the_admin_still_has_every_card_and_the_edit_button(): void
    {
        $edition = $this->seasonWithMoney();

        $response = $this->actingAs($this->builtIn('admin'))->get(route('admin.editions.show', $edition))->assertOk();

        foreach ([
            route('admin.editions.registrations.index', $edition),
            route('admin.editions.teams.index', $edition),
            route('admin.editions.squads.index', $edition),
            route('admin.editions.matches.index', $edition),
            route('admin.auctions.show', $edition),
            route('admin.edition-transactions.index', ['edition_id' => $edition->id]),
            route('admin.edition-contributions.index', ['edition_id' => $edition->id]),
            route('admin.editions.edit', $edition),
            route('admin.editions.report.pdf', $edition),
            '95,432.00',
        ] as $expected) {
            $response->assertSee($expected, false);
        }
    }

    // ----- The Edition summary PDF -----

    public function test_the_edition_summary_pdf_still_downloads_for_a_role_without_finance_view(): void
    {
        $edition = $this->seasonWithMoney();

        $response = $this->actingAs($this->userWith('editions.view'))->get(route('admin.editions.report.pdf', $edition));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_the_edition_summary_pdf_prints_the_paid_fees_only_for_a_role_with_finance_view(): void
    {
        $edition = $this->seasonWithMoney();

        // A PDF's text is compressed, so look at the page that goes into it: the view the controller
        // renders, with exactly the data it hands over.
        $pages = [];
        $pdf = Mockery::mock(DomPdf::class);
        $pdf->shouldReceive('setPaper')->andReturnSelf();
        $pdf->shouldReceive('download')->andReturn(response('pdf'));
        Pdf::shouldReceive('loadView')->twice()->andReturnUsing(function (string $view, array $data) use (&$pages, $pdf) {
            $this->assertSame('admin.editions.report-pdf', $view);
            $pages[] = ['fees' => $data['paidRegistrationFees'], 'html' => view($view, $data)->render()];

            return $pdf;
        });

        $this->actingAs($this->userWith('editions.view'))->get(route('admin.editions.report.pdf', $edition))->assertOk();
        $this->actingAs($this->userWith('editions.view', 'finance.view'))->get(route('admin.editions.report.pdf', $edition))->assertOk();

        [$withoutFinance, $withFinance] = $pages;

        $this->assertNull($withoutFinance['fees']);
        $this->assertStringNotContainsString('Paid Registration Fees', $withoutFinance['html']);
        $this->assertStringNotContainsString('7,351.00', $withoutFinance['html']);
        // The rest of the report is intact.
        $this->assertStringContainsString('Registration Summary', $withoutFinance['html']);
        $this->assertStringContainsString('Standings', $withoutFinance['html']);

        $this->assertSame(7351.0, $withFinance['fees']);
        $this->assertStringContainsString('Paid Registration Fees', $withFinance['html']);
        $this->assertStringContainsString('7,351.00', $withFinance['html']);
    }

    // ----- Reports -----

    public function test_the_reports_page_lists_only_the_reports_the_role_may_open(): void
    {
        $edition = $this->seasonWithMoney();
        $query = ['edition_id' => $edition->id];
        $summary = route('admin.editions.report.pdf', $edition);
        $registrations = route('admin.player-registrations.export', $query);
        $matches = route('admin.matches.index', $query);
        $finance = [
            route('admin.edition-transactions.export', $query),
            route('admin.edition-contributions.export', $query),
            route('admin.edition-contributions.index', $query),
            route('admin.reports.financial-summary', $query),
        ];
        $everything = [$summary, $registrations, $matches, ...$finance];
        $page = route('admin.reports.index', $query);

        // reports.view alone opens the page but none of the reports, and no empty boxes are left.
        $response = $this->actingAs($this->userWith('reports.view'))->get($page)->assertOk();
        foreach ($everything as $link) {
            $response->assertDontSee($link, false);
        }
        foreach (['Tournament Reports', 'Registration Reports', 'Finance &amp; Contributions'] as $heading) {
            $response->assertDontSee($heading, false);
        }

        // Finance reports follow finance.view (the summary needs both that and reports.view).
        $response = $this->actingAs($this->userWith('reports.view', 'finance.view'))->get($page)->assertOk();
        foreach ($finance as $link) {
            $response->assertSee($link, false);
        }
        foreach ([$summary, $registrations, $matches] as $link) {
            $response->assertDontSee($link, false);
        }

        // The other reports follow their own modules.
        $response = $this->actingAs($this->userWith('reports.view', 'editions.view', 'registrations.view', 'matches.view'))->get($page)->assertOk();
        foreach ([$summary, $registrations, $matches] as $link) {
            $response->assertSee($link, false);
        }
        foreach ($finance as $link) {
            $response->assertDontSee($link, false);
        }
    }

    public function test_the_reports_page_of_the_admin_still_links_every_report(): void
    {
        [$edition, , $done] = $this->seasonWithMatches();
        $query = ['edition_id' => $edition->id];

        $response = $this->actingAs($this->builtIn('admin'))->get(route('admin.reports.index', $query))->assertOk();

        foreach ([
            route('admin.editions.report.pdf', $edition),
            route('admin.player-registrations.export', $query),
            route('admin.edition-transactions.export', $query),
            route('admin.edition-contributions.export', $query),
            route('admin.edition-contributions.index', $query),
            route('admin.reports.financial-summary', $query),
            route('admin.matches.index', $query),
            route('admin.matches.show', $done),
            route('public.matches.scorecard.pdf', $done),
        ] as $link) {
            $response->assertSee($link, false);
        }
    }

    // ----- Dashboard -----

    public function test_the_dashboard_lists_matches_as_plain_text_and_links_nothing_the_role_cannot_open(): void
    {
        [, $live, $done] = $this->seasonWithMatches();

        $response = $this->actingAs($this->userWith('dashboard.tournament'))->get(route('admin.dashboard'))->assertOk();

        // The matches are still listed...
        $response->assertSee('Alpha Strikers vs Beta Blasters')->assertSee('Alpha Strikers won by 10 runs');
        // ...but not linked, and nothing else on the page points into a module the role cannot open.
        foreach ([
            route('admin.matches.show', $live),
            route('admin.matches.show', $done),
            route('admin.player-registrations.index'),
            route('admin.edition-transactions.index'),
            route('admin.edition-contributions.index'),
            route('admin.reports.index'),
        ] as $link) {
            $response->assertDontSee($link, false);
        }
    }

    public function test_the_dashboard_finance_block_does_not_link_to_registrations_or_reports_without_their_permissions(): void
    {
        [$edition, $live] = $this->seasonWithMatches();
        $registrationLinks = [
            route('admin.player-registrations.index'),
            'awaiting payment verification',
        ];
        $reports = e(route('admin.reports.index', ['edition_id' => $edition->id]));
        $ledger = e(route('admin.edition-transactions.index', ['edition_id' => $edition->id]));
        $contributions = e(route('admin.edition-contributions.index', ['edition_id' => $edition->id]));

        // finance.view shows the payment, ledger and contribution blocks (and links to the ledger and
        // the contributions, which finance.view opens) but registrations and reports are other modules.
        $response = $this->actingAs($this->userWith('dashboard.tournament', 'finance.view'))->get(route('admin.dashboard'))->assertOk();

        $response->assertSee('Registration Payments')->assertSee('95,432.00')->assertSee($ledger, false)->assertSee($contributions, false);
        foreach ([...$registrationLinks, $reports] as $absent) {
            $response->assertDontSee($absent, false);
        }

        // With those permissions the same links are there.
        $response = $this->actingAs($this->userWith('dashboard.tournament', 'finance.view', 'registrations.view', 'reports.view', 'matches.view'))
            ->get(route('admin.dashboard'))
            ->assertOk();

        $response->assertSee(e(route('admin.player-registrations.index', ['edition_id' => $edition->id, 'payment_status' => 'pending'])), false)
            ->assertSee(e(route('admin.player-registrations.index', ['edition_id' => $edition->id])), false)
            ->assertSee('awaiting payment verification')
            ->assertSee($reports, false)
            ->assertSee(route('admin.matches.show', $live), false);
    }

    public function test_the_scorer_dashboard_still_links_its_matches_and_nothing_else(): void
    {
        [, $live, $done] = $this->seasonWithMatches();

        $response = $this->actingAs($this->builtIn('scorer'))->get(route('admin.dashboard'))->assertOk();

        $response->assertSee(route('admin.matches.show', $live), false)->assertSee(route('admin.matches.show', $done), false);
        foreach ([route('admin.player-registrations.index'), route('admin.edition-transactions.index'), route('admin.reports.index'), '95,432.00'] as $absent) {
            $response->assertDontSee($absent, false);
        }
    }

    // ----- Finance tab bar -----

    public function test_the_finance_tab_bar_only_offers_the_tabs_the_role_may_open(): void
    {
        $overview = route('admin.finance.overview');
        $committee = route('admin.finance.committee');

        // Contributors alone: none of the finance pages is offered (none would open).
        $response = $this->actingAs($this->userWith('contributors.view'))->get(route('admin.contributors.index'))->assertOk();
        foreach ([$overview, $committee, route('admin.edition-contributions.index'), route('admin.edition-transactions.index')] as $absent) {
            $response->assertDontSee($absent, false);
        }

        // The committee has no sidebar entry, so its link only ever comes from the tab bar.
        $this->actingAs($this->userWith('contributors.view', 'finance.view'))->get(route('admin.contributors.index'))
            ->assertOk()->assertSee($overview, false)->assertDontSee($committee, false);
        $this->actingAs($this->userWith('contributors.view', 'finance.view', 'committee.view'))->get(route('admin.contributors.index'))
            ->assertOk()->assertSee($committee, false);
        $this->actingAs($this->builtIn('admin'))->get(route('admin.contributors.index'))
            ->assertOk()->assertSee($committee, false)->assertSee($overview, false);
    }

    public function test_contributor_names_are_links_only_for_a_role_that_may_open_contributors(): void
    {
        $edition = $this->seasonWithMoney();
        $contribution = EditionContribution::firstOrFail();
        $contributorPage = route('admin.contributors.show', $contribution->contributor);

        $this->actingAs($this->userWith('finance.view'))->get(route('admin.edition-contributions.index', ['edition_id' => $edition->id]))
            ->assertOk()->assertSee($contribution->contributorName())->assertDontSee($contributorPage, false);
        $this->actingAs($this->userWith('finance.view', 'contributors.view'))->get(route('admin.edition-contributions.index', ['edition_id' => $edition->id]))
            ->assertOk()->assertSee($contributorPage, false);
    }

    // ----- Inside a season -----

    public function test_season_pages_do_not_link_to_the_editions_or_teams_pages_the_role_cannot_open(): void
    {
        $empty = Edition::factory()->create(['name' => 'RPPL Quiet Cup', 'status' => 'upcoming', 'year' => 2061]);
        [$edition, , , $editionTeam] = $this->seasonWithMatches();

        // A role that may see squads but not editions: the breadcrumb is plain text, and the empty
        // state does not send it to the Teams page.
        $response = $this->actingAs($this->userWith('teams.view'))->get(route('admin.editions.squads.index', $empty))->assertOk();
        $response->assertSee('RPPL Quiet Cup')
            ->assertDontSee(route('admin.editions.index'), false)
            ->assertDontSee(route('admin.editions.show', $empty), false)
            ->assertDontSee(route('admin.editions.teams.index', $empty), false);

        // A role that may see a season's teams but not squads: the team name is not a link to its squad.
        $squad = route('admin.editions.squads.show', [$edition, $editionTeam]);
        $this->actingAs($this->userWith('editions.view'))->get(route('admin.editions.teams.index', $edition))
            ->assertOk()->assertSee('Alpha Strikers')->assertDontSee($squad, false);
        $this->actingAs($this->userWith('editions.view', 'teams.view'))->get(route('admin.editions.teams.index', $edition))
            ->assertOk()->assertSee($squad, false);

        // The admin keeps all of them.
        $this->actingAs($this->builtIn('admin'))->get(route('admin.editions.squads.index', $empty))
            ->assertOk()->assertSee(route('admin.editions.show', $empty), false)->assertSee(route('admin.editions.teams.index', $empty), false);
    }
}
