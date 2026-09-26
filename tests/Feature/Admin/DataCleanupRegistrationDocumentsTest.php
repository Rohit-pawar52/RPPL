<?php

namespace Tests\Feature\Admin;

use App\Models\DataCleanupLog;
use App\Models\Edition;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 3.49 — the highest-priority new cleanup category: purges
 * private Aadhaar/payment-proof FILES for a selected, no-longer-open
 * edition without ever touching the registration record itself.
 */
class DataCleanupRegistrationDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $scorerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->scorerRole = Role::create(['name' => 'Scorer', 'slug' => 'scorer']);
        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    private function scorer(): User
    {
        return User::factory()->create(['role_id' => $this->scorerRole->id]);
    }

    private function registrationWithDocuments(Edition $edition, bool $withAadhaar = true, bool $withPaymentProof = true): PlayerRegistration
    {
        $aadhaarPath = $withAadhaar ? 'player-registrations/aadhaar/'.uniqid().'.jpg' : null;
        $paymentProofPath = $withPaymentProof ? 'player-registrations/payment-proofs/'.uniqid().'.jpg' : null;

        if ($aadhaarPath) {
            Storage::disk('local')->put($aadhaarPath, 'fake-aadhaar-content');
        }
        if ($paymentProofPath) {
            Storage::disk('local')->put($paymentProofPath, 'fake-payment-proof-content');
        }

        return PlayerRegistration::factory()->create([
            'edition_id' => $edition->id,
            'aadhaar_document_path' => $aadhaarPath,
            'payment_proof_path' => $paymentProofPath,
        ]);
    }

    public function test_deleting_both_document_types_clears_paths_deletes_files_and_preserves_the_registration(): void
    {
        $edition = Edition::factory()->create(['registration_open' => false]);
        $registration = $this->registrationWithDocuments($edition);
        $aadhaarPath = $registration->aadhaar_document_path;
        $paymentProofPath = $registration->payment_proof_path;

        $response = $this->actingAs($this->admin())->delete(route('admin.data-cleanup.registration-documents.destroy'), [
            'edition_id' => $edition->id,
            'document_type' => 'both',
        ]);

        $response->assertRedirect(route('admin.data-cleanup.index', ['tab' => 'registration-documents']));

        $registration->refresh();
        $this->assertNull($registration->aadhaar_document_path);
        $this->assertNull($registration->payment_proof_path);
        Storage::disk('local')->assertMissing($aadhaarPath);
        Storage::disk('local')->assertMissing($paymentProofPath);

        // The registration record itself, its player, payment status,
        // fee, and edition link all survive untouched.
        $this->assertModelExists($registration);
        $this->assertNotNull($registration->player_id);
        $this->assertNotNull($registration->registration_fee);
        $this->assertSame($edition->id, $registration->edition_id);
    }

    public function test_aadhaar_only_leaves_payment_proof_untouched(): void
    {
        $edition = Edition::factory()->create(['registration_open' => false]);
        $registration = $this->registrationWithDocuments($edition);
        $paymentProofPath = $registration->payment_proof_path;

        $this->actingAs($this->admin())->delete(route('admin.data-cleanup.registration-documents.destroy'), [
            'edition_id' => $edition->id,
            'document_type' => 'aadhaar',
        ]);

        $registration->refresh();
        $this->assertNull($registration->aadhaar_document_path);
        $this->assertSame($paymentProofPath, $registration->payment_proof_path);
        Storage::disk('local')->assertExists($paymentProofPath);
    }

    public function test_payment_proof_only_leaves_aadhaar_untouched(): void
    {
        $edition = Edition::factory()->create(['registration_open' => false]);
        $registration = $this->registrationWithDocuments($edition);
        $aadhaarPath = $registration->aadhaar_document_path;

        $this->actingAs($this->admin())->delete(route('admin.data-cleanup.registration-documents.destroy'), [
            'edition_id' => $edition->id,
            'document_type' => 'payment_proof',
        ]);

        $registration->refresh();
        $this->assertNull($registration->payment_proof_path);
        $this->assertSame($aadhaarPath, $registration->aadhaar_document_path);
        Storage::disk('local')->assertExists($aadhaarPath);
    }

    public function test_a_registration_with_a_missing_file_is_handled_safely(): void
    {
        $edition = Edition::factory()->create(['registration_open' => false]);
        $registration = PlayerRegistration::factory()->create([
            'edition_id' => $edition->id,
            'aadhaar_document_path' => 'player-registrations/aadhaar/already-gone.jpg',
            'payment_proof_path' => null,
        ]);

        $response = $this->actingAs($this->admin())->delete(route('admin.data-cleanup.registration-documents.destroy'), [
            'edition_id' => $edition->id,
            'document_type' => 'both',
        ]);

        $response->assertSessionHas('success');
        $registration->refresh();
        $this->assertNull($registration->aadhaar_document_path);

        $log = DataCleanupLog::first();
        $this->assertSame(0, $log->files_deleted);
    }

    public function test_an_edition_still_open_for_registration_is_rejected(): void
    {
        $edition = Edition::factory()->create(['registration_open' => true]);
        $registration = $this->registrationWithDocuments($edition);

        $response = $this->actingAs($this->admin())->delete(route('admin.data-cleanup.registration-documents.destroy'), [
            'edition_id' => $edition->id,
            'document_type' => 'both',
        ]);

        $response->assertSessionHasErrors('edition_id');
        $registration->refresh();
        $this->assertNotNull($registration->aadhaar_document_path);
        Storage::disk('local')->assertExists($registration->aadhaar_document_path);
    }

    public function test_only_the_selected_edition_is_affected(): void
    {
        $targetEdition = Edition::factory()->create(['registration_open' => false]);
        $otherEdition = Edition::factory()->create(['registration_open' => false]);
        $targetRegistration = $this->registrationWithDocuments($targetEdition);
        $otherRegistration = $this->registrationWithDocuments($otherEdition);

        $this->actingAs($this->admin())->delete(route('admin.data-cleanup.registration-documents.destroy'), [
            'edition_id' => $targetEdition->id,
            'document_type' => 'both',
        ]);

        $this->assertNull($targetRegistration->fresh()->aadhaar_document_path);
        $this->assertNotNull($otherRegistration->fresh()->aadhaar_document_path);
        Storage::disk('local')->assertExists($otherRegistration->aadhaar_document_path);
    }

    public function test_preview_counts_match_what_deletion_actually_affects(): void
    {
        $edition = Edition::factory()->create(['registration_open' => false]);
        $this->registrationWithDocuments($edition, withAadhaar: true, withPaymentProof: false);
        $this->registrationWithDocuments($edition, withAadhaar: true, withPaymentProof: true);
        $this->registrationWithDocuments($edition, withAadhaar: false, withPaymentProof: false);

        $preview = $this->actingAs($this->admin())->getJson(route('admin.data-cleanup.preview.registration-documents', [
            'edition_id' => $edition->id,
            'document_type' => 'both',
        ]));

        $preview->assertOk()->assertJson(['aadhaar' => 2, 'payment_proof' => 1]);
    }

    public function test_scorer_cannot_delete_registration_documents(): void
    {
        $edition = Edition::factory()->create(['registration_open' => false]);
        $registration = $this->registrationWithDocuments($edition);

        $this->actingAs($this->scorer())->delete(route('admin.data-cleanup.registration-documents.destroy'), [
            'edition_id' => $edition->id,
            'document_type' => 'both',
        ])->assertForbidden();

        $this->assertNotNull($registration->fresh()->aadhaar_document_path);
    }

    public function test_audit_log_records_edition_and_document_type_criteria(): void
    {
        $edition = Edition::factory()->create(['registration_open' => false]);
        $this->registrationWithDocuments($edition);

        $this->actingAs($this->admin())->delete(route('admin.data-cleanup.registration-documents.destroy'), [
            'edition_id' => $edition->id,
            'document_type' => 'both',
        ]);

        $log = DataCleanupLog::first();
        $this->assertSame('registration_documents', $log->category);
        $this->assertSame('delete_registration_documents', $log->action);
        $this->assertSame($edition->id, $log->criteria['edition_id']);
        $this->assertSame('both', $log->criteria['document_type']);
        $this->assertSame(1, $log->records_affected);
        $this->assertSame(2, $log->files_deleted);
    }
}
