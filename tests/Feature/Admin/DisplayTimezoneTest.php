<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\GameMatch;
use App\Models\Notification;
use App\Models\NotificationSend;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Times are stored in UTC and shown in system.display_timezone (India by
 * default, UTC+5:30). Every example here is the same instant: 12:20 AM on
 * 3 October 2026 in India, which is 18:50 on 2 October in UTC — the case
 * where showing the stored UTC value gets the DATE wrong, not just the
 * hour. DisplayTimezoneConventionTest keeps the views honest; this file
 * proves the behaviour on the screens, exports and filters that matter.
 */
class DisplayTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private const UTC = '2026-10-02 18:50:00';

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        app(SettingsService::class)->flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    // ----- Player registrations -----

    public function test_registration_screens_show_the_time_in_the_display_timezone(): void
    {
        $registration = PlayerRegistration::factory()->create(['registered_at' => self::UTC]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.player-registrations.show', $registration))
            ->assertOk()
            ->assertSee('03 Oct 2026, 12:20 AM')
            ->assertDontSee('02 Oct 2026')
            ->assertDontSee('06:50 PM');

        $this->actingAs($admin)->get(route('admin.player-registrations.index'))
            ->assertOk()
            ->assertSee('03 Oct 2026')
            ->assertDontSee('02 Oct 2026');

        $this->actingAs($admin)->get(route('admin.player-registrations.edit', $registration))
            ->assertOk()
            ->assertSee('value="2026-10-03T00:20"', false)
            ->assertSee('Time in Asia/Kolkata.');
    }

    public function test_the_screens_follow_a_changed_display_timezone(): void
    {
        $registration = PlayerRegistration::factory()->create(['registered_at' => self::UTC]);
        app(SettingsService::class)->set('system.display_timezone', 'America/New_York');

        // 18:50 UTC is 2:50 PM in New York (UTC-4 in October), same day.
        $this->actingAs($this->admin())->get(route('admin.player-registrations.show', $registration))
            ->assertSee('02 Oct 2026, 02:50 PM')
            ->assertDontSee('03 Oct 2026');
    }

    public function test_the_registered_at_form_reads_display_timezone_times_and_stores_utc(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $player = Player::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.player-registrations.store'), [
            'edition_id' => $edition->id,
            'player_id' => $player->id,
            'payment_status' => 'pending',
            'registered_at' => '2026-10-03T00:20',
        ])->assertSessionHasNoErrors();

        $registration = PlayerRegistration::where('player_id', $player->id)->sole();
        $this->assertSame(self::UTC, $registration->registered_at->format('Y-m-d H:i:s'));

        // Editing and saving the value the form shows never moves the time.
        $update = fn (string $value) => $this->actingAs($admin)->put(route('admin.player-registrations.update', $registration), [
            'payment_status' => 'pending',
            'registered_at' => $value,
        ])->assertSessionHasNoErrors();

        $update('2026-10-03T08:05');
        $update('2026-10-03T08:05');
        $this->assertSame('2026-10-03 02:35:00', $registration->fresh()->registered_at->format('Y-m-d H:i:s'));

        // A plain date means that day's start in the display timezone.
        $update('2026-01-05');
        $this->assertSame('2026-01-04 18:30:00', $registration->fresh()->registered_at->format('Y-m-d H:i:s'));
        $this->actingAs($admin)->get(route('admin.player-registrations.show', $registration))->assertSee('05 Jan 2026, 12:00 AM');

        $update('');
        $this->assertNull($registration->fresh()->registered_at);
    }

    public function test_an_imported_google_sheet_time_reads_the_same_on_the_registration_page(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $admin = $this->admin();

        // The sheet says 12:20 AM on 3 October (day/month/year, India time).
        $this->actingAs($admin)->post(route('admin.player-registrations.import.store'), [
            'edition_id' => $edition->id,
            'csv_file' => UploadedFile::fake()->createWithContent('responses.csv', "Timestamp,Name\n03/10/2026 00:20:00,Midnight Player\n"),
        ])->assertSessionHasNoErrors();

        $registration = PlayerRegistration::sole();
        $this->assertSame(self::UTC, $registration->registered_at->format('Y-m-d H:i:s'));

        $this->actingAs($admin)->get(route('admin.player-registrations.show', $registration))
            ->assertSee('03 Oct 2026, 12:20 AM');
    }

    public function test_the_registration_date_filter_uses_display_timezone_days(): void
    {
        // 12:20 AM, 11:59:59 PM and 12:00 AM (the next day) in India.
        foreach (['Alpha Filter' => '2026-10-02 18:50:00', 'Bravo Filter' => '2026-10-03 18:29:59', 'Charlie Filter' => '2026-10-03 18:30:00'] as $name => $at) {
            PlayerRegistration::factory()->create([
                'player_id' => Player::factory()->create(['name' => $name]),
                'registered_at' => $at,
            ]);
        }

        $admin = $this->admin();
        $list = fn (array $query) => $this->actingAs($admin)->get(route('admin.player-registrations.index', $query))->assertOk();

        // "3 October" is the whole day in India: the first two, not the third.
        $list(['from_date' => '2026-10-03', 'to_date' => '2026-10-03'])
            ->assertSee('Alpha Filter')->assertSee('Bravo Filter')->assertDontSee('Charlie Filter');

        // Nothing was registered on 2 October by the clock on the wall.
        $list(['to_date' => '2026-10-02'])
            ->assertDontSee('Alpha Filter')->assertDontSee('Bravo Filter')->assertDontSee('Charlie Filter');

        $list(['from_date' => '2026-10-04'])
            ->assertSee('Charlie Filter')->assertDontSee('Alpha Filter');
    }

    public function test_the_registration_export_and_the_public_status_page_show_the_display_timezone_date(): void
    {
        $player = Player::factory()->create(['phone' => '9876543210']);
        $registration = PlayerRegistration::factory()->create(['player_id' => $player->id, 'registered_at' => self::UTC])
            ->assignRegistrationNumber();

        $csv = $this->actingAs($this->admin())->get(route('admin.player-registrations.export'))->streamedContent();
        $this->assertStringContainsString('2026-10-03', $csv);
        $this->assertStringNotContainsString('2026-10-02', $csv);

        auth()->logout();

        $this->post(route('public.player-registration.status.lookup'), [
            'registration_number' => $registration->registration_number,
            'phone' => '9876543210',
        ])->assertOk()->assertSee('03 Oct 2026')->assertDontSee('02 Oct 2026');
    }

    // ----- Matches -----

    public function test_match_times_export_and_filter_use_the_display_timezone(): void
    {
        $match = GameMatch::factory()->create([
            'scheduled_at' => self::UTC,
            'match_status' => 'completed',
            'started_at' => self::UTC,
            'completed_at' => '2026-10-02 19:50:00',
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.matches.show', $match))
            ->assertOk()
            ->assertSee('03 Oct 2026, 12:20 AM')
            ->assertSee('03 Oct 2026, 01:20 AM');

        $csv = $this->actingAs($admin)->get(route('admin.matches.export'))->streamedContent();
        $this->assertStringContainsString('2026-10-03 00:20', $csv);
        $this->assertStringNotContainsString('2026-10-02', $csv);

        $name = $match->teamA->team->name;
        $this->actingAs($admin)->get(route('admin.matches.index', ['from_date' => '2026-10-03', 'to_date' => '2026-10-03']))
            ->assertSee($name);
        $this->actingAs($admin)->get(route('admin.matches.index', ['to_date' => '2026-10-02']))
            ->assertDontSee($name);
    }

    // ----- Other admin screens -----

    public function test_notification_times_use_the_display_timezone_in_twelve_hour_format(): void
    {
        $notification = Notification::factory()->create(['created_at' => self::UTC, 'updated_at' => self::UTC]);
        NotificationSend::factory()->create([
            'notification_id' => $notification->id,
            'created_at' => self::UTC,
            'completed_at' => '2026-10-02 18:55:00',
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('03 Oct 2026')
            ->assertSee('03 Oct 2026, 12:20 AM');

        $this->actingAs($admin)->get(route('admin.notifications.show', $notification))
            ->assertOk()
            ->assertSee('03 Oct 2026, 12:20 AM')
            ->assertSee('03 Oct 2026, 12:25 AM')
            ->assertDontSee('02 Oct 2026')
            ->assertDontSee('00:20');
    }

    public function test_created_and_updated_dates_on_editions_and_users_use_the_display_timezone(): void
    {
        $edition = Edition::factory()->create(['created_at' => self::UTC, 'updated_at' => self::UTC]);
        $user = User::factory()->create(['role_id' => $this->adminRole->id, 'created_at' => self::UTC]);
        $admin = $this->admin();

        foreach ([route('admin.editions.index'), route('admin.users.show', $user)] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertSee('03 Oct 2026')->assertDontSee('02 Oct 2026');
        }
    }

    public function test_the_financial_summary_time_and_the_footer_year_use_the_display_timezone(): void
    {
        $edition = Edition::factory()->create();
        $admin = $this->admin();

        Carbon::setTestNow(self::UTC);
        $this->actingAs($admin)->get(route('admin.reports.financial-summary', ['edition_id' => $edition->id]))
            ->assertOk()
            ->assertSee('Generated 03 Oct 2026, 12:20 AM');

        // 8 PM on 31 December in UTC is already 1:30 AM on 1 January in India.
        Carbon::setTestNow('2026-12-31 20:00:00');
        $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk()->assertSee('&copy; 2027', false);
        auth()->logout();
        $this->get(route('public.home'))->assertOk()->assertSee('&copy; 2027', false);
    }
}
