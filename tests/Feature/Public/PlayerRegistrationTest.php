<?php

namespace Tests\Feature\Public;

use App\Jobs\ProcessPaymentProofOcr;
use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\User;
use App\Services\Registration\GuestPlayerRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 3.39C — public/guest player registration submission. Admin
 * registration/CSV import compatibility is proven by their own existing
 * test suites continuing to pass unchanged (not repeated here). Payment
 * proof OCR (Phase B) dispatch/failure-isolation is covered at the
 * bottom of this file; OCR's own extraction/job behavior has its own
 * dedicated test suites (TransactionIdExtractorTest, ProcessPaymentProofOcrTest).
 * What the form asks and how each answer is validated is in
 * PlayerRegistrationFormTest.
 */
class PlayerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function openEdition(array $overrides = []): Edition
    {
        return Edition::factory()->create(array_merge([
            'status' => 'active',
            'registration_open' => true,
            'registration_fee' => 400.00,
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ramesh Joshi',
            'age' => 24,
            'phone' => '9876543210',
            'email' => null,
            'primary_role' => 'batter',
            'batting_style' => 'right_hand',
            'bowling_style' => 'none',
            'village' => 'Sendriya',
            'tehsil' => 'Multai',
            'district' => 'Betul',
            'submitted_utr' => '402912345678',
        ], $overrides);
    }

    /**
     * ->create() (not ->image()) deliberately: ->image() requires the GD
     * extension purely to render real pixel data, which this
     * environment doesn't have — ->create() with an explicit MIME type
     * satisfies the image/mimes validation rules identically without it.
     */
    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->create('photo.jpg', 500, 'image/jpeg');
    }

    private function paymentProof(): UploadedFile
    {
        return UploadedFile::fake()->create('proof.jpg', 300, 'image/jpeg');
    }

    private function submit(array $payload, ?UploadedFile $photo = null, ?UploadedFile $proof = null)
    {
        return $this->post(route('public.player-registration.store'), array_merge($payload, [
            'photo' => $photo ?? $this->photo(),
            'payment_proof' => $proof ?? $this->paymentProof(),
        ]));
    }

    // ----- Availability -----

    public function test_form_is_available_when_an_edition_is_open_and_shows_closed_state_otherwise(): void
    {
        $edition = $this->openEdition(['name' => 'RPPL 2026']);

        $this->get(route('public.player-registration.create'))
            ->assertOk()
            ->assertSee('RPPL 2026')
            ->assertSee('400.00');

        $edition->update(['registration_open' => false]);

        $this->get(route('public.player-registration.create'))
            ->assertOk()
            ->assertSee('currently closed');
    }

    public function test_edition_with_registration_open_but_no_fee_configured_is_treated_as_closed(): void
    {
        $this->openEdition(['registration_fee' => null]);

        $this->get(route('public.player-registration.create'))->assertSee('currently closed');
    }

    public function test_at_most_one_edition_may_have_registration_open_at_a_time(): void
    {
        Storage::fake('local');
        $this->admin_can_open_first_edition_but_not_a_second();
    }

    private function admin_can_open_first_edition_but_not_a_second(): void
    {
        // Uses the real admin update flow to prove the V1 invariant.
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $first = Edition::factory()->create(['status' => 'active', 'registration_open' => false]);
        $second = Edition::factory()->create(['status' => 'active', 'registration_open' => false]);

        $this->actingAs($admin)->put(route('admin.editions.update', $first), [
            'name' => $first->name, 'year' => $first->year, 'status' => 'active', 'registration_open' => '1',
        ])->assertSessionDoesntHaveErrors();
        $this->assertTrue($first->fresh()->registration_open);

        $this->actingAs($admin)->put(route('admin.editions.update', $second), [
            'name' => $second->name, 'year' => $second->year, 'status' => 'active', 'registration_open' => '1',
        ])->assertSessionHasErrors('registration_open');
        $this->assertFalse($second->fresh()->registration_open);
    }

    // ----- Successful new player submission -----

    public function test_successful_submission_creates_a_new_player_and_pending_registration(): void
    {
        Storage::fake('local');
        $edition = $this->openEdition();

        $this->submit($this->validPayload(['phone' => '+91 98765 43210', 'email' => 'Ramesh@Example.com']))
            ->assertRedirect(route('public.player-registration.success'));

        $player = Player::firstWhere('name', 'Ramesh Joshi');
        $this->assertNotNull($player);
        $this->assertSame('9876543210', $player->phone); // +91/spaces normalized away
        $this->assertSame('ramesh@example.com', $player->email);
        $this->assertSame('batter', $player->primary_role);
        $this->assertSame('right_hand', $player->batting_style);
        $this->assertSame('none', $player->bowling_style);
        $this->assertNull($player->date_of_birth, 'age is asked instead of a date of birth');
        $this->assertNull($player->user_id);
        $this->assertNull($player->photo_path, 'the submitted photo is never made public by itself');

        $registration = PlayerRegistration::where('player_id', $player->id)->firstOrFail();
        $this->assertSame($edition->id, $registration->edition_id);
        $this->assertSame('pending', $registration->payment_status);
        $this->assertSame('400.00', (string) $registration->registration_fee);
        $this->assertNotNull($registration->registered_at);
        $this->assertSame(sprintf('RPPL-%d-%06d', $edition->year, $registration->id), $registration->registration_number);

        // What the player answered for this edition lives on the registration.
        $this->assertSame(24, $registration->age);
        $this->assertSame('Sendriya', $registration->village);
        $this->assertSame('Multai', $registration->tehsil);
        $this->assertSame('Betul', $registration->district);
        $this->assertSame('402912345678', $registration->submitted_utr);
        $this->assertNull($registration->payment_reference, 'payment_reference stays the admin-verified value');

        // Private storage, never public; no Aadhaar is collected any more.
        $this->assertNotNull($registration->photo_path);
        $this->assertNotNull($registration->payment_proof_path);
        $this->assertNull($registration->aadhaar_document_path);
        Storage::disk('local')->assertExists($registration->photo_path);
        Storage::disk('local')->assertExists($registration->payment_proof_path);
        $this->assertStringStartsWith('player-registrations/photos/', $registration->photo_path);
        $this->assertStringStartsWith('player-registrations/payment-proofs/', $registration->payment_proof_path);

        // Success page shows the number, never the private paths.
        $success = $this->get(route('public.player-registration.success'));
        $success->assertOk()->assertSee($registration->registration_number);
        $success->assertDontSee($registration->photo_path);
        $success->assertDontSee($registration->payment_proof_path);
    }

    public function test_malicious_server_controlled_fields_in_the_request_are_ignored(): void
    {
        Storage::fake('local');
        $edition = $this->openEdition(['registration_fee' => 400.00]);
        $otherEdition = Edition::factory()->create(['status' => 'active']);

        $this->submit($this->validPayload([
            'payment_status' => 'paid',
            'registration_fee' => '99999.99',
            'payment_reference' => 'FAKE-REF',
            'registration_number' => 'RPPL-9999-999999',
            'edition_id' => $otherEdition->id,
            // Fields the form no longer asks for.
            'date_of_birth' => '2000-01-01',
            'aadhaar_document' => $this->photo(),
        ]))->assertRedirect(route('public.player-registration.success'));

        $registration = PlayerRegistration::firstOrFail();
        $this->assertSame($edition->id, $registration->edition_id); // never $otherEdition
        $this->assertSame('pending', $registration->payment_status);
        $this->assertSame('400.00', (string) $registration->registration_fee);
        $this->assertNull($registration->payment_reference);
        $this->assertNull($registration->aadhaar_document_path);
        $this->assertNull($registration->player->date_of_birth);
        $this->assertNotSame('RPPL-9999-999999', $registration->registration_number);
    }

    public function test_the_utr_is_stored_as_one_upper_case_token(): void
    {
        Storage::fake('local');
        $this->openEdition();

        $this->submit($this->validPayload(['submitted_utr' => ' ab12 3456 cd78 ']))
            ->assertRedirect(route('public.player-registration.success'));

        $this->assertSame('AB123456CD78', PlayerRegistration::firstOrFail()->submitted_utr);
    }

    // ----- Existing player reuse -----

    public function test_existing_player_is_reused_and_master_fields_are_never_overwritten(): void
    {
        Storage::fake('local');
        $edition = $this->openEdition();
        $existing = Player::factory()->create([
            'name' => 'Original Name',
            'phone' => '9876543210',
            'email' => 'original@example.com',
            'date_of_birth' => '1995-05-05',
            'primary_role' => null,
            'batting_style' => null,
            'bowling_style' => null,
        ]);

        // Same phone, but a different name/role/hands submitted — must
        // reuse the existing Player and NOT touch any of its fields.
        $this->submit($this->validPayload([
            'name' => 'Different Spelling',
            'phone' => '9876543210',
            'email' => 'different@example.com',
            'primary_role' => 'bowler',
            'batting_style' => 'left_hand',
            'bowling_style' => 'left_arm',
        ]))->assertRedirect(route('public.player-registration.success'));

        $this->assertSame(1, Player::count());
        $existing->refresh();
        $this->assertSame('Original Name', $existing->name);
        $this->assertSame('original@example.com', $existing->email);
        $this->assertSame('1995-05-05', $existing->date_of_birth->format('Y-m-d'));
        $this->assertNull($existing->primary_role);
        $this->assertNull($existing->batting_style);
        $this->assertNull($existing->bowling_style);

        // The answers for THIS edition are kept on the registration anyway.
        $registration = PlayerRegistration::where('player_id', $existing->id)->firstOrFail();
        $this->assertSame($edition->id, $registration->edition_id);
        $this->assertSame(24, $registration->age);
        $this->assertSame('Sendriya', $registration->village);
        $this->assertSame('402912345678', $registration->submitted_utr);
    }

    // ----- Identity: the mobile number decides who someone is -----

    public function test_an_email_that_belongs_to_another_player_does_not_merge_or_block(): void
    {
        Storage::fake('local');
        $this->openEdition();
        $other = Player::factory()->create(['phone' => '9998887766', 'email' => 'shared@example.com']);

        // A different person (new mobile number) giving an email that
        // already belongs to someone else — e.g. a family address.
        $this->submit($this->validPayload(['phone' => '9111111111', 'email' => 'shared@example.com']))
            ->assertRedirect(route('public.player-registration.success'));

        $newcomer = Player::firstWhere('phone', '9111111111');
        $this->assertNotNull($newcomer);
        $this->assertNotSame($other->id, $newcomer->id);
        $this->assertNull($newcomer->email, 'the address stays with its first owner');
        $this->assertSame(0, PlayerRegistration::where('player_id', $other->id)->count());
        $this->assertSame(1, PlayerRegistration::where('player_id', $newcomer->id)->count());
    }

    public function test_the_player_who_owns_the_phone_is_registered_whatever_email_is_given(): void
    {
        Storage::fake('local');
        $this->openEdition();
        $owner = Player::factory()->create(['phone' => '9876543210', 'email' => null]);
        $emailOwner = Player::factory()->create(['phone' => '9998887766', 'email' => 'other@example.com']);

        $this->submit($this->validPayload(['phone' => '9876543210', 'email' => 'other@example.com']))
            ->assertRedirect(route('public.player-registration.success'));

        $this->assertSame(2, Player::count());
        $this->assertSame(1, PlayerRegistration::where('player_id', $owner->id)->count());
        $this->assertSame(0, PlayerRegistration::where('player_id', $emailOwner->id)->count());
        $this->assertNull($owner->fresh()->email, 'an existing player is never edited by the form');
    }

    // ----- Rejections -----

    public function test_inactive_matched_player_is_rejected_with_zero_writes(): void
    {
        Storage::fake('local');
        $this->openEdition();
        Player::factory()->create(['phone' => '9876543210', 'is_active' => false]);

        $this->submit($this->validPayload(['phone' => '9876543210']))
            ->assertSessionHasErrors('phone');

        $this->assertSame(0, PlayerRegistration::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_same_edition_duplicate_is_rejected_without_leaking_the_existing_registration_number(): void
    {
        Storage::fake('local');
        $edition = $this->openEdition();
        $player = Player::factory()->create(['phone' => '9876543210']);
        $existingRegistration = PlayerRegistration::factory()->create([
            'edition_id' => $edition->id,
            'player_id' => $player->id,
        ])->assignRegistrationNumber();

        $response = $this->submit($this->validPayload(['phone' => '9876543210']));
        $response->assertSessionHasErrors('phone');
        $response->assertDontSee($existingRegistration->registration_number);

        $this->assertSame(1, PlayerRegistration::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    // ----- Edition eligibility at write time -----

    public function test_closed_completed_or_unconfigured_fee_edition_rejects_post(): void
    {
        Storage::fake('local');

        // No open edition at all: a plain flash message, not a
        // per-field validation error (there is no edition context to
        // attach one to).
        $this->submit($this->validPayload())->assertSessionHas('error');
        $this->assertSame(0, PlayerRegistration::count());

        // A "completed" edition that still has registration_open=true
        // (e.g. left over from before it completed) must still be
        // treated as closed to the public — status alone excludes it.
        Edition::factory()->create(['status' => 'completed', 'registration_open' => true, 'registration_fee' => 400]);
        $this->submit($this->validPayload(['phone' => '9111111111']))->assertSessionHas('error');
        $this->assertSame(0, PlayerRegistration::count());
    }

    public function test_edition_closing_between_precheck_and_write_is_rejected_and_cleans_up_stored_files(): void
    {
        Storage::fake('local');
        $edition = $this->openEdition();

        // Simulate an admin closing registration in the instant between
        // this service call's own resolution and its locked, authoritative
        // re-check — bypassing the $edition object entirely, exactly like
        // a genuinely concurrent admin action would.
        DB::table('editions')->where('id', $edition->id)->update(['registration_open' => false]);

        $this->expectException(ValidationException::class);

        try {
            // $edition is deliberately the STALE in-memory object fetched
            // before the DB::table() update above — it still reports
            // registration_open = true, exactly like a request that
            // resolved the edition moments before a concurrent admin
            // action closed it. The service's own locked re-check must
            // catch this from the database, not trust the object it was
            // handed.
            app(GuestPlayerRegistrationService::class)->register(
                $edition,
                $this->validPayload(),
                $this->photo(),
                $this->paymentProof(),
            );
        } finally {
            $this->assertSame(0, PlayerRegistration::count());
            $this->assertEmpty(Storage::disk('local')->allFiles(), 'stored files must be cleaned up when the authoritative write fails');
        }
    }

    // ----- Payment proof OCR dispatch (Phase B) -----

    public function test_successful_registration_dispatches_ocr_processing_for_the_new_registration(): void
    {
        Storage::fake('local');
        Queue::fake();
        $edition = $this->openEdition();

        $this->submit($this->validPayload())->assertRedirect(route('public.player-registration.success'));

        $registration = PlayerRegistration::firstOrFail();
        Queue::assertPushed(ProcessPaymentProofOcr::class, fn ($job) => $job->registration->is($registration));
    }

    /**
     * The core Phase B safety invariant, proven at the service boundary
     * rather than via a brittle framework-level queue-connection
     * simulation: a queue dispatch failure must never roll back the
     * already-committed registration, delete its just-stored files, or
     * surface an error in place of the successful result. Forcing the
     * default queue connection to one that doesn't exist makes
     * ProcessPaymentProofOcr::dispatch() itself throw when it tries to
     * resolve a connection — a genuine dispatch-time failure, not a
     * mocked-away one.
     */
    public function test_registration_succeeds_even_when_ocr_dispatch_itself_fails(): void
    {
        Storage::fake('local');
        $edition = $this->openEdition();
        config(['queue.default' => 'nonexistent-connection']);

        $registration = app(GuestPlayerRegistrationService::class)->register(
            $edition,
            $this->validPayload(),
            $this->photo(),
            $this->paymentProof(),
        );

        $this->assertInstanceOf(PlayerRegistration::class, $registration);
        $this->assertTrue($registration->exists);
        $this->assertDatabaseHas('player_registrations', ['id' => $registration->id]);
        Storage::disk('local')->assertExists($registration->photo_path);
        Storage::disk('local')->assertExists($registration->payment_proof_path);
    }
}
