<?php

namespace Tests\Feature\Public;

use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 3.39E — public/guest registration-status lookup. Admin
 * verification, guest submission, and CSV import are proven by their
 * own existing suites and are not re-audited here.
 */
class PlayerRegistrationStatusTest extends TestCase
{
    use RefreshDatabase;

    private function registrationFor(array $playerOverrides = [], array $registrationOverrides = []): PlayerRegistration
    {
        $player = Player::factory()->create(array_merge(['phone' => '9876543210'], $playerOverrides));

        return PlayerRegistration::factory()
            ->create(array_merge(['player_id' => $player->id], $registrationOverrides))
            ->assignRegistrationNumber();
    }

    private function lookup(string $registrationNumber, string $phone)
    {
        return $this->post(route('public.player-registration.status.lookup'), [
            'registration_number' => $registrationNumber,
            'phone' => $phone,
        ]);
    }

    // ----- Availability -----

    public function test_status_form_is_available_regardless_of_registration_open_state(): void
    {
        $this->get(route('public.player-registration.status'))->assertOk();

        Edition::factory()->create(['registration_open' => true, 'registration_fee' => 400, 'status' => 'active']);
        $this->get(route('public.player-registration.status'))->assertOk();
    }

    // ----- Successful lookup + normalization -----

    public function test_correct_registration_number_and_phone_returns_result(): void
    {
        $registration = $this->registrationFor(['name' => 'Ramesh Joshi'], ['payment_status' => 'paid', 'registration_fee' => 400]);

        $response = $this->lookup($registration->registration_number, '9876543210');

        $response->assertOk();
        $response->assertSee($registration->registration_number);
        $response->assertSee('Ramesh Joshi');
        $response->assertSee($registration->edition->name);
        $response->assertSee('400.00');
        $response->assertSee('Paid');
    }

    public function test_phone_variant_and_lowercase_registration_number_are_normalized(): void
    {
        $registration = $this->registrationFor();

        $response = $this->lookup(strtolower($registration->registration_number), '+91 98765 43210');

        $response->assertOk()->assertSee($registration->registration_number);
    }

    // ----- Failure / enumeration -----

    public function test_wrong_registration_number_returns_generic_failure(): void
    {
        $this->registrationFor();

        $this->lookup('RPPL-2026-999999', '9876543210')
            ->assertOk()
            ->assertSee('No matching registration was found');
    }

    public function test_correct_registration_number_with_wrong_phone_returns_same_generic_failure(): void
    {
        $registration = $this->registrationFor();

        $this->lookup($registration->registration_number, '9998887766')
            ->assertOk()
            ->assertSee('No matching registration was found');
    }

    public function test_another_players_phone_returns_same_generic_failure(): void
    {
        $registration = $this->registrationFor(['phone' => '9876543210']);
        $this->registrationFor(['phone' => '9998887766']); // a genuinely existing, different player's phone

        $this->lookup($registration->registration_number, '9998887766')
            ->assertOk()
            ->assertSee('No matching registration was found');
    }

    public function test_nonexistent_number_and_wrong_phone_failures_are_indistinguishable(): void
    {
        $registration = $this->registrationFor();

        $nonexistent = $this->lookup('RPPL-2026-999999', '9876543210');
        $wrongPhone = $this->lookup($registration->registration_number, '9998887766');

        $nonexistent->assertOk();
        $wrongPhone->assertOk();
        $this->assertSame($nonexistent->status(), $wrongPhone->status());
        $this->assertSame($nonexistent->getContent(), $wrongPhone->getContent());
    }

    // ----- Historical accessibility -----

    public function test_inactive_players_historical_registration_remains_accessible(): void
    {
        $registration = $this->registrationFor(['is_active' => false]);

        $this->lookup($registration->registration_number, '9876543210')
            ->assertOk()
            ->assertSee($registration->registration_number);
    }

    public function test_completed_editions_registration_remains_accessible(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        $registration = $this->registrationFor([], ['edition_id' => $edition->id]);

        $this->lookup($registration->registration_number, '9876543210')
            ->assertOk()
            ->assertSee($registration->registration_number);
    }

    // ----- Displayed vs hidden fields -----

    public function test_result_shows_only_the_intended_public_fields_and_hides_sensitive_data(): void
    {
        Storage::fake('local');
        $player = Player::factory()->create([
            'phone' => '9876543210',
            'email' => 'guest@example.com',
            'date_of_birth' => '2000-01-01',
            'primary_role' => 'batter',
        ]);
        $aadhaarPath = UploadedFile::fake()->create('a.jpg', 50, 'image/jpeg')->store('player-registrations/aadhaar', 'local');
        $proofPath = UploadedFile::fake()->create('p.jpg', 50, 'image/jpeg')->store('player-registrations/payment-proofs', 'local');
        $registration = PlayerRegistration::factory()->create([
            'player_id' => $player->id,
            'payment_reference' => 'UTR12345',
            'aadhaar_document_path' => $aadhaarPath,
            'payment_proof_path' => $proofPath,
        ])->assignRegistrationNumber();

        $response = $this->lookup($registration->registration_number, '9876543210');

        $response->assertOk();

        // Shown.
        $response->assertSee($registration->registration_number);
        $response->assertSee($player->name);
        $response->assertSee($registration->edition->name);

        // Hidden.
        $response->assertDontSee('9876543210');
        $response->assertDontSee('guest@example.com');
        $response->assertDontSee('2000-01-01');
        $response->assertDontSee('UTR12345');
        $response->assertDontSee($aadhaarPath);
        $response->assertDontSee($proofPath);
        $response->assertDontSee(route('admin.player-registrations.aadhaar', $registration), false);
        $response->assertDontSee(route('admin.player-registrations.payment-proof', $registration), false);
    }

    // ----- Payment status labels (combined, not four separate tests) -----

    public function test_all_payment_statuses_render_the_expected_human_label(): void
    {
        $expected = [
            'pending' => ['9111111111', 'Pending Verification'],
            'paid' => ['9222222222', 'Paid'],
            'failed' => ['9333333333', 'Payment Verification Failed'],
            'refunded' => ['9444444444', 'Refunded'],
        ];

        foreach ($expected as $status => [$phone, $label]) {
            $registration = $this->registrationFor(['phone' => $phone], ['payment_status' => $status]);

            $this->lookup($registration->registration_number, $phone)
                ->assertOk()
                ->assertSee($label);
        }
    }

    // ----- Read-only / rate limiting -----

    public function test_lookup_performs_no_database_mutation(): void
    {
        $registration = $this->registrationFor(['name' => 'Original Name'], ['payment_status' => 'pending'])->fresh();
        $playerBefore = $registration->player->fresh()->getAttributes();
        $registrationBefore = $registration->getAttributes();

        $this->lookup($registration->registration_number, '9876543210')->assertOk();
        $this->lookup('RPPL-2026-999999', '9876543210')->assertOk();

        // ksort() so this compares content, not incidental attribute
        // insertion order (fresh() loads columns in DB order; the
        // in-memory model built them via factory + assignRegistrationNumber()
        // in a different order) — a real mutation would change a VALUE,
        // which ksort'd assertSame still catches.
        $playerAfter = $registration->player->fresh()->getAttributes();
        $registrationAfter = $registration->fresh()->getAttributes();
        ksort($playerBefore);
        ksort($playerAfter);
        ksort($registrationBefore);
        ksort($registrationAfter);

        $this->assertSame($playerBefore, $playerAfter);
        $this->assertSame($registrationBefore, $registrationAfter);
    }

    public function test_status_lookup_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->lookup('RPPL-2026-999999', '9876543210')->assertOk();
        }

        $this->lookup('RPPL-2026-999999', '9876543210')->assertStatus(429);
    }
}
