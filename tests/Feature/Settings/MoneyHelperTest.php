<?php

namespace Tests\Feature\Settings;

use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pre-UAT polish — money(), the single call site templates use instead
 * of hardcoding '₹' directly. Formatting only: proves the configured
 * system.currency_symbol is honored, the registry default renders
 * identically to the previously-hardcoded '₹', and a setting change is
 * reflected on the very next call (SettingsService's own cache
 * invalidation, no second cache).
 */
class MoneyHelperTest extends TestCase
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

    public function test_default_currency_symbol_matches_the_previously_hardcoded_rupee_sign(): void
    {
        $this->assertSame('₹1,234.50', money(1234.5));
    }

    public function test_configured_currency_symbol_is_honored(): void
    {
        app(SettingsService::class)->set('system.currency_symbol', '$');

        $this->assertSame('$1,234.50', money(1234.5));
    }

    public function test_changing_the_currency_symbol_is_reflected_on_the_next_call(): void
    {
        $this->assertSame('₹0.00', money(0));

        app(SettingsService::class)->set('system.currency_symbol', '€');

        $this->assertSame('€0.00', money(0));
    }

    public function test_null_amount_formats_as_zero(): void
    {
        $this->assertSame('₹0.00', money(null));
    }

    public function test_custom_decimal_places_are_respected(): void
    {
        $this->assertSame('₹1,000', money(1000, 0));
    }

    public function test_a_configured_symbol_is_html_escaped_in_blade_output(): void
    {
        app(SettingsService::class)->set('system.currency_symbol', '<b>');

        $rendered = e(money(5));

        $this->assertStringNotContainsString('<b>', $rendered);
        $this->assertStringContainsString('&lt;b&gt;', $rendered);
    }
}
