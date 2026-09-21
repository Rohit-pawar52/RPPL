<?php

namespace Tests\Feature\Settings;

use App\Models\Setting;
use App\Services\Settings\SettingsService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Phase 3.44B1 — the Settings data foundation. No admin UI/routes exist
 * yet; this proves SettingsService's own contract: registry defaults
 * with an empty table, persisted-value precedence, explicit-fallback
 * semantics, cache invalidation, the allow-list boundary, and the
 * encrypted-value boundary (never exposed via the generic accessor).
 */
class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private SettingsService $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = app(SettingsService::class);

        // phpunit.xml runs tests against the `array` cache driver, which
        // persists for the lifetime of the test process — RefreshDatabase
        // resets the settings TABLE between tests, but not this cache
        // entry, so it must be cleared explicitly here too.
        $this->settings->flush();
    }

    public function test_registry_defaults_are_returned_when_the_settings_table_is_empty(): void
    {
        $this->assertSame(0, Setting::count());

        $this->assertSame('RajaBhoj Pawar Premier League', $this->settings->get('general.application_name'));
        $this->assertSame('RPPL', $this->settings->get('general.short_name'));
        $this->assertFalse($this->settings->boolean('system.maintenance_mode'));
        $this->assertSame('INR', $this->settings->get('system.currency'));
        $this->assertSame('₹', $this->settings->get('system.currency_symbol'));
        $this->assertSame('Asia/Kolkata', $this->settings->get('system.display_timezone'));
    }

    public function test_a_persisted_value_overrides_the_registry_default(): void
    {
        $this->settings->set('general.application_name', 'Custom League Name');

        $this->assertSame('Custom League Name', $this->settings->get('general.application_name'));
    }

    /**
     * Disambiguates get()'s two-level fallback (Phase 3.44A's "avoid
     * ambiguous fallback semantics" requirement): an explicit caller
     * fallback wins over the registry default when nothing is
     * persisted; the registry default is only used when the caller
     * passes no fallback argument at all.
     */
    public function test_an_explicit_caller_fallback_overrides_the_registry_default_when_unset(): void
    {
        $this->assertSame('RajaBhoj Pawar Premier League', $this->settings->get('general.application_name'));
        $this->assertSame('Caller Default', $this->settings->get('general.application_name', 'Caller Default'));
    }

    public function test_persisted_value_always_wins_over_any_fallback(): void
    {
        $this->settings->set('general.application_name', 'Persisted Name');

        $this->assertSame('Persisted Name', $this->settings->get('general.application_name', 'Caller Default'));
    }

    public function test_boolean_conversion_works_correctly(): void
    {
        $this->settings->set('system.maintenance_mode', true);
        $this->assertTrue($this->settings->boolean('system.maintenance_mode'));

        $this->settings->set('system.maintenance_mode', false);
        $this->assertFalse($this->settings->boolean('system.maintenance_mode'));
    }

    public function test_setting_mutation_invalidates_cache_and_is_immediately_visible(): void
    {
        // Warm the cache with the registry default first.
        $this->assertSame('RPPL', $this->settings->get('general.short_name'));

        $this->settings->set('general.short_name', 'RB-PPL');

        $this->assertSame('RB-PPL', $this->settings->get('general.short_name'));
    }

    public function test_writing_an_unknown_setting_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings->set('not.a.real.setting', 'value');
    }

    public function test_reading_an_unknown_setting_key_returns_null_or_the_given_fallback_rather_than_throwing(): void
    {
        $this->assertNull($this->settings->get('not.a.real.setting'));
        $this->assertSame('fallback', $this->settings->get('not.a.real.setting', 'fallback'));
    }

    public function test_encrypted_secret_is_never_stored_as_plaintext(): void
    {
        $this->settings->set('payment.razorpay_key_secret', 'super-secret-value');

        $stored = Setting::where('group', 'payment')->where('key', 'razorpay_key_secret')->firstOrFail();

        $this->assertNotSame('super-secret-value', $stored->value);
        $this->assertStringNotContainsString('super-secret-value', (string) $stored->value);
    }

    public function test_the_encrypted_accessor_returns_the_original_value(): void
    {
        $this->settings->set('payment.razorpay_key_secret', 'super-secret-value');

        $this->assertSame('super-secret-value', $this->settings->getEncrypted('payment.razorpay_key_secret'));
    }

    /**
     * The core safety boundary: generic get() must never return a
     * decrypted (or raw ciphertext) value for an encrypted setting, even
     * though the persisted row genuinely exists.
     */
    public function test_generic_get_never_exposes_an_encrypted_setting(): void
    {
        $this->settings->set('payment.razorpay_key_secret', 'super-secret-value');

        $this->assertNull($this->settings->get('payment.razorpay_key_secret'));
        $this->assertSame('fallback', $this->settings->get('payment.razorpay_key_secret', 'fallback'));
    }

    public function test_unset_encrypted_value_behaves_safely(): void
    {
        $this->assertNull($this->settings->getEncrypted('payment.razorpay_key_secret'));
        $this->assertNull($this->settings->get('payment.razorpay_key_secret'));
    }

    public function test_get_encrypted_rejects_a_non_encrypted_key(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings->getEncrypted('general.application_name');
    }

    public function test_group_and_key_combination_must_be_unique(): void
    {
        Setting::factory()->create(['group' => 'general', 'key' => 'application_name']);

        $this->expectException(QueryException::class);

        Setting::factory()->create(['group' => 'general', 'key' => 'application_name']);
    }
}
