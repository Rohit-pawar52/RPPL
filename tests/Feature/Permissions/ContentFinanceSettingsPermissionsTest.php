<?php

namespace Tests\Feature\Permissions;

use App\Jobs\SendNotificationJob;
use App\Models\ContentPage;
use App\Models\Edition;
use App\Models\EditionTransaction;
use App\Models\Notification;
use App\Models\NotificationSend;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Finance, website content, notifications, reports, analytics, settings, data cleanup and the
 * dashboard ask the signed-in user's role for a permission instead of for the 'admin' slug. These
 * tests use custom roles holding only the permission under test (plus panel.access, to get into
 * the panel at all) to prove that each permission opens exactly its own area, that
 * `<module>.manage` is needed to change something `<module>.view` only lets a role look at, and
 * that the built-in scorer still gets nothing outside matches and scoring.
 */
class ContentFinanceSettingsPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private int $customRoles = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // The array cache driver outlives RefreshDatabase's reset of the settings table.
        app(SettingsService::class)->flush();
    }

    /**
     * A user whose role holds panel.access plus exactly the given permissions.
     */
    private function userWith(string ...$permissions): User
    {
        $this->customRoles++;

        $role = Role::create(['name' => 'Custom '.$this->customRoles, 'slug' => 'custom-'.$this->customRoles]);
        $role->syncPermissions(['panel.access', ...$permissions]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function scorer(): User
    {
        return User::factory()->create(['role_id' => Role::create(['name' => 'Scorer', 'slug' => 'scorer'])->id]);
    }

    // ----- Settings -----

    public function test_settings_open_and_update_only_with_the_settings_manage_permission(): void
    {
        $settings = app(SettingsService::class);
        $before = $settings->get('contact.email');

        $outsider = $this->userWith('reports.view', 'finance.manage', 'news.manage');
        $this->actingAs($outsider)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($outsider)
            ->put(route('admin.settings.contact.update'), ['email' => 'outsider@rppl.test'])
            ->assertForbidden();
        $this->assertSame($before, $settings->get('contact.email'));

        $editor = $this->userWith('settings.manage');
        $this->actingAs($editor)->get(route('admin.settings.index'))->assertOk();
        $this->actingAs($editor)
            ->put(route('admin.settings.contact.update'), ['email' => 'contact@rppl.test'])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'contact']));
        $this->assertSame('contact@rppl.test', $settings->get('contact.email'));
    }

    // ----- Finance -----

    public function test_finance_view_opens_the_finance_screens_but_recording_a_transaction_needs_finance_manage(): void
    {
        $payload = [
            'edition_id' => Edition::factory()->create(['year' => 2051])->id,
            'type' => 'income',
            'category' => 'Sponsorship',
            'amount' => '1500',
            'transaction_date' => '2026-01-15',
        ];

        $viewer = $this->userWith('finance.view');
        $this->actingAs($viewer)->get(route('admin.finance.overview'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.edition-transactions.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.edition-transactions.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.edition-transactions.store'), $payload)->assertForbidden();
        $this->assertSame(0, EditionTransaction::count());

        // finance.manage includes looking at finance.
        $manager = $this->userWith('finance.manage');
        $this->actingAs($manager)->get(route('admin.finance.overview'))->assertOk();
        $this->actingAs($manager)
            ->post(route('admin.edition-transactions.store'), $payload)
            ->assertRedirect(route('admin.edition-transactions.index'));
        $this->assertSame(1, EditionTransaction::count());

        // Permissions of neighbouring modules open nothing here.
        $neighbour = $this->userWith('contributors.manage', 'committee.manage', 'reports.view');
        $this->actingAs($neighbour)->get(route('admin.finance.overview'))->assertForbidden();
        $this->actingAs($neighbour)->get(route('admin.edition-transactions.index'))->assertForbidden();
    }

    // ----- Website content and contributors -----

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function modulesWithViewAndManage(): array
    {
        return [
            'news' => ['news', 'admin.news.index', 'admin.news.create'],
            'photos' => ['photos', 'admin.photos.index', 'admin.photos.create'],
            'videos' => ['videos', 'admin.videos.index', 'admin.videos.create'],
            'rules' => ['rules', 'admin.rules.index', 'admin.rules.create'],
            'rule types (share the rules permissions)' => ['rules', 'admin.rule-types.index', 'admin.rule-types.create'],
            'announcements' => ['announcements', 'admin.announcements.index', 'admin.announcements.create'],
            'advertisements' => ['advertisements', 'admin.advertisements.index', 'admin.advertisements.create'],
            'contributors' => ['contributors', 'admin.contributors.index', 'admin.contributors.create'],
        ];
    }

    #[DataProvider('modulesWithViewAndManage')]
    public function test_viewing_and_managing_are_separate_permissions_in_each_module(string $module, string $index, string $create): void
    {
        $viewer = $this->userWith("{$module}.view");
        $this->actingAs($viewer)->get(route($index))->assertOk();
        $this->actingAs($viewer)->get(route($create))->assertForbidden();

        $manager = $this->userWith("{$module}.manage");
        $this->actingAs($manager)->get(route($index))->assertOk();
        $this->actingAs($manager)->get(route($create))->assertOk();

        // Some other area's permission opens neither.
        $outsider = $this->userWith('settings.manage');
        $this->actingAs($outsider)->get(route($index))->assertForbidden();
        $this->actingAs($outsider)->get(route($create))->assertForbidden();
    }

    public function test_content_pages_need_the_content_pages_manage_permission(): void
    {
        ContentPage::factory()->create(['type' => ContentPage::TYPE_PRIVACY_POLICY]);

        $this->actingAs($this->userWith('news.manage', 'settings.manage'))
            ->get(route('admin.content-pages.index'))
            ->assertForbidden();

        $this->actingAs($this->userWith('content_pages.manage'))
            ->get(route('admin.content-pages.index'))
            ->assertOk();
    }

    // ----- Notifications -----

    public function test_notifications_manage_can_draft_but_only_notifications_send_can_broadcast(): void
    {
        Queue::fake();

        $drafter = $this->userWith('notifications.manage');
        $this->actingAs($drafter)
            ->post(route('admin.notifications.store'), ['title' => 'Match tomorrow', 'message' => 'See you at the ground.'])
            ->assertRedirect(route('admin.notifications.index'));
        $notification = Notification::firstWhere('title', 'Match tomorrow');
        $this->assertNotNull($notification);

        $this->actingAs($drafter)->post(route('admin.notifications.send', $notification))->assertForbidden();
        $this->assertSame(0, NotificationSend::count());
        Queue::assertNotPushed(SendNotificationJob::class);

        $sender = $this->userWith('notifications.send');
        $this->actingAs($sender)
            ->post(route('admin.notifications.send', $notification))
            ->assertRedirect(route('admin.notifications.show', $notification));
        $this->assertSame(1, NotificationSend::count());
        Queue::assertPushed(SendNotificationJob::class);

        // Being allowed to send is not being allowed to write.
        $this->actingAs($sender)
            ->post(route('admin.notifications.store'), ['title' => 'Sneaky', 'message' => 'Not allowed.'])
            ->assertForbidden();
        $this->assertNull(Notification::firstWhere('title', 'Sneaky'));
    }

    // ----- Reports, analytics, data cleanup -----

    public function test_reports_and_analytics_are_independent_permissions(): void
    {
        $reports = $this->userWith('reports.view');
        $this->actingAs($reports)->get(route('admin.reports.index'))->assertOk();
        $this->actingAs($reports)->get(route('admin.analytics.index'))->assertForbidden();

        // The Financial Summary is all money, so reports.view alone is not enough: it needs finance.view too.
        $this->actingAs($reports)->get(route('admin.reports.financial-summary'))->assertForbidden();
        $this->actingAs($this->userWith('reports.view', 'finance.view'))->get(route('admin.reports.financial-summary'))->assertOk();
        $this->actingAs($this->userWith('finance.view'))->get(route('admin.reports.financial-summary'))->assertForbidden();

        $analytics = $this->userWith('analytics.view');
        $this->actingAs($analytics)->get(route('admin.analytics.index'))->assertOk();
        $this->actingAs($analytics)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($analytics)->get(route('admin.reports.financial-summary'))->assertForbidden();
    }

    public function test_data_cleanup_needs_the_data_cleanup_manage_permission(): void
    {
        $old = Notification::factory()->create(['created_at' => now()->subDays(10)]);
        $cutoff = now()->subDays(5)->toDateString();
        $preview = route('admin.data-cleanup.preview.cutoff', ['category' => 'notifications', 'before_date' => $cutoff]);

        // Other powerful permissions are not enough, and nothing is deleted.
        $outsider = $this->userWith('settings.manage', 'notifications.manage', 'reports.view');
        $this->actingAs($outsider)->get(route('admin.data-cleanup.index'))->assertForbidden();
        $this->actingAs($outsider)->getJson($preview)->assertForbidden();
        $this->actingAs($outsider)
            ->delete(route('admin.data-cleanup.notifications.destroy'), ['before_date' => $cutoff])
            ->assertForbidden();
        $this->assertModelExists($old);

        $cleaner = $this->userWith('data_cleanup.manage');
        $this->actingAs($cleaner)->get(route('admin.data-cleanup.index'))->assertOk();
        $this->actingAs($cleaner)->getJson($preview)->assertOk()->assertJson(['count' => 1]);
        $this->actingAs($cleaner)
            ->delete(route('admin.data-cleanup.notifications.destroy'), ['before_date' => $cutoff])
            ->assertRedirect();
        $this->assertModelMissing($old);
    }

    // ----- Dashboard -----

    /**
     * An edition with distinctive figures, so a leak would show up as one of these exact strings.
     */
    private function editionWithDistinctiveFigures(): Edition
    {
        $edition = Edition::factory()->create(['name' => 'RPPL Hidden Cup', 'status' => 'active', 'year' => 2052]);

        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'paid', 'registration_fee' => 7351]);
        EditionTransaction::factory()->create(['edition_id' => $edition->id, 'type' => 'income', 'amount' => 48621]);

        return $edition;
    }

    public function test_a_role_with_only_panel_access_gets_the_plain_welcome_dashboard(): void
    {
        $this->editionWithDistinctiveFigures();

        $this->actingAs($this->userWith())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewIs('admin.dashboard.welcome')
            ->assertViewMissing('edition')
            ->assertSee('Use the sections in the sidebar')
            ->assertDontSee('RPPL Hidden Cup')
            ->assertDontSee('Registered Players')
            ->assertDontSee('48,621')
            ->assertDontSee('7351')
            ->assertDontSee('7,351');
    }

    public function test_the_scorer_and_auctioneer_dashboards_are_unchanged_and_finance_figures_follow_finance_view(): void
    {
        $this->editionWithDistinctiveFigures();

        // The scorer sees the tournament figures, never the money.
        $this->actingAs($this->scorer())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewIs('admin.dashboard.index')
            ->assertViewHas('canViewFinance', false)
            ->assertSee('Registered Players')
            ->assertDontSee('Registration Payments')
            ->assertDontSee('48,621')
            ->assertDontSee('7,351');

        // The auctioneer (a built-in role, present after the migrations) sees the auction alone.
        $auctioneer = User::factory()->create(['role_id' => Role::where('slug', 'auctioneer')->firstOrFail()->id]);
        $this->actingAs($auctioneer)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewIs('admin.dashboard.auctioneer')
            ->assertDontSee('Registered Players')
            ->assertDontSee('48,621');

        // Any role that may see the tournament dashboard sees the money once it holds finance.view.
        $accountant = $this->userWith('dashboard.tournament', 'finance.view');
        $this->actingAs($accountant)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewIs('admin.dashboard.index')
            ->assertViewHas('canViewFinance', true)
            ->assertSee('Registration Payments')
            ->assertSee('48,621');
    }

    // ----- The built-in scorer -----

    public function test_the_scorer_still_cannot_open_settings_finance_or_the_other_management_screens(): void
    {
        $scorer = $this->scorer();

        foreach ([
            'admin.settings.index',
            'admin.finance.overview',
            'admin.edition-transactions.index',
            'admin.contributors.index',
            'admin.reports.index',
            'admin.analytics.index',
            'admin.data-cleanup.index',
            'admin.notifications.index',
            'admin.content-pages.index',
            'admin.news.index',
            'admin.announcements.index',
            'admin.auctions.index',
        ] as $routeName) {
            $this->actingAs($scorer)->get(route($routeName))->assertForbidden();
        }
    }
}
