<?php

namespace Tests\Feature\Settings;

use App\Services\Settings\DisplayTimezoneFormatter;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Phase 3.44B3 — display_datetime()/DisplayTimezoneFormatter convert a
 * stored (UTC) timestamp for DISPLAY only, in system.display_timezone.
 * Proves the conversion is correct for two different configured zones
 * and — critically — that doing so never mutates the application's
 * actual runtime timezone (config('app.timezone') stays 'UTC', and a
 * freshly-created Carbon instance is still UTC afterward).
 */
class DisplayTimezoneFormatterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml runs the `array` cache driver, which persists for
        // the lifetime of the test process — RefreshDatabase resets the
        // settings TABLE between tests, but not SettingsService's own
        // cache entry, so it must be cleared explicitly here too.
        app(SettingsService::class)->flush();
    }

    public function test_a_stored_utc_timestamp_formats_correctly_for_asia_kolkata(): void
    {
        // Asia/Kolkata is the registry default — no explicit set() needed.
        $utc = Carbon::create(2026, 6, 15, 18, 30, 0, 'UTC');

        $formatted = display_datetime($utc, 'd M Y, h:i A');

        // UTC+5:30 — 18:30 UTC becomes 00:00 the next day in Kolkata.
        $this->assertSame('16 Jun 2026, 12:00 AM', $formatted);
    }

    public function test_the_same_timestamp_formats_differently_for_a_different_configured_timezone(): void
    {
        app(SettingsService::class)->set('system.display_timezone', 'America/New_York');

        $utc = Carbon::create(2026, 6, 15, 18, 30, 0, 'UTC');

        // UTC-4 (EDT in June) — 18:30 UTC becomes 14:30 the same day.
        $this->assertSame('15 Jun 2026, 02:30 PM', display_datetime($utc, 'd M Y, h:i A'));
    }

    public function test_null_datetime_returns_null(): void
    {
        $this->assertNull(display_datetime(null, 'd M Y'));
    }

    public function test_formatting_for_display_never_mutates_the_original_carbon_instance(): void
    {
        $utc = Carbon::create(2026, 6, 15, 18, 30, 0, 'UTC');

        display_datetime($utc, 'd M Y, h:i A');

        $this->assertSame('UTC', $utc->getTimezone()->getName());
    }

    public function test_changing_the_display_timezone_setting_does_not_change_the_application_runtime_timezone(): void
    {
        app(SettingsService::class)->set('system.display_timezone', 'America/New_York');

        display_datetime(Carbon::now(), 'd M Y');

        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('UTC', Carbon::now()->getTimezone()->getName());
    }

    public function test_formatter_service_is_resolvable_directly_and_behaves_the_same_as_the_helper(): void
    {
        $utc = Carbon::create(2026, 1, 1, 0, 0, 0, 'UTC');

        $viaService = app(DisplayTimezoneFormatter::class)->format($utc, 'd M Y, h:i A');
        $viaHelper = display_datetime($utc, 'd M Y, h:i A');

        $this->assertSame($viaHelper, $viaService);
    }
}
