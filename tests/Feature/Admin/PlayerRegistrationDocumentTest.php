<?php

namespace Tests\Feature\Admin;

use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 3.39D — admin review of guest-submitted registrations: secure
 * private document access, payment-status/reference editing, and the
 * expanded review show page. Admin CRUD authorization itself (guest/
 * scorer rejection on the ordinary index/show/edit/update/destroy
 * routes) is already covered by PlayerRegistrationManagementTest and is
 * not repeated here — only the two new document endpoints are.
 */
class PlayerRegistrationDocumentTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $scorerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->scorerRole = Role::create(['name' => 'Scorer', 'slug' => 'scorer']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    private function scorer(): User
    {
        return User::factory()->create(['role_id' => $this->scorerRole->id]);
    }

    private function registrationWithDocuments(): PlayerRegistration
    {
        $aadhaarPath = UploadedFile::fake()->create('aadhaar.pdf', 200, 'application/pdf')
            ->store('player-registrations/aadhaar', 'local');
        $proofPath = UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')
            ->store('player-registrations/payment-proofs', 'local');

        return PlayerRegistration::factory()->create([
            'aadhaar_document_path' => $aadhaarPath,
            'payment_proof_path' => $proofPath,
        ])->assignRegistrationNumber();
    }

    // ----- Authorized access -----

    public function test_admin_can_view_aadhaar_document(): void
    {
        Storage::fake('local');
        $registration = $this->registrationWithDocuments();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.aadhaar', $registration));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_admin_can_view_payment_proof(): void
    {
        Storage::fake('local');
        $registration = $this->registrationWithDocuments();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.payment-proof', $registration));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_document_download_name_is_server_generated_from_registration_number(): void
    {
        Storage::fake('local');
        $aadhaarPath = UploadedFile::fake()->create('my-original-upload-name.pdf', 200, 'application/pdf')
            ->store('player-registrations/aadhaar', 'local');
        $registration = PlayerRegistration::factory()->create([
            'aadhaar_document_path' => $aadhaarPath,
        ])->assignRegistrationNumber();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.aadhaar', $registration));

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringNotContainsString('my-original-upload-name', $disposition);
        $this->assertStringContainsString(strtolower($registration->registration_number), $disposition);
        $this->assertStringContainsString('aadhaar', $disposition);
    }

    // ----- Unauthorized access — proven server-side, not by URL shape -----

    public function test_scorer_cannot_view_aadhaar_document(): void
    {
        Storage::fake('local');
        $registration = $this->registrationWithDocuments();

        $this->actingAs($this->scorer())
            ->get(route('admin.player-registrations.aadhaar', $registration))
            ->assertForbidden();
    }

    public function test_scorer_cannot_view_payment_proof(): void
    {
        Storage::fake('local');
        $registration = $this->registrationWithDocuments();

        $this->actingAs($this->scorer())
            ->get(route('admin.player-registrations.payment-proof', $registration))
            ->assertForbidden();
    }

    public function test_guest_cannot_view_aadhaar_document(): void
    {
        Storage::fake('local');
        $registration = $this->registrationWithDocuments();

        $this->get(route('admin.player-registrations.aadhaar', $registration))
            ->assertRedirect(route('admin.login'));
    }

    public function test_guest_cannot_view_payment_proof(): void
    {
        Storage::fake('local');
        $registration = $this->registrationWithDocuments();

        $this->get(route('admin.player-registrations.payment-proof', $registration))
            ->assertRedirect(route('admin.login'));
    }

    // ----- Missing document handling -----

    public function test_null_document_path_returns_a_clean_404(): void
    {
        $registration = PlayerRegistration::factory()->create([
            'aadhaar_document_path' => null,
            'payment_proof_path' => null,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.aadhaar', $registration))
            ->assertNotFound();

        $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.payment-proof', $registration))
            ->assertNotFound();
    }

    public function test_db_path_with_no_physical_file_returns_a_clean_404(): void
    {
        Storage::fake('local');
        $registration = PlayerRegistration::factory()->create([
            'aadhaar_document_path' => 'player-registrations/aadhaar/does-not-exist.jpg',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.aadhaar', $registration))
            ->assertNotFound();
    }

    // ----- Path ownership -----

    public function test_document_route_ignores_any_path_supplied_in_the_request(): void
    {
        Storage::fake('local');
        $registration = $this->registrationWithDocuments();
        $other = $this->registrationWithDocuments();

        $response = $this->actingAs($this->admin())->get(
            route('admin.player-registrations.aadhaar', $registration).'?path='.urlencode($other->aadhaar_document_path)
        );

        // Still resolves the route-bound registration's OWN column —
        // the query string is never read.
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // ----- Show page review display -----

    public function test_show_page_displays_full_review_fields_and_document_actions(): void
    {
        Storage::fake('local');
        $player = Player::factory()->create([
            'phone' => '9876543210',
            'email' => 'guest@example.com',
            'date_of_birth' => now()->subYears(25)->format('Y-m-d'),
            'primary_role' => 'all_rounder',
        ]);
        $registration = PlayerRegistration::factory()->create([
            'player_id' => $player->id,
            'payment_reference' => 'UTR999',
        ])->assignRegistrationNumber();
        $registration->update([
            'aadhaar_document_path' => UploadedFile::fake()->create('a.jpg', 50, 'image/jpeg')->store('player-registrations/aadhaar', 'local'),
            'payment_proof_path' => UploadedFile::fake()->create('p.jpg', 50, 'image/jpeg')->store('player-registrations/payment-proofs', 'local'),
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.player-registrations.show', $registration));

        $response->assertOk();
        $response->assertSee($registration->registration_number);
        $response->assertSee('9876543210');
        $response->assertSee('guest@example.com');
        $response->assertSee('25');
        $response->assertSee('All-rounder');
        $response->assertSee('UTR999');
        $response->assertSee(route('admin.player-registrations.aadhaar', $registration));
        $response->assertSee(route('admin.player-registrations.payment-proof', $registration));

        // Never the raw storage path.
        $response->assertDontSee($registration->aadhaar_document_path);
        $response->assertDontSee($registration->payment_proof_path);
    }

    public function test_show_page_displays_clean_fallbacks_for_a_manual_registration_with_no_documents(): void
    {
        $player = Player::factory()->create(['date_of_birth' => null, 'primary_role' => null]);
        $registration = PlayerRegistration::factory()->create([
            'player_id' => $player->id,
            'aadhaar_document_path' => null,
            'payment_proof_path' => null,
            'payment_reference' => null,
        ])->assignRegistrationNumber();

        $response = $this->actingAs($this->admin())->get(route('admin.player-registrations.show', $registration));

        $response->assertOk();
        $response->assertSeeInOrder(['Aadhaar Document', 'Not provided']);
        $response->assertSee('Not provided');
    }

    public function test_index_shows_registration_number_column(): void
    {
        $registration = PlayerRegistration::factory()->create()->assignRegistrationNumber();

        $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.index'))
            ->assertOk()
            ->assertSee($registration->registration_number);
    }
}
