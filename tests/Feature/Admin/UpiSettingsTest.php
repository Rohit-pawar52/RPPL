<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The UPI ID and QR image the public registration form shows — their own
 * form on the Settings → Payments tab, separate from Razorpay.
 */
class UpiSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $scorerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->scorerRole = Role::create(['name' => 'Scorer', 'slug' => 'scorer']);
        app(SettingsService::class)->flush();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    private function settings(): SettingsService
    {
        return app(SettingsService::class);
    }

    public function test_admin_can_save_the_upi_id_and_blank_clears_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.settings.upi.update'), ['upi_id' => 'rppl@ybl'])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'payments']))
            ->assertSessionHas('success');

        $this->assertSame('rppl@ybl', $this->settings()->get('payment.upi_id'));

        $this->actingAs($admin)->get(route('admin.settings.index', ['tab' => 'payments']))
            ->assertOk()
            ->assertSee('rppl@ybl');

        $this->actingAs($admin)->put(route('admin.settings.upi.update'), ['upi_id' => ''])->assertSessionHasNoErrors();

        $this->assertNull($this->settings()->get('payment.upi_id'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUpiIds(): array
    {
        return [
            'no bank part' => ['rppl'],
            'too short a name' => ['a@ybl'],
            'nothing after the @' => ['rppl@'],
            'spaces' => ['two words@ybl'],
            'query string smuggled in' => ['rppl@ybl&am=1'],
        ];
    }

    #[DataProvider('invalidUpiIds')]
    public function test_an_invalid_upi_id_is_rejected(string $upiId): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.upi.update'), ['upi_id' => $upiId])
            ->assertSessionHasErrors('upi_id');

        $this->assertNull($this->settings()->get('payment.upi_id'));
    }

    public function test_the_qr_code_can_be_uploaded_replaced_and_removed(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $url = route('admin.settings.upi.update');

        $this->actingAs($admin)->put($url, ['upi_qr' => UploadedFile::fake()->create('qr.png', 50, 'image/png')])
            ->assertSessionHasNoErrors();

        $first = $this->settings()->get('payment.upi_qr_path');
        $this->assertStringStartsWith('payments/', $first);
        Storage::disk('public')->assertExists($first);

        $this->actingAs($admin)->put($url, ['upi_qr' => UploadedFile::fake()->create('qr-new.png', 50, 'image/png')])
            ->assertSessionHasNoErrors();

        $second = $this->settings()->get('payment.upi_qr_path');
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        // Saving the form again without a file keeps the QR.
        $this->actingAs($admin)->put($url, ['upi_id' => 'rppl@ybl'])->assertSessionHasNoErrors();
        $this->assertSame($second, $this->settings()->get('payment.upi_qr_path'));

        $this->actingAs($admin)->put($url, ['remove_upi_qr' => '1'])->assertSessionHasNoErrors();

        $this->assertNull($this->settings()->get('payment.upi_qr_path'));
        Storage::disk('public')->assertMissing($second);
    }

    public function test_the_qr_must_be_an_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->put(route('admin.settings.upi.update'), ['upi_qr' => UploadedFile::fake()->create('qr.pdf', 50, 'application/pdf')])
            ->assertSessionHasErrors('upi_qr');

        $this->assertNull($this->settings()->get('payment.upi_qr_path'));
    }

    public function test_a_scorer_cannot_change_the_upi_details(): void
    {
        $scorer = User::factory()->create(['role_id' => $this->scorerRole->id]);

        $this->actingAs($scorer)
            ->put(route('admin.settings.upi.update'), ['upi_id' => 'rppl@ybl'])
            ->assertForbidden();

        $this->assertNull($this->settings()->get('payment.upi_id'));
    }

    public function test_saving_upi_details_and_saving_razorpay_do_not_touch_each_other(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.settings.payments.update'), [
            'razorpay_mode' => 'live',
            'razorpay_key_id' => 'rzp_live_123',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->put(route('admin.settings.upi.update'), ['upi_id' => 'rppl@ybl'])->assertSessionHasNoErrors();

        $this->assertSame('rzp_live_123', $this->settings()->get('payment.razorpay_key_id'));
        $this->assertSame('live', $this->settings()->get('payment.razorpay_mode'));

        $this->actingAs($admin)->put(route('admin.settings.payments.update'), [
            'razorpay_mode' => 'test',
            'razorpay_key_id' => 'rzp_test_456',
        ])->assertSessionHasNoErrors();

        $this->assertSame('rppl@ybl', $this->settings()->get('payment.upi_id'));
    }
}
