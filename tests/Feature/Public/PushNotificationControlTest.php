<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B2 — proves the public notification control and its JS module
 * are wired into the public layout. Browser/Firebase behavior itself
 * (permission prompts, getToken(), the service worker) is not something
 * PHPUnit can exercise — that is covered by manual smoke testing, not
 * here (see the Phase B2 report). B1's tests already prove backend
 * persistence/upsert behavior; this file does not repeat them.
 */
class PushNotificationControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_home_page_includes_the_hidden_notification_control_for_guests(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('id="fcm-subscribe-button"', $content);

        // Starts hidden — resources/js/push-notifications.js is the only
        // thing that ever reveals it, and only after confirming the
        // browser/Firebase config are actually usable.
        $this->assertStringContainsString('class="hidden rounded-md', $content);
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

    /**
     * A static public/ asset, not a Laravel route — served directly by
     * the webserver in production, so the meaningful check here is that
     * the file exists on disk, not an HTTP round-trip through the test
     * client's router (which has no route for it and would 404
     * regardless of whether the file is actually servable).
     */
    public function test_service_worker_file_exists(): void
    {
        $this->assertFileExists(public_path('firebase-messaging-sw.js'));
    }
}
