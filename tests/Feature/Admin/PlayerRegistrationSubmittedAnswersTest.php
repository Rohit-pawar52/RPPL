<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The admin side of what the public registration form now collects: the
 * private photo, the UTR the player typed (and how it compares with the
 * payment screenshot), and the cleanup of the new private file.
 */
class PlayerRegistrationSubmittedAnswersTest extends TestCase
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

    private function registrationWithPhoto(array $overrides = []): PlayerRegistration
    {
        $path = UploadedFile::fake()->create('my-own-name.jpg', 100, 'image/jpeg')->store('player-registrations/photos', 'local');

        return PlayerRegistration::factory()->create(array_merge(['photo_path' => $path], $overrides))->assignRegistrationNumber();
    }

    // ----- The private photo -----

    public function test_admin_can_view_the_submitted_photo_but_a_scorer_and_a_guest_cannot(): void
    {
        $registration = $this->registrationWithPhoto();
        $url = route('admin.player-registrations.photo', $registration);

        $response = $this->actingAs($this->admin())->get($url)->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');

        // The download name is generated from the registration number, never the upload's name.
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString(strtolower($registration->registration_number), $disposition);
        $this->assertStringContainsString('photo', $disposition);
        $this->assertStringNotContainsString('my-own-name', $disposition);

        $this->actingAs(User::factory()->create(['role_id' => $this->scorerRole->id]))->get($url)->assertForbidden();

        auth()->logout();
        $this->get($url)->assertRedirect(route('admin.login'));
    }

    public function test_a_registration_without_a_photo_or_with_a_missing_file_is_a_clean_404(): void
    {
        $admin = $this->admin();

        $withoutPhoto = PlayerRegistration::factory()->create(['photo_path' => null]);
        $this->actingAs($admin)->get(route('admin.player-registrations.photo', $withoutPhoto))->assertNotFound();

        $missingFile = PlayerRegistration::factory()->create(['photo_path' => 'player-registrations/photos/gone.jpg']);
        $this->actingAs($admin)->get(route('admin.player-registrations.photo', $missingFile))->assertNotFound();
    }

    public function test_the_photo_path_cannot_be_changed_through_the_edit_form(): void
    {
        $registration = $this->registrationWithPhoto();
        $original = $registration->photo_path;

        $this->actingAs($this->admin())->put(route('admin.player-registrations.update', $registration), [
            'payment_status' => 'paid',
            'photo_path' => 'player-registrations/photos/tampered.jpg',
        ]);

        $this->assertSame($original, $registration->fresh()->photo_path);
    }

    public function test_the_show_page_links_the_submitted_photo_and_shows_the_bowling_arm(): void
    {
        $registration = $this->registrationWithPhoto();
        $registration->player->update(['bowling_style' => 'left_arm']);

        $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.show', $registration))
            ->assertOk()
            ->assertSee('Bowling arm')
            ->assertSee('Left arm')
            ->assertSee('View submitted photo')
            ->assertSee(route('admin.player-registrations.photo', $registration), false);
    }

    public function test_the_show_page_previews_the_images_inline(): void
    {
        $registration = $this->registrationWithPhoto();
        $proof = UploadedFile::fake()->create('p.png', 10, 'image/png')->store('player-registrations/payment-proofs', 'local');
        $pdf = UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')->store('player-registrations/aadhaar', 'local');
        $registration->update(['payment_proof_path' => $proof, 'aadhaar_document_path' => $pdf]);

        $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.show', $registration))
            ->assertOk()
            ->assertSee('<img src="'.route('admin.player-registrations.photo', $registration).'"', false)
            ->assertSee('<img src="'.route('admin.player-registrations.payment-proof', $registration).'"', false)
            // A PDF cannot be an <img>; it keeps its button only.
            ->assertDontSee('<img src="'.route('admin.player-registrations.aadhaar', $registration).'"', false);
    }

    public function test_a_registration_with_only_drive_links_shows_no_broken_preview(): void
    {
        $registration = PlayerRegistration::factory()->create(['photo_url' => 'https://drive.google.com/open?id=ABC123456', 'photo_path' => null]);

        $this->actingAs($this->admin())
            ->get(route('admin.player-registrations.show', $registration))
            ->assertOk()
            ->assertDontSee('<img', false);
    }

    public function test_deleting_a_registration_removes_its_submitted_photo_too(): void
    {
        $registration = $this->registrationWithPhoto();
        $path = $registration->photo_path;
        Storage::disk('local')->assertExists($path);

        $this->actingAs($this->admin())
            ->delete(route('admin.player-registrations.destroy', $registration))
            ->assertRedirect(route('admin.player-registrations.index'));

        $this->assertModelMissing($registration);
        Storage::disk('local')->assertMissing($path);
    }

    // ----- The UTR the player typed -----

    public function test_the_show_page_flags_a_utr_that_another_registration_also_typed(): void
    {
        $first = PlayerRegistration::factory()->create(['submitted_utr' => 'AB123456CD78']);
        PlayerRegistration::factory()->create(['submitted_utr' => 'ab123456cd78']);
        $unique = PlayerRegistration::factory()->create(['submitted_utr' => 'UNIQUE123456']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.player-registrations.show', $first))
            ->assertOk()
            ->assertSee('AB123456CD78')
            ->assertSee('also typed on another registration');

        $this->actingAs($admin)->get(route('admin.player-registrations.show', $unique))
            ->assertOk()
            ->assertSee('UNIQUE123456')
            ->assertDontSee('also typed on another registration');
    }

    public function test_the_show_page_compares_the_typed_utr_with_the_one_read_from_the_screenshot(): void
    {
        $admin = $this->admin();

        $matching = PlayerRegistration::factory()->create([
            'submitted_utr' => 'ABC12345678',
            'ocr_status' => 'extracted',
            'ocr_transaction_id' => 'abc 12345678',
        ]);
        $this->actingAs($admin)->get(route('admin.player-registrations.show', $matching))
            ->assertSee('matches the screenshot')
            ->assertDontSee('differs from the screenshot');

        $differing = PlayerRegistration::factory()->create([
            'submitted_utr' => 'ABC12345678',
            'ocr_status' => 'extracted',
            'ocr_transaction_id' => 'XYZ98765432',
        ]);
        $this->actingAs($admin)->get(route('admin.player-registrations.show', $differing))
            ->assertSee('differs from the screenshot')
            ->assertDontSee('matches the screenshot');

        // Nothing was read off the screenshot: no verdict either way.
        $unread = PlayerRegistration::factory()->create(['submitted_utr' => 'ABC12345678', 'ocr_status' => 'not_found', 'ocr_transaction_id' => null]);
        $this->actingAs($admin)->get(route('admin.player-registrations.show', $unread))
            ->assertDontSee('matches the screenshot')
            ->assertDontSee('differs from the screenshot');
    }

    // ----- Data cleanup of the new private file -----

    public function test_data_cleanup_can_remove_just_the_submitted_photos(): void
    {
        $edition = Edition::factory()->create(['registration_open' => false]);
        $admin = $this->admin();

        $photo = UploadedFile::fake()->create('p.jpg', 10, 'image/jpeg')->store('player-registrations/photos', 'local');
        $proof = UploadedFile::fake()->create('s.jpg', 10, 'image/jpeg')->store('player-registrations/payment-proofs', 'local');
        $registration = PlayerRegistration::factory()->create([
            'edition_id' => $edition->id,
            'photo_path' => $photo,
            'payment_proof_path' => $proof,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.data-cleanup.preview.registration-documents', ['edition_id' => $edition->id, 'document_type' => 'photo']))
            ->assertOk()
            ->assertJson(['photo' => 1, 'aadhaar' => 0, 'payment_proof' => 0]);

        // "Aadhaar + Payment Proof" never touches the photo.
        $this->actingAs($admin)->delete(route('admin.data-cleanup.registration-documents.destroy'), [
            'edition_id' => $edition->id,
            'document_type' => 'both',
        ]);
        $registration->refresh();
        $this->assertSame($photo, $registration->photo_path);
        $this->assertNull($registration->payment_proof_path);
        Storage::disk('local')->assertExists($photo);
        Storage::disk('local')->assertMissing($proof);

        $this->actingAs($admin)->delete(route('admin.data-cleanup.registration-documents.destroy'), [
            'edition_id' => $edition->id,
            'document_type' => 'photo',
        ])->assertRedirect(route('admin.data-cleanup.index', ['tab' => 'registration-documents']));

        $registration->refresh();
        $this->assertNull($registration->photo_path);
        Storage::disk('local')->assertMissing($photo);
        $this->assertModelExists($registration);
        $this->assertNotNull(Player::find($registration->player_id));
    }
}
