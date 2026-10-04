<?php

namespace Tests\Feature\Auction;

use App\Models\Auction;
use App\Models\Edition;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who may run the auction: admin and auctioneer, both fully — and the
 * auctioneer gets nothing outside it (the way a scorer is limited to
 * matches). A scorer gets nothing in the auction.
 */
class AuctionAccessTest extends TestCase
{
    use RefreshDatabase;

    private Edition $edition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = Edition::factory()->create(['status' => 'active']);
    }

    private function userWithRole(string $slug): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_the_auctioneer_role_exists_after_the_migrations_and_the_seeder_does_not_duplicate_it(): void
    {
        $this->assertSame(1, Role::where('slug', 'auctioneer')->count());

        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertSame(1, Role::where('slug', 'auctioneer')->count());
        $this->assertSame(['admin', 'auctioneer', 'scorer'], Role::orderBy('slug')->pluck('slug')->all());
    }

    public function test_an_admin_can_give_someone_the_auctioneer_role(): void
    {
        $admin = $this->userWithRole('admin');
        $role = Role::where('slug', 'auctioneer')->first();

        $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()->assertSee('Auctioneer');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Hall Auctioneer',
            'email' => 'auctioneer@example.test',
            'role_id' => $role->id,
            'password' => 'a-long-password-1',
            'password_confirmation' => 'a-long-password-1',
        ])->assertSessionHasNoErrors();

        $this->assertSame($role->id, User::firstWhere('email', 'auctioneer@example.test')->role_id);
    }

    public function test_admin_and_auctioneer_both_open_every_auction_page_and_can_act(): void
    {
        foreach (['admin', 'auctioneer'] as $slug) {
            $user = $this->userWithRole($slug);

            $this->actingAs($user)->get(route('admin.auctions.index'))->assertOk();
            $this->actingAs($user)->get(route('admin.auctions.show', $this->edition))->assertOk();
        }

        $auctioneer = $this->userWithRole('auctioneer');
        $this->actingAs($auctioneer)
            ->post(route('admin.auctions.store', $this->edition), [
                'team_purse' => 600000, 'min_bid' => 500, 'bid_step' => 500, 'min_squad' => 12, 'max_squad' => 15, 'show_live_bids' => 1,
            ])
            ->assertRedirect(route('admin.auctions.show', $this->edition));

        $this->assertSame($auctioneer->id, Auction::first()->created_by);
    }

    public function test_a_scorer_and_a_guest_cannot_use_the_auction(): void
    {
        $scorer = $this->userWithRole('scorer');

        $this->actingAs($scorer)->get(route('admin.auctions.index'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.auctions.show', $this->edition))->assertForbidden();
        $this->actingAs($scorer)->post(route('admin.auctions.store', $this->edition), [
            'team_purse' => 600000, 'min_bid' => 500, 'bid_step' => 500, 'min_squad' => 12, 'max_squad' => 15,
        ])->assertForbidden();

        $this->assertSame(0, Auction::count());

        auth()->logout();
        $this->get(route('admin.auctions.index'))->assertRedirect(route('admin.login'));
    }

    public function test_the_auctioneer_gets_nothing_outside_the_auction(): void
    {
        $auctioneer = $this->userWithRole('auctioneer');

        foreach ([
            'admin.matches.index', 'admin.editions.index', 'admin.teams.index', 'admin.venues.index',
            'admin.players.index', 'admin.player-registrations.index', 'admin.news.index', 'admin.videos.index',
            'admin.advertisements.index', 'admin.settings.index', 'admin.finance.overview', 'admin.reports.index',
            'admin.analytics.index', 'admin.users.index', 'admin.data-cleanup.index', 'admin.notifications.index',
        ] as $route) {
            $this->actingAs($auctioneer)->get(route($route))->assertForbidden();
        }

        $this->actingAs($auctioneer)->get(route('admin.editions.squads.index', $this->edition))->assertForbidden();
    }

    public function test_the_sidebar_shows_each_role_only_what_it_can_use(): void
    {
        $auctioneerHtml = $this->actingAs($this->userWithRole('auctioneer'))->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('admin.auctions.index').'"', $auctioneerHtml);
        $this->assertStringContainsString('href="'.route('admin.dashboard').'"', $auctioneerHtml);
        $this->assertStringNotContainsString('href="'.route('admin.matches.index').'"', $auctioneerHtml);
        foreach (['Tournament Management', 'Player Management', 'Content Management', 'Finance Management', 'System Management'] as $group) {
            $this->assertStringNotContainsString($group, $auctioneerHtml);
        }

        // A scorer keeps Matches and does not get the Auction.
        $scorerHtml = $this->actingAs($this->userWithRole('scorer'))->get(route('admin.matches.index'))->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('admin.matches.index').'"', $scorerHtml);
        $this->assertStringNotContainsString('href="'.route('admin.auctions.index').'"', $scorerHtml);

        // An admin sees both.
        $adminHtml = $this->actingAs($this->userWithRole('admin'))->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('admin.auctions.index').'"', $adminHtml);
        $this->assertStringContainsString('href="'.route('admin.matches.index').'"', $adminHtml);
    }

    public function test_the_auctioneers_dashboard_is_the_auction_only(): void
    {
        $response = $this->actingAs($this->userWithRole('auctioneer'))->get(route('admin.dashboard'))->assertOk();

        $response->assertSee('Player auction');
        $response->assertSee($this->edition->name);
        $response->assertSee('Set up the auction');
        $response->assertSee(route('admin.auctions.show', $this->edition), false);
        // None of the match / registration / finance figures.
        foreach (['Registered Players', 'Matches Needing Attention', 'Recent Results', 'Registration Payments', 'Finance'] as $hidden) {
            $response->assertDontSee($hidden);
        }

        Auction::factory()->live()->create(['edition_id' => $this->edition->id]);
        $this->actingAs($this->userWithRole('auctioneer'))->get(route('admin.dashboard'))->assertSee('Open the auction')->assertSee('live');
    }

    public function test_the_edition_hub_links_to_the_auction_for_an_admin(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('admin.editions.show', $this->edition))
            ->assertOk()
            ->assertSee('Not set up')
            ->assertSee(route('admin.auctions.show', $this->edition), false);

        Auction::factory()->create(['edition_id' => $this->edition->id]);

        $this->actingAs($admin)->get(route('admin.editions.show', $this->edition))->assertSee('Draft');
    }
}
