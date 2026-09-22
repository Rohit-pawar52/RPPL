<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlayerRegistrationManagementTest extends TestCase
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

    // ----- Authorization -----

    public function test_guest_cannot_access_registrations(): void
    {
        $this->get(route('admin.player-registrations.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_scorer_is_forbidden_from_registration_management(): void
    {
        $scorer = $this->scorer();
        $registration = PlayerRegistration::factory()->create();

        $this->actingAs($scorer)->get(route('admin.player-registrations.index'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.player-registrations.create'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.player-registrations.show', $registration))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.player-registrations.edit', $registration))->assertForbidden();
        $this->actingAs($scorer)->put(route('admin.player-registrations.update', $registration), [
            'payment_status' => 'paid',
        ])->assertForbidden();
        $this->actingAs($scorer)->delete(route('admin.player-registrations.destroy', $registration))->assertForbidden();
    }

    public function test_admin_can_list_registrations(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.index'))
            ->assertOk()
            ->assertViewIs('admin.player-registrations.index');
    }

    // ----- Create -----

    public function test_admin_can_create_registration(): void
    {
        $edition = Edition::factory()->create(['status' => 'upcoming']);
        $player = Player::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.player-registrations.store'), [
            'edition_id' => $edition->id,
            'player_id' => $player->id,
            'payment_status' => 'pending',
            'registration_fee' => 400,
        ]);

        $response->assertRedirect(route('admin.player-registrations.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('player_registrations', [
            'edition_id' => $edition->id,
            'player_id' => $player->id,
            'payment_status' => 'pending',
        ]);
    }

    public function test_inactive_player_cannot_be_newly_registered(): void
    {
        $edition = Edition::factory()->create(['status' => 'upcoming']);
        $player = Player::factory()->inactive()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.player-registrations.store'), [
            'edition_id' => $edition->id,
            'player_id' => $player->id,
            'payment_status' => 'pending',
        ]);

        $response->assertSessionHasErrors('player_id');
        $this->assertDatabaseCount('player_registrations', 0);
    }

    public function test_completed_edition_cannot_accept_new_registration(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        $player = Player::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.player-registrations.store'), [
            'edition_id' => $edition->id,
            'player_id' => $player->id,
            'payment_status' => 'pending',
        ]);

        $response->assertSessionHasErrors('edition_id');
        $this->assertDatabaseCount('player_registrations', 0);
    }

    public function test_duplicate_player_and_edition_registration_is_rejected(): void
    {
        $edition = Edition::factory()->create(['status' => 'upcoming']);
        $player = Player::factory()->create();
        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'player_id' => $player->id]);

        $response = $this->actingAs($this->admin())->post(route('admin.player-registrations.store'), [
            'edition_id' => $edition->id,
            'player_id' => $player->id,
            'payment_status' => 'pending',
        ]);

        $response->assertSessionHasErrors('player_id');
        $this->assertDatabaseCount('player_registrations', 1);
    }

    public function test_same_player_can_register_in_a_different_eligible_edition(): void
    {
        $editionOne = Edition::factory()->create(['status' => 'upcoming']);
        $editionTwo = Edition::factory()->create(['status' => 'upcoming']);
        $player = Player::factory()->create();
        PlayerRegistration::factory()->create(['edition_id' => $editionOne->id, 'player_id' => $player->id]);

        $response = $this->actingAs($this->admin())->post(route('admin.player-registrations.store'), [
            'edition_id' => $editionTwo->id,
            'player_id' => $player->id,
            'payment_status' => 'pending',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('player_registrations', 2);
    }

    public function test_different_players_can_register_in_the_same_edition(): void
    {
        $edition = Edition::factory()->create(['status' => 'upcoming']);
        $playerOne = Player::factory()->create();
        $playerTwo = Player::factory()->create();
        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'player_id' => $playerOne->id]);

        $response = $this->actingAs($this->admin())->post(route('admin.player-registrations.store'), [
            'edition_id' => $edition->id,
            'player_id' => $playerTwo->id,
            'payment_status' => 'pending',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('player_registrations', 2);
    }

    public function test_create_validation_requires_edition_and_player(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.player-registrations.store'), []);

        $response->assertSessionHasErrors(['edition_id', 'player_id', 'payment_status']);
        $this->assertDatabaseCount('player_registrations', 0);
    }

    // ----- Show / Update -----

    public function test_admin_can_view_registration(): void
    {
        $registration = PlayerRegistration::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.show', $registration))
            ->assertOk()
            ->assertSee($registration->player->name);
    }

    /**
     * Pre-UAT audit fix: ocr_transaction_id/ocr_status were captured by
     * the OCR job but never surfaced anywhere in the admin UI, so an
     * admin had no way to compare the extracted candidate against the
     * payment reference they enter. A duplicate-warning badge should
     * also appear when hasDuplicateOcrTransactionId() is true.
     */
    public function test_admin_can_see_the_ocr_suggestion_and_duplicate_warning(): void
    {
        PlayerRegistration::factory()->create([
            'ocr_status' => 'extracted',
            'ocr_transaction_id' => 'TXN12345',
        ]);
        $registration = PlayerRegistration::factory()->create([
            'ocr_status' => 'extracted',
            'ocr_transaction_id' => 'TXN12345',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.show', $registration));

        $response->assertOk();
        $response->assertSee('TXN12345');
        $response->assertSee('also seen on another registration');
    }

    public function test_pending_ocr_status_shows_a_neutral_placeholder(): void
    {
        $registration = PlayerRegistration::factory()->create(['ocr_status' => 'pending', 'ocr_transaction_id' => null]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.show', $registration));

        $response->assertOk();
        $response->assertSee('Pending');
    }

    public function test_admin_can_update_editable_metadata(): void
    {
        $registration = PlayerRegistration::factory()->create(['payment_status' => 'pending']);

        $response = $this->actingAs($this->admin())->put(route('admin.player-registrations.update', $registration), [
            'payment_status' => 'paid',
            'registration_fee' => 500,
        ]);

        $response->assertRedirect(route('admin.player-registrations.index'));
        $this->assertDatabaseHas('player_registrations', [
            'id' => $registration->id,
            'payment_status' => 'paid',
        ]);
    }

    // ----- Payment verification (Phase 3.39D) -----

    public function test_admin_can_mark_a_pending_registration_paid_and_set_payment_reference(): void
    {
        $registration = PlayerRegistration::factory()->create(['payment_status' => 'pending', 'payment_reference' => null]);

        $response = $this->actingAs($this->admin())->put(route('admin.player-registrations.update', $registration), [
            'payment_status' => 'paid',
            'registration_fee' => $registration->registration_fee,
            'payment_reference' => '  UTR12345  ',
        ]);

        $response->assertRedirect(route('admin.player-registrations.index'));
        $this->assertDatabaseHas('player_registrations', [
            'id' => $registration->id,
            'payment_status' => 'paid',
            'payment_reference' => 'UTR12345',
        ]);
    }

    public function test_payment_reference_is_optional_and_may_be_cleared(): void
    {
        $registration = PlayerRegistration::factory()->create(['payment_status' => 'paid', 'payment_reference' => 'OLD-REF']);

        // paid does not require a reference to be present.
        $response = $this->actingAs($this->admin())->put(route('admin.player-registrations.update', $registration), [
            'payment_status' => 'paid',
            'registration_fee' => $registration->registration_fee,
            'payment_reference' => '',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('player_registrations', [
            'id' => $registration->id,
            'payment_reference' => null,
        ]);
    }

    public function test_payment_reference_over_max_length_is_rejected(): void
    {
        $registration = PlayerRegistration::factory()->create();

        $response = $this->actingAs($this->admin())->put(route('admin.player-registrations.update', $registration), [
            'payment_status' => 'paid',
            'payment_reference' => str_repeat('a', 101),
        ]);

        $response->assertSessionHasErrors('payment_reference');
    }

    public function test_registration_number_and_document_paths_cannot_be_changed_through_update(): void
    {
        $registration = PlayerRegistration::factory()->create([
            'aadhaar_document_path' => 'player-registrations/aadhaar/original.jpg',
            'payment_proof_path' => 'player-registrations/payment-proofs/original.jpg',
        ])->assignRegistrationNumber();
        $originalNumber = $registration->registration_number;

        $this->actingAs($this->admin())->put(route('admin.player-registrations.update', $registration), [
            'payment_status' => 'paid',
            'registration_number' => 'RPPL-9999-999999',
            'aadhaar_document_path' => 'tampered.jpg',
            'payment_proof_path' => 'tampered.jpg',
        ]);

        $this->assertDatabaseHas('player_registrations', [
            'id' => $registration->id,
            'registration_number' => $originalNumber,
            'aadhaar_document_path' => 'player-registrations/aadhaar/original.jpg',
            'payment_proof_path' => 'player-registrations/payment-proofs/original.jpg',
        ]);
    }

    public function test_edition_and_player_identity_cannot_be_changed_through_update(): void
    {
        $originalEdition = Edition::factory()->create();
        $originalPlayer = Player::factory()->create();
        $otherEdition = Edition::factory()->create();
        $otherPlayer = Player::factory()->create();

        $registration = PlayerRegistration::factory()->create([
            'edition_id' => $originalEdition->id,
            'player_id' => $originalPlayer->id,
        ]);

        $this->actingAs($this->admin())->put(route('admin.player-registrations.update', $registration), [
            'edition_id' => $otherEdition->id,
            'player_id' => $otherPlayer->id,
            'payment_status' => 'paid',
        ]);

        $this->assertDatabaseHas('player_registrations', [
            'id' => $registration->id,
            'edition_id' => $originalEdition->id,
            'player_id' => $originalPlayer->id,
        ]);
    }

    public function test_inactive_player_on_existing_registration_remains_viewable_and_editable(): void
    {
        $player = Player::factory()->inactive()->create();
        $registration = PlayerRegistration::factory()->create(['player_id' => $player->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.show', $registration))
            ->assertOk()
            ->assertSee($player->name);

        $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.edit', $registration))
            ->assertOk()
            ->assertSee($player->name);

        $this->actingAs($this->admin())
            ->put(route('admin.player-registrations.update', $registration), ['payment_status' => 'paid'])
            ->assertRedirect(route('admin.player-registrations.index'));

        $this->assertDatabaseHas('player_registrations', ['id' => $registration->id, 'payment_status' => 'paid']);
    }

    public function test_completed_editions_existing_registration_remains_viewable(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        $registration = PlayerRegistration::factory()->create(['edition_id' => $edition->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.show', $registration))
            ->assertOk()
            ->assertSee($edition->name);
    }

    // ----- Delete -----

    public function test_registration_with_no_squad_assignment_can_be_deleted(): void
    {
        $registration = PlayerRegistration::factory()->create();

        $response = $this->actingAs($this->admin())->delete(route('admin.player-registrations.destroy', $registration));

        $response->assertRedirect(route('admin.player-registrations.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('player_registrations', ['id' => $registration->id]);
    }

    public function test_deleting_a_registration_also_deletes_its_owned_private_documents(): void
    {
        Storage::fake('local');
        $aadhaarPath = UploadedFile::fake()->create('aadhaar.jpg', 100, 'image/jpeg')
            ->store('player-registrations/aadhaar', 'local');
        $proofPath = UploadedFile::fake()->create('proof.jpg', 100, 'image/jpeg')
            ->store('player-registrations/payment-proofs', 'local');

        $registration = PlayerRegistration::factory()->create([
            'aadhaar_document_path' => $aadhaarPath,
            'payment_proof_path' => $proofPath,
        ]);

        $response = $this->actingAs($this->admin())->delete(route('admin.player-registrations.destroy', $registration));

        $response->assertRedirect(route('admin.player-registrations.index'));
        $this->assertDatabaseMissing('player_registrations', ['id' => $registration->id]);
        Storage::disk('local')->assertMissing($aadhaarPath);
        Storage::disk('local')->assertMissing($proofPath);
    }

    public function test_registration_assigned_to_squad_cannot_be_deleted(): void
    {
        $registration = PlayerRegistration::factory()->create();
        $team = Team::factory()->create();
        $editionTeam = EditionTeam::factory()->create([
            'edition_id' => $registration->edition_id,
            'team_id' => $team->id,
        ]);
        TeamPlayer::factory()->create([
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registration->id,
        ]);

        $response = $this->actingAs($this->admin())->delete(route('admin.player-registrations.destroy', $registration));

        $response->assertRedirect(route('admin.player-registrations.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('player_registrations', ['id' => $registration->id]);
    }

    public function test_deletion_block_preserves_registration_and_its_payment_status(): void
    {
        $registration = PlayerRegistration::factory()->create(['payment_status' => 'paid']);
        $team = Team::factory()->create();
        $editionTeam = EditionTeam::factory()->create([
            'edition_id' => $registration->edition_id,
            'team_id' => $team->id,
        ]);
        TeamPlayer::factory()->create([
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registration->id,
        ]);

        $this->actingAs($this->admin())->delete(route('admin.player-registrations.destroy', $registration));

        $this->assertDatabaseHas('player_registrations', ['id' => $registration->id, 'payment_status' => 'paid']);
    }

    public function test_blocked_deletion_leaves_owned_documents_in_place(): void
    {
        Storage::fake('local');
        $aadhaarPath = UploadedFile::fake()->create('aadhaar.jpg', 100, 'image/jpeg')
            ->store('player-registrations/aadhaar', 'local');

        $registration = PlayerRegistration::factory()->create(['aadhaar_document_path' => $aadhaarPath]);
        $team = Team::factory()->create();
        $editionTeam = EditionTeam::factory()->create([
            'edition_id' => $registration->edition_id,
            'team_id' => $team->id,
        ]);
        TeamPlayer::factory()->create([
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registration->id,
        ]);

        $this->actingAs($this->admin())->delete(route('admin.player-registrations.destroy', $registration));

        Storage::disk('local')->assertExists($aadhaarPath);
    }

    public function test_unauthorized_delete_is_blocked_and_registration_survives(): void
    {
        $registration = PlayerRegistration::factory()->create();

        $this->delete(route('admin.player-registrations.destroy', $registration))
            ->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas('player_registrations', ['id' => $registration->id]);
    }

    // ----- Filters / Pagination -----

    public function test_edition_and_payment_status_filters_work(): void
    {
        $editionOne = Edition::factory()->create();
        $editionTwo = Edition::factory()->create();

        $matching = PlayerRegistration::factory()->create([
            'edition_id' => $editionOne->id,
            'payment_status' => 'paid',
        ]);
        PlayerRegistration::factory()->create([
            'edition_id' => $editionTwo->id,
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.player-registrations.index', [
            'edition_id' => $editionOne->id,
            'payment_status' => 'paid',
        ]));

        $response->assertSee($matching->player->name);
    }

    public function test_search_filters_by_player_name(): void
    {
        $matching = PlayerRegistration::factory()->create();
        $matching->player->update(['name' => 'Findable Player']);
        $other = PlayerRegistration::factory()->create();
        $other->player->update(['name' => 'Other Player']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.index', ['search' => 'Findable']));

        $response->assertSee('Findable Player')->assertDontSee('Other Player');
    }

    public function test_search_filters_by_registration_number(): void
    {
        $matching = PlayerRegistration::factory()->create()->assignRegistrationNumber();
        $other = PlayerRegistration::factory()->create()->assignRegistrationNumber();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.index', ['search' => $matching->registration_number]));

        $response->assertSee($matching->registration_number)->assertDontSee($other->registration_number);
    }

    public function test_search_filters_by_player_phone(): void
    {
        $matching = PlayerRegistration::factory()->create();
        $matching->player->update(['phone' => '9876543210']);
        $other = PlayerRegistration::factory()->create();
        $other->player->update(['phone' => '9998887766']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.index', ['search' => '9876543210']));

        $response->assertSee($matching->player->name)->assertDontSee($other->player->name);
    }

    public function test_pagination_preserves_filters(): void
    {
        $edition = Edition::factory()->create();
        PlayerRegistration::factory()->count(20)->create(['edition_id' => $edition->id]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.index', ['edition_id' => $edition->id, 'page' => 2]));

        $response->assertOk();
        $response->assertSee('edition_id='.$edition->id, false);
    }

    // ----- Date range -----

    public function test_date_range_filters_by_registered_at(): void
    {
        $inRange = PlayerRegistration::factory()->create(['registered_at' => '2026-03-15']);
        $before = PlayerRegistration::factory()->create(['registered_at' => '2026-01-01']);
        $after = PlayerRegistration::factory()->create(['registered_at' => '2026-06-01']);

        $response = $this->actingAs($this->admin())->get(route('admin.player-registrations.index', [
            'from_date' => '2026-03-01',
            'to_date' => '2026-03-31',
        ]));

        $response->assertSee($inRange->registration_number)
            ->assertDontSee($before->registration_number)
            ->assertDontSee($after->registration_number);
    }

    public function test_from_date_only_filters_open_ended(): void
    {
        $recent = PlayerRegistration::factory()->create(['registered_at' => '2026-06-01']);
        $old = PlayerRegistration::factory()->create(['registered_at' => '2026-01-01']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.index', ['from_date' => '2026-05-01']));

        $response->assertSee($recent->registration_number)->assertDontSee($old->registration_number);
    }

    public function test_to_date_only_filters_open_started(): void
    {
        $old = PlayerRegistration::factory()->create(['registered_at' => '2026-01-01']);
        $recent = PlayerRegistration::factory()->create(['registered_at' => '2026-06-01']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.index', ['to_date' => '2026-02-01']));

        $response->assertSee($old->registration_number)->assertDontSee($recent->registration_number);
    }

    public function test_to_date_before_from_date_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.player-registrations.index', [
            'from_date' => '2026-06-01',
            'to_date' => '2026-01-01',
        ]));

        $response->assertSessionHasErrors('to_date');
    }

    // ----- Sorting -----

    public function test_sorting_ascending_by_registration_fee(): void
    {
        $cheap = PlayerRegistration::factory()->create(['registration_fee' => 100]);
        $expensive = PlayerRegistration::factory()->create(['registration_fee' => 900]);

        $response = $this->actingAs($this->admin())->get(route('admin.player-registrations.index', [
            'sort' => 'registration_fee', 'direction' => 'asc',
        ]));

        $body = $response->getContent();
        $this->assertLessThan(
            strpos($body, $expensive->registration_number),
            strpos($body, $cheap->registration_number)
        );
    }

    public function test_sorting_descending_by_registration_fee(): void
    {
        $cheap = PlayerRegistration::factory()->create(['registration_fee' => 100]);
        $expensive = PlayerRegistration::factory()->create(['registration_fee' => 900]);

        $response = $this->actingAs($this->admin())->get(route('admin.player-registrations.index', [
            'sort' => 'registration_fee', 'direction' => 'desc',
        ]));

        $body = $response->getContent();
        $this->assertLessThan(
            strpos($body, $cheap->registration_number),
            strpos($body, $expensive->registration_number)
        );
    }

    public function test_invalid_sort_column_falls_back_to_default_safely(): void
    {
        PlayerRegistration::factory()->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.index', ['sort' => 'password']));

        $response->assertOk();
    }

    // ----- Selected-rows export -----

    public function test_selected_export_contains_only_the_selected_registrations(): void
    {
        $selected = PlayerRegistration::factory()->create();
        $notSelected = PlayerRegistration::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.player-registrations.export-selected'), [
            'selected_ids' => [$selected->id],
        ]);

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString($selected->registration_number, $content);
        $this->assertStringNotContainsString($notSelected->registration_number, $content);
    }

    public function test_selected_export_ignores_ambient_filters(): void
    {
        $editionOne = Edition::factory()->create();
        $editionTwo = Edition::factory()->create();
        $selected = PlayerRegistration::factory()->create(['edition_id' => $editionTwo->id]);

        $response = $this->actingAs($this->admin())->post(
            route('admin.player-registrations.export-selected', ['edition_id' => $editionOne->id]),
            ['selected_ids' => [$selected->id]]
        );

        $response->assertOk();
        $this->assertStringContainsString($selected->registration_number, $response->streamedContent());
    }

    public function test_selected_export_rejects_a_nonexistent_id(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.player-registrations.export-selected'), [
            'selected_ids' => [999999],
        ]);

        $response->assertSessionHasErrors('selected_ids.0');
    }

    public function test_selected_export_requires_at_least_one_id(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.player-registrations.export-selected'), [
            'selected_ids' => [],
        ]);

        $response->assertSessionHasErrors('selected_ids');
    }

    public function test_scorer_cannot_use_selected_export(): void
    {
        $registration = PlayerRegistration::factory()->create();

        $this->actingAs($this->scorer())
            ->post(route('admin.player-registrations.export-selected'), ['selected_ids' => [$registration->id]])
            ->assertForbidden();
    }

    // ----- UI -----

    public function test_admin_sidebar_contains_registrations_link(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertSee(route('admin.player-registrations.index'), false);
    }

    public function test_scorer_sidebar_does_not_expose_registration_management(): void
    {
        $this->actingAs($this->scorer())
            ->get(route('admin.dashboard'))
            ->assertDontSee(route('admin.player-registrations.index'), false);
    }
}
