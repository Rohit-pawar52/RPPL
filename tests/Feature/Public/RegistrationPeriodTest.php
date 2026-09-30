<?php

namespace Tests\Feature\Public;

use App\Models\Edition;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Optional registration_opens_at/registration_closes_at window layered on
 * top of the existing registration_open switch.
 */
class RegistrationPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->flush();
        Queue::fake();
        Storage::fake('local');
    }

    private function openEdition(array $overrides = []): Edition
    {
        return Edition::factory()->create(array_merge([
            'name' => 'RPPL 2026',
            'status' => 'active',
            'registration_open' => true,
            'registration_fee' => 400.00,
        ], $overrides));
    }

    private function submit()
    {
        return $this->post(route('public.player-registration.store'), [
            'name' => 'Ramesh Joshi',
            'phone' => '9876543210',
            'date_of_birth' => '2000-01-01',
            'primary_role' => 'batter',
            'aadhaar_document' => UploadedFile::fake()->create('aadhaar.jpg', 500, 'image/jpeg'),
            'payment_proof' => UploadedFile::fake()->create('proof.jpg', 300, 'image/jpeg'),
        ]);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    // ----- Public availability -----

    public function test_edition_without_registration_dates_keeps_existing_open_closed_behavior(): void
    {
        $edition = $this->openEdition();

        $this->get(route('public.player-registration.create'))->assertOk()->assertSee('RPPL 2026');
        $this->submit()->assertRedirect(route('public.player-registration.success'));
        $this->assertSame(1, PlayerRegistration::count());

        $edition->update(['registration_open' => false]);

        $this->get(route('public.player-registration.create'))
            ->assertSee('Player registration is currently closed.');
    }

    public function test_future_opening_time_blocks_registration_with_not_yet_open_message(): void
    {
        $this->openEdition(['registration_opens_at' => now()->addDay()]);

        $this->get(route('public.player-registration.create'))
            ->assertOk()
            ->assertSee('Registration has not opened yet.')
            ->assertDontSee('Submit Registration');

        $this->submit()->assertSessionHas('error');
        $this->assertSame(0, PlayerRegistration::count());
    }

    public function test_inside_the_window_registration_proceeds_and_shows_closing_time(): void
    {
        $this->openEdition([
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDay(),
        ]);

        $this->get(route('public.player-registration.create'))
            ->assertOk()
            ->assertSee('Registration closes on');

        $this->submit()->assertRedirect(route('public.player-registration.success'));
        $this->assertSame(1, PlayerRegistration::count());
    }

    public function test_past_closing_time_blocks_registration(): void
    {
        $this->openEdition(['registration_closes_at' => now()->subMinute()]);

        $this->get(route('public.player-registration.create'))
            ->assertSee('Player registration is currently closed.');

        $this->submit()->assertSessionHas('error');
        $this->assertSame(0, PlayerRegistration::count());
    }

    public function test_hindi_window_messages_render(): void
    {
        $this->openEdition(['registration_opens_at' => now()->addDay()]);

        $this->withCookie('rppl_locale', 'hi')
            ->get(route('public.player-registration.create'))
            ->assertSee('पंजीकरण अभी शुरू नहीं हुआ है।');

        Edition::query()->update(['registration_opens_at' => null, 'registration_closes_at' => now()->addDay()]);

        $this->withCookie('rppl_locale', 'hi')
            ->get(route('public.player-registration.create'))
            ->assertSee('को बंद होगा।');
    }

    // ----- Admin form -----

    public function test_admin_dates_are_entered_in_display_timezone_and_stored_as_utc(): void
    {
        app(SettingsService::class)->set('system.display_timezone', 'Asia/Kolkata');

        $this->actingAs($this->admin())->post(route('admin.editions.store'), [
            'name' => 'RPPL 2027',
            'year' => 2027,
            'status' => 'upcoming',
            'registration_open' => '1',
            'registration_fee' => 500,
            'registration_opens_at' => '2027-01-10T10:00',
            'registration_closes_at' => '2027-01-20T20:00',
            'registration_reminder_enabled' => '1',
            'registration_reminder_minutes_before' => 1440,
        ])->assertRedirect(route('admin.editions.index'));

        $edition = Edition::where('year', 2027)->firstOrFail();
        $this->assertSame('2027-01-10 04:30:00', $edition->registration_opens_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2027-01-20 14:30:00', $edition->registration_closes_at->utc()->format('Y-m-d H:i:s'));
        $this->assertTrue($edition->registration_reminder_enabled);
        $this->assertSame(1440, $edition->registration_reminder_minutes_before);

        $this->actingAs($this->admin())
            ->get(route('admin.editions.edit', $edition))
            ->assertSee('2027-01-10T10:00', false)
            ->assertSee('2027-01-20T20:00', false);
    }

    public function test_closing_time_must_be_after_opening_time_and_reminder_requires_closing_time(): void
    {
        $base = ['name' => 'RPPL 2027', 'year' => 2027, 'status' => 'upcoming', 'registration_fee' => 500];

        $this->actingAs($this->admin())->post(route('admin.editions.store'), $base + [
            'registration_opens_at' => '2027-01-20T10:00',
            'registration_closes_at' => '2027-01-10T10:00',
        ])->assertSessionHasErrors('registration_closes_at');

        $this->actingAs($this->admin())->post(route('admin.editions.store'), $base + [
            'registration_reminder_enabled' => '1',
            'registration_reminder_minutes_before' => 1440,
        ])->assertSessionHasErrors('registration_closes_at');

        $this->actingAs($this->admin())->post(route('admin.editions.store'), $base + [
            'registration_opens_at' => 'not-a-date',
        ])->assertSessionHasErrors('registration_opens_at');

        $this->assertSame(0, Edition::count());
    }

    public function test_existing_edition_update_without_period_fields_leaves_them_untouched(): void
    {
        $closesAt = now()->addDays(3)->startOfMinute();
        $edition = $this->openEdition(['year' => 2026, 'registration_closes_at' => $closesAt]);

        $this->actingAs($this->admin())->put(route('admin.editions.update', $edition), [
            'name' => 'RPPL 2026 Renamed',
            'year' => 2026,
            'status' => 'active',
            'registration_open' => '1',
            'registration_fee' => 400,
        ])->assertRedirect(route('admin.editions.index'));

        $fresh = $edition->fresh();
        $this->assertSame('RPPL 2026 Renamed', $fresh->name);
        $this->assertTrue($fresh->registration_closes_at->equalTo($closesAt));
    }
}
