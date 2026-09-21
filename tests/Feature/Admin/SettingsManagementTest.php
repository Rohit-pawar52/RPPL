<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 3.44B2 — the admin Settings management UI on top of Phase
 * 3.44B1's data foundation. Proves authorization, one allow-listed
 * FormRequest per tab, color/timezone validation, the encrypted-secret
 * masking contract (blank keeps existing, non-blank replaces, GET never
 * exposes it), and the logo/favicon upload lifecycle.
 */
class SettingsManagementTest extends TestCase
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

    public function test_admin_can_access_settings(): void
    {
        $this->actingAs($this->admin())->get(route('admin.settings.index'))->assertOk();
    }

    public function test_scorer_cannot_access_settings(): void
    {
        $this->actingAs($this->scorer())->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_guest_cannot_access_settings(): void
    {
        $this->get(route('admin.settings.index'))->assertRedirect(route('admin.login'));
    }

    public function test_scorer_cannot_update_settings(): void
    {
        $this->actingAs($this->scorer())->put(route('admin.settings.general.update'), [
            'application_name' => 'Hacked League',
            'short_name' => 'HL',
            'primary_color' => '#111111',
            'secondary_color' => '#222222',
            'button_color' => '#333333',
        ])->assertForbidden();

        $this->assertSame('RajaBhoj Pawar Premier League', app(SettingsService::class)->get('general.application_name'));
    }

    // ----- Sidebar -----

    public function test_settings_nav_item_is_visible_to_admin_and_hidden_from_scorer(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Settings');

        $this->actingAs($this->scorer())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Settings');
    }

    // ----- General tab -----

    public function test_admin_can_update_general_settings(): void
    {
        $response = $this->actingAs($this->admin())->put(route('admin.settings.general.update'), [
            'application_name' => 'Custom League',
            'short_name' => 'CL',
            'tagline' => 'A new era',
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
            'button_color' => '#abcdef',
        ]);

        $response->assertRedirect(route('admin.settings.index', ['tab' => 'general']));

        $settings = app(SettingsService::class);
        $this->assertSame('Custom League', $settings->get('general.application_name'));
        $this->assertSame('CL', $settings->get('general.short_name'));
        $this->assertSame('A new era', $settings->get('general.tagline'));
        $this->assertSame('#123456', $settings->get('general.primary_color'));
    }

    public function test_invalid_hex_color_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())->put(route('admin.settings.general.update'), [
            'application_name' => 'Custom League',
            'short_name' => 'CL',
            'primary_color' => 'not-a-color',
            'secondary_color' => '#654321',
            'button_color' => '#abcdef',
        ]);

        $response->assertSessionHasErrors('primary_color');
        $this->assertSame('RajaBhoj Pawar Premier League', app(SettingsService::class)->get('general.application_name'));
    }

    public function test_application_name_is_required(): void
    {
        $response = $this->actingAs($this->admin())->put(route('admin.settings.general.update'), [
            'application_name' => '',
            'short_name' => 'CL',
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
            'button_color' => '#abcdef',
        ]);

        $response->assertSessionHasErrors('application_name');
    }

    public function test_logo_upload_works(): void
    {
        Storage::fake('public');

        $logo = UploadedFile::fake()->create('logo.png', 100, 'image/png');

        $this->actingAs($this->admin())->put(route('admin.settings.general.update'), [
            'application_name' => 'Custom League',
            'short_name' => 'CL',
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
            'button_color' => '#abcdef',
            'logo' => $logo,
        ]);

        $path = app(SettingsService::class)->get('general.logo_path');

        $this->assertNotNull($path);
        $this->assertStringStartsWith('branding/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_logo_replacement_deletes_the_old_file(): void
    {
        Storage::fake('public');

        $first = UploadedFile::fake()->create('first.png', 100, 'image/png');
        $this->actingAs($this->admin())->put(route('admin.settings.general.update'), [
            'application_name' => 'Custom League',
            'short_name' => 'CL',
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
            'button_color' => '#abcdef',
            'logo' => $first,
        ]);
        $firstPath = app(SettingsService::class)->get('general.logo_path');

        $second = UploadedFile::fake()->create('second.png', 100, 'image/png');
        $this->actingAs($this->admin())->put(route('admin.settings.general.update'), [
            'application_name' => 'Custom League',
            'short_name' => 'CL',
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
            'button_color' => '#abcdef',
            'logo' => $second,
        ]);
        $secondPath = app(SettingsService::class)->get('general.logo_path');

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_favicon_upload_accepts_ico(): void
    {
        Storage::fake('public');

        $favicon = UploadedFile::fake()->create('favicon.ico', 50, 'image/x-icon');

        $response = $this->actingAs($this->admin())->put(route('admin.settings.general.update'), [
            'application_name' => 'Custom League',
            'short_name' => 'CL',
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
            'button_color' => '#abcdef',
            'favicon' => $favicon,
        ]);

        $response->assertSessionDoesntHaveErrors('favicon');

        $path = app(SettingsService::class)->get('general.favicon_path');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_remove_logo_checkbox_deletes_the_stored_file(): void
    {
        Storage::fake('public');

        $logo = UploadedFile::fake()->create('logo.png', 100, 'image/png');
        $this->actingAs($this->admin())->put(route('admin.settings.general.update'), [
            'application_name' => 'Custom League',
            'short_name' => 'CL',
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
            'button_color' => '#abcdef',
            'logo' => $logo,
        ]);
        $path = app(SettingsService::class)->get('general.logo_path');
        Storage::disk('public')->assertExists($path);

        $this->actingAs($this->admin())->put(route('admin.settings.general.update'), [
            'application_name' => 'Custom League',
            'short_name' => 'CL',
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
            'button_color' => '#abcdef',
            'remove_logo' => '1',
        ]);

        $this->assertNull(app(SettingsService::class)->get('general.logo_path'));
        Storage::disk('public')->assertMissing($path);
    }

    // ----- Contact tab -----

    public function test_admin_can_update_contact_settings(): void
    {
        $response = $this->actingAs($this->admin())->put(route('admin.settings.contact.update'), [
            'email' => 'contact@rppl.test',
            'phone' => '9999999999',
            'whatsapp' => '9999999999',
            'address' => 'Pune, Maharashtra',
        ]);

        $response->assertRedirect(route('admin.settings.index', ['tab' => 'contact']));

        $settings = app(SettingsService::class);
        $this->assertSame('contact@rppl.test', $settings->get('contact.email'));
        $this->assertSame('Pune, Maharashtra', $settings->get('contact.address'));
    }

    public function test_invalid_contact_email_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())->put(route('admin.settings.contact.update'), [
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    // ----- System tab -----

    public function test_admin_can_update_system_settings(): void
    {
        $response = $this->actingAs($this->admin())->put(route('admin.settings.system.update'), [
            'maintenance_mode' => '1',
            'maintenance_message' => 'Back soon.',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'display_timezone' => 'America/New_York',
        ]);

        $response->assertRedirect(route('admin.settings.index', ['tab' => 'system']));

        $settings = app(SettingsService::class);
        $this->assertTrue($settings->boolean('system.maintenance_mode'));
        $this->assertSame('USD', $settings->get('system.currency'));
        $this->assertSame('America/New_York', $settings->get('system.display_timezone'));
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())->put(route('admin.settings.system.update'), [
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'display_timezone' => 'Not/ARealZone',
        ]);

        $response->assertSessionHasErrors('display_timezone');
        $this->assertSame('Asia/Kolkata', app(SettingsService::class)->get('system.display_timezone'));
    }

    // ----- Payments tab -----

    public function test_admin_can_configure_a_razorpay_secret(): void
    {
        $response = $this->actingAs($this->admin())->put(route('admin.settings.payments.update'), [
            'razorpay_enabled' => '1',
            'razorpay_mode' => 'test',
            'razorpay_key_id' => 'rzp_test_key',
            'razorpay_key_secret' => 'super-secret-value',
        ]);

        $response->assertRedirect(route('admin.settings.index', ['tab' => 'payments']));

        $settings = app(SettingsService::class);
        $this->assertTrue($settings->boolean('payment.razorpay_enabled'));
        $this->assertSame('rzp_test_key', $settings->get('payment.razorpay_key_id'));
        $this->assertSame('super-secret-value', $settings->getEncrypted('payment.razorpay_key_secret'));

        $stored = Setting::where('group', 'payment')->where('key', 'razorpay_key_secret')->firstOrFail();
        $this->assertStringNotContainsString('super-secret-value', (string) $stored->value);
    }

    public function test_blank_secret_preserves_the_existing_value(): void
    {
        $admin = $this->admin();
        app(SettingsService::class)->set('payment.razorpay_key_secret', 'original-secret');

        $this->actingAs($admin)->put(route('admin.settings.payments.update'), [
            'razorpay_mode' => 'test',
            'razorpay_key_secret' => '',
        ]);

        $this->assertSame('original-secret', app(SettingsService::class)->getEncrypted('payment.razorpay_key_secret'));
    }

    public function test_non_blank_secret_replaces_the_existing_value(): void
    {
        $admin = $this->admin();
        app(SettingsService::class)->set('payment.razorpay_key_secret', 'original-secret');

        $this->actingAs($admin)->put(route('admin.settings.payments.update'), [
            'razorpay_mode' => 'test',
            'razorpay_key_secret' => 'replacement-secret',
        ]);

        $this->assertSame('replacement-secret', app(SettingsService::class)->getEncrypted('payment.razorpay_key_secret'));
    }

    public function test_settings_page_never_exposes_the_decrypted_or_ciphertext_secret(): void
    {
        app(SettingsService::class)->set('payment.razorpay_key_secret', 'super-secret-value');

        $response = $this->actingAs($this->admin())->get(route('admin.settings.index', ['tab' => 'payments']));

        $response->assertOk();
        $response->assertDontSee('super-secret-value');

        $ciphertext = Setting::where('group', 'payment')->where('key', 'razorpay_key_secret')->value('value');
        $response->assertDontSee($ciphertext, false);
    }

    public function test_razorpay_mode_must_be_test_or_live(): void
    {
        $response = $this->actingAs($this->admin())->put(route('admin.settings.payments.update'), [
            'razorpay_mode' => 'production',
        ]);

        $response->assertSessionHasErrors('razorpay_mode');
    }

    // ----- Public website tab -----

    public function test_admin_can_update_public_website_settings(): void
    {
        $response = $this->actingAs($this->admin())->put(route('admin.settings.public-website.update'), [
            'footer_text' => 'Thanks for visiting RPPL.',
        ]);

        $response->assertRedirect(route('admin.settings.index', ['tab' => 'public-website']));
        $this->assertSame('Thanks for visiting RPPL.', app(SettingsService::class)->get('public.footer_text'));
    }

    // ----- Batch atomicity -----

    public function test_updating_one_tab_does_not_change_settings_belonging_to_another_tab(): void
    {
        $this->actingAs($this->admin())->put(route('admin.settings.general.update'), [
            'application_name' => 'Custom League',
            'short_name' => 'CL',
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
            'button_color' => '#abcdef',
        ]);

        $this->assertSame('INR', app(SettingsService::class)->get('system.currency'));
    }
}
