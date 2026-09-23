<?php

namespace Tests\Feature\Public;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B2 (data foundation) + Phase 3.47 (soft opt-in UX: bell badge,
 * soft prompt, 7-day "Not Now" cooldown) — proves the public
 * notification control/soft prompt and their JS module are wired into
 * the public layout, and ONLY the public layout. Actual browser
 * behavior itself (permission prompts, getToken(), localStorage
 * cooldown timing, the service worker) is not something PHPUnit can
 * exercise — that is covered by manual real-browser verification, not
 * here (see the Phase 3.47 report). B1's tests already prove backend
 * persistence/upsert behavior; this file does not repeat them.
 */
class PushNotificationControlTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    public function test_public_header_renders_a_bell_badge_instead_of_the_old_text_button(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('id="fcm-subscribe-button"', $content);
        $this->assertStringContainsString('aria-label="Enable notifications"', $content);
        $this->assertStringContainsString('data-state="default"', $content);

        // The old large text button is gone.
        $this->assertStringNotContainsString('Enable Notifications</button>', $content);

        // Starts hidden — resources/js/push-notifications.js is the only
        // thing that ever reveals it, and only after confirming the
        // browser/Firebase config are actually usable.
        $this->assertStringContainsString('class="hidden rounded-md p-1.5', $content);
    }

    public function test_public_home_page_includes_the_soft_prompt_markup(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('id="rppl-push-soft-prompt"', false);
        $response->assertSee('Never miss an update from RPPL');
        $response->assertSee('id="rppl-push-soft-prompt-enable"', false);
        $response->assertSee('id="rppl-push-soft-prompt-dismiss"', false);

        // Hidden by default — only the JS timer/eligibility logic ever
        // reveals it, never rendered visible server-side.
        $this->assertMatchesRegularExpression(
            '/id="rppl-push-soft-prompt"\s+class="hidden/',
            $response->getContent()
        );
    }

    public function test_public_layout_loads_the_push_notifications_script_module(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        $this->assertStringContainsString('push-notifications', $response->getContent());
    }

    public function test_home_page_never_renders_a_token_or_owner_identifier(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertDontSee('fcm_token', false);
        $response->assertDontSee('firebaseToken', false);
    }

    public function test_admin_pages_do_not_render_the_bell_or_soft_prompt(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('id="fcm-subscribe-button"', false);
        $response->assertDontSee('id="rppl-push-soft-prompt"', false);
    }

    public function test_login_page_does_not_render_the_bell_or_soft_prompt(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertOk();
        $response->assertDontSee('id="fcm-subscribe-button"', false);
        $response->assertDontSee('id="rppl-push-soft-prompt"', false);
    }

    public function test_maintenance_page_does_not_render_the_bell_or_soft_prompt(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('admin.settings.system.update'), [
            'maintenance_mode' => '1',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'display_timezone' => 'Asia/Kolkata',
            'committee_minimum_contribution' => '1000',
        ]);

        $response = $this->get(route('public.home'));

        $response->assertStatus(503);
        $response->assertDontSee('id="fcm-subscribe-button"', false);
        $response->assertDontSee('id="rppl-push-soft-prompt"', false);
    }

    /**
     * A static public/ asset, not a Laravel route — served directly by
     * the webserver in production, so the meaningful check here is that
     * the file exists on disk, not an HTTP round-trip through the test
     * client's router (which has no route for it and would 404
     * regardless of whether the file is actually servable). Phase 3.47
     * deliberately never modifies this file — the opt-in UX change is
     * entirely in push-notifications.js/the public layout.
     */
    public function test_service_worker_file_exists(): void
    {
        $this->assertFileExists(public_path('firebase-messaging-sw.js'));
    }
}
