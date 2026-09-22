<?php

namespace Tests\Feature\Admin;

use App\Models\Announcement;
use App\Models\Role;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3.45 — admin CRUD for public-ticker announcements. Proves
 * authorization (AnnouncementPolicy), the create/update/delete flow,
 * Unicode/emoji support, and the display-timezone round-trip for
 * starts_at/ends_at (DisplayTimezoneFormatter).
 */
class AnnouncementManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $scorerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->scorerRole = Role::create(['name' => 'Scorer', 'slug' => 'scorer']);

        // phpunit.xml runs the `array` cache driver, which persists for
        // the lifetime of the test process — RefreshDatabase resets the
        // settings TABLE between tests, but not SettingsService's own
        // cache entry, so it must be cleared explicitly here too.
        app(SettingsService::class)->flush();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    private function scorer(): User
    {
        return User::factory()->create(['role_id' => $this->scorerRole->id]);
    }

    // ----- Authorization -----

    public function test_admin_can_access_announcement_management(): void
    {
        $this->actingAs($this->admin())->get(route('admin.announcements.index'))->assertOk();
        $this->actingAs($this->admin())->get(route('admin.announcements.create'))->assertOk();
    }

    public function test_scorer_cannot_manage_announcements(): void
    {
        $scorer = $this->scorer();
        $announcement = Announcement::factory()->create();

        $this->actingAs($scorer)->get(route('admin.announcements.index'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.announcements.create'))->assertForbidden();
        $this->actingAs($scorer)->post(route('admin.announcements.store'), ['message' => 'Hacked'])->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.announcements.edit', $announcement))->assertForbidden();
        $this->actingAs($scorer)->put(route('admin.announcements.update', $announcement), ['message' => 'Hacked'])->assertForbidden();
        $this->actingAs($scorer)->delete(route('admin.announcements.destroy', $announcement))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.announcements.index'))->assertRedirect(route('admin.login'));
    }

    // ----- Create -----

    public function test_admin_can_create_an_announcement(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.announcements.store'), [
            'message' => 'Final match on 1 January',
            'is_active' => '1',
            'sort_order' => 5,
        ]);

        $response->assertRedirect(route('admin.announcements.index'));
        $this->assertDatabaseHas('announcements', [
            'message' => 'Final match on 1 January',
            'is_active' => 1,
            'sort_order' => 5,
        ]);
    }

    public function test_message_supports_unicode_and_emoji(): void
    {
        $message = '🏏 Final Match — 1 January 📢';

        $this->actingAs($this->admin())->post(route('admin.announcements.store'), [
            'message' => $message,
            'is_active' => '1',
        ]);

        $this->assertDatabaseHas('announcements', ['message' => $message]);
    }

    public function test_end_before_start_validation_fails(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.announcements.store'), [
            'message' => 'Bad window',
            'starts_at' => '2026-06-10T10:00',
            'ends_at' => '2026-06-09T10:00',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('ends_at');
        $this->assertDatabaseMissing('announcements', ['message' => 'Bad window']);
    }

    public function test_starts_at_is_stored_converted_from_the_display_timezone_to_utc(): void
    {
        app(SettingsService::class)->set('system.display_timezone', 'Asia/Kolkata');

        $this->actingAs($this->admin())->post(route('admin.announcements.store'), [
            'message' => 'Timezone check',
            'starts_at' => '2026-06-10T19:00',
            'is_active' => '1',
        ]);

        $announcement = Announcement::where('message', 'Timezone check')->firstOrFail();

        // 19:00 IST (UTC+5:30) is 13:30 UTC the same day.
        $this->assertSame('2026-06-10 13:30:00', $announcement->starts_at->format('Y-m-d H:i:s'));
    }

    // ----- Update -----

    public function test_admin_can_update_an_announcement(): void
    {
        $announcement = Announcement::factory()->create(['message' => 'Old message', 'sort_order' => 0]);

        $response = $this->actingAs($this->admin())->put(route('admin.announcements.update', $announcement), [
            'message' => 'Updated message',
            'is_active' => '1',
            'sort_order' => 9,
        ]);

        $response->assertRedirect(route('admin.announcements.index'));
        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'message' => 'Updated message',
            'sort_order' => 9,
        ]);
    }

    public function test_admin_can_disable_an_announcement(): void
    {
        $announcement = Announcement::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())->put(route('admin.announcements.update', $announcement), [
            'message' => $announcement->message,
            'is_active' => '0',
        ]);

        $this->assertFalse($announcement->fresh()->is_active);
    }

    // ----- Delete -----

    public function test_admin_can_delete_an_announcement(): void
    {
        $announcement = Announcement::factory()->create();

        $response = $this->actingAs($this->admin())->delete(route('admin.announcements.destroy', $announcement));

        $response->assertRedirect(route('admin.announcements.index'));
        $this->assertModelMissing($announcement);
    }

    // ----- Message safety -----

    public function test_raw_html_in_message_is_stored_as_plain_text(): void
    {
        $this->actingAs($this->admin())->post(route('admin.announcements.store'), [
            'message' => '<script>alert(1)</script>',
            'is_active' => '1',
        ]);

        $this->assertDatabaseHas('announcements', ['message' => '<script>alert(1)</script>']);
    }

    // ----- Admin index -----

    public function test_admin_index_shows_derived_status_badges(): void
    {
        Announcement::factory()->create(['message' => 'Disabled one', 'is_active' => false]);
        Announcement::factory()->create(['message' => 'Scheduled one', 'starts_at' => now()->addDay()]);
        Announcement::factory()->create(['message' => 'Expired one', 'ends_at' => now()->subDay()]);
        Announcement::factory()->create(['message' => 'Active one']);

        $response = $this->actingAs($this->admin())->get(route('admin.announcements.index'));

        $response->assertOk();
        $response->assertSee('disabled');
        $response->assertSee('scheduled');
        $response->assertSee('expired');
        $response->assertSee('active');
    }

    // ----- Sidebar -----

    public function test_announcements_nav_item_is_visible_to_admin_and_hidden_from_scorer(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Announcements');

        $this->actingAs($this->scorer())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Announcements');
    }
}
