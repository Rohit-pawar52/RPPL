<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Admin sidebar: grouped "Management" sections driven by
 * config/admin_navigation.php, route-name based active states,
 * permission-aware visibility and the topbar breadcrumb.
 */
class AdminNavigationTest extends TestCase
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
     * Whether the page marks $routeName's link as the current page.
     */
    private function isMarkedCurrent(string $html, string $routeName): bool
    {
        return (bool) preg_match('#href="'.preg_quote(route($routeName), '#').'"(?:\s+data-tip="[^"]*")?\s+aria-current="page"#', $html);
    }

    public function test_every_configured_navigation_route_exists_and_is_listed_once(): void
    {
        $config = config('admin_navigation');
        $routes = collect($config['top'])
            ->merge(collect($config['groups'])->flatMap(fn ($group) => $group['items']))
            ->pluck('route');

        foreach ($routes as $name) {
            $this->assertTrue(Route::has($name), "Navigation points at a missing route: {$name}");
        }

        $this->assertSame($routes->unique()->count(), $routes->count(), 'A page is listed twice in the navigation.');
    }

    public function test_admin_sees_every_management_group_and_its_pages(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk()->getContent();

        foreach (['Tournament Management', 'Player Management', 'Content Management', 'Finance Management', 'Communication', 'System Management'] as $group) {
            $this->assertStringContainsString($group, $html);
        }

        foreach ([
            'admin.dashboard', 'admin.matches.index', 'admin.reports.index',
            'admin.editions.index', 'admin.teams.index', 'admin.venues.index',
            'admin.players.index', 'admin.player-registrations.index',
            'admin.news.index', 'admin.videos.index', 'admin.photos.index', 'admin.announcements.index', 'admin.rules.index', 'admin.content-pages.index',
            'admin.finance.overview', 'admin.edition-transactions.index', 'admin.edition-contributions.index', 'admin.contributors.index',
            'admin.notifications.index', 'admin.users.index', 'admin.settings.index', 'admin.data-cleanup.index',
        ] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $html, $route);
        }

        // Season teams and squads are opened from the Edition hub, not the sidebar.
        foreach (['admin.edition-teams.index', 'admin.team-players.index'] as $route) {
            $this->assertStringNotContainsString('href="'.route($route).'"', $html, $route);
        }
    }

    public function test_the_older_edition_team_and_squad_pages_still_highlight_editions(): void
    {
        foreach (['admin.edition-teams.index', 'admin.team-players.index'] as $route) {
            $html = $this->actingAs($this->admin())->get(route($route))->assertOk()->getContent();

            $this->assertMatchesRegularExpression('#aria-current="page"[^>]*>\s*(?:<[^>]+>\s*)*Editions#', $html, $route);
        }
    }

    public function test_a_scorer_only_sees_links_they_can_use(): void
    {
        $html = $this->actingAs($this->scorer())->get(route('admin.matches.index'))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('admin.dashboard').'"', $html);
        $this->assertStringContainsString('href="'.route('admin.matches.index').'"', $html);

        foreach (['Tournament Management', 'Player Management', 'Content Management', 'Finance Management', 'System Management'] as $group) {
            $this->assertStringNotContainsString($group, $html);
        }

        foreach (['admin.reports.index', 'admin.players.index', 'admin.videos.index', 'admin.settings.index', 'admin.data-cleanup.index', 'admin.users.index'] as $route) {
            $this->assertStringNotContainsString('href="'.route($route).'"', $html, $route);
        }
    }

    public function test_the_current_page_and_its_group_are_marked_active_even_on_child_pages_and_with_a_query_string(): void
    {
        $admin = $this->admin();

        // A create page and a filtered list both keep their module current.
        $html = $this->actingAs($admin)->get(route('admin.videos.create'))->assertOk()->getContent();
        $this->assertTrue($this->isMarkedCurrent($html, 'admin.videos.index'));
        $this->assertFalse($this->isMarkedCurrent($html, 'admin.photos.index'));
        $this->assertMatchesRegularExpression('#<details class="adm-group" data-group="content"\s+open#', $html);
        $this->assertDoesNotMatchRegularExpression('#data-group="finance"\s+open#', $html);

        $html = $this->actingAs($admin)->get(route('admin.players.index', ['search' => 'abc', 'page' => 2]))->getContent();
        $this->assertTrue($this->isMarkedCurrent($html, 'admin.players.index'));
        $this->assertMatchesRegularExpression('#data-group="players"\s+open#', $html);
    }

    public function test_one_entry_covers_related_route_families(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.rule-types.index'))->assertOk()->getContent();

        // Rule Types has no link of its own: it highlights Rules & Regulations.
        $this->assertTrue($this->isMarkedCurrent($html, 'admin.rules.index'));
    }

    public function test_top_level_pages_are_marked_current(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.dashboard'))->getContent();
        $this->assertTrue($this->isMarkedCurrent($html, 'admin.dashboard'));
        $this->assertFalse($this->isMarkedCurrent($html, 'admin.matches.index'));

        $html = $this->actingAs($this->admin())->get(route('admin.matches.index'))->getContent();
        $this->assertTrue($this->isMarkedCurrent($html, 'admin.matches.index'));
    }

    public function test_the_topbar_shows_the_group_and_page_and_the_account_menu(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.finance.overview'))
            ->assertOk()
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Finance Management', 'Overview'], false)
            ->assertSee($admin->name)
            ->assertSee(route('admin.logout'), false)
            ->assertSee('View site');
    }

    public function test_pages_can_set_a_subtitle_and_actions_in_the_shared_page_header(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.videos.index'))
            ->assertOk()
            ->assertSee(route('admin.videos.create'), false);
    }
}
