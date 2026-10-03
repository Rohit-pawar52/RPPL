<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\User;
use App\Support\DriveLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * An imported registration's Google Drive photo / screenshot is copied into
 * private storage (when Drive serves it without a login), and the photo can
 * become the public profile photo, resized.
 */
class RegistrationDriveFilesTest extends TestCase
{
    use RefreshDatabase;

    private const PHOTO_LINK = 'https://drive.google.com/open?id=PHOTOFILE123';

    private const PROOF_LINK = 'https://drive.google.com/open?id=PROOFFILE123';

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'slug' => 'admin']);
        Role::create(['name' => 'Scorer', 'slug' => 'scorer']);
        Storage::fake('local');
        Storage::fake('public');
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::firstWhere('slug', 'admin')->id]);
    }

    /**
     * A real 1x1 PNG (no GD needed to make one).
     */
    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    }

    private function sheet(string $photo = self::PHOTO_LINK, string $proof = self::PROOF_LINK): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'responses.csv',
            "Timestamp,Name,Mobile Number,Original Photos,Upload payment screenshot\n03/10/2026 10:00:00,Drive Player,9876543210,{$photo},{$proof}\n",
        );
    }

    private function import(UploadedFile $file)
    {
        $edition = Edition::factory()->create(['status' => 'active']);

        return $this->actingAs($this->admin())->post(route('admin.player-registrations.import.store'), [
            'edition_id' => $edition->id,
            'csv_file' => $file,
        ]);
    }

    public function test_import_copies_the_photo_and_screenshot_of_the_same_row_into_private_storage(): void
    {
        Http::fake(['drive.google.com/*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);

        $this->import($this->sheet())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('import_notes', fn (array $notes) => str_contains(implode("\n", $notes['info']), 'being copied in the background'));

        $registration = PlayerRegistration::sole();
        $this->assertSame('Drive Player', $registration->player->name);
        $this->assertStringStartsWith('player-registrations/photos/', $registration->photo_path);
        $this->assertStringStartsWith('player-registrations/payment-proofs/', $registration->payment_proof_path);
        Storage::disk('local')->assertExists($registration->photo_path);
        Storage::disk('local')->assertExists($registration->payment_proof_path);
        // The links stay, as a reference.
        $this->assertSame(self::PHOTO_LINK, $registration->photo_url);
        // Each file was asked for by its own id, on drive.google.com.
        Http::assertSent(fn ($request) => $request->url() === 'https://drive.google.com/uc?export=download&id=PHOTOFILE123');
        Http::assertSent(fn ($request) => $request->url() === 'https://drive.google.com/uc?export=download&id=PROOFFILE123');
        // Not public.
        $this->assertNull($registration->player->photo_path);
    }

    public function test_a_private_file_is_reported_and_keeps_its_link_without_breaking_the_import(): void
    {
        // Drive answers a private file with an HTML login page.
        Http::fake(['drive.google.com/*' => Http::response('<html>Sign in</html>', 200, ['Content-Type' => 'text/html'])]);

        $this->import($this->sheet())->assertSessionHasNoErrors()->assertSessionHas('success');

        $registration = PlayerRegistration::sole();
        $this->assertNull($registration->photo_path);
        $this->assertNull($registration->payment_proof_path);
        $this->assertSame(self::PHOTO_LINK, $registration->photo_url);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_a_link_without_a_file_id_is_never_requested(): void
    {
        Http::fake();

        $this->import($this->sheet('https://drive.google.com/drive/folders/', 'https://drive.google.com/drive/folders/'))
            ->assertSessionHasNoErrors();

        Http::assertNothingSent();
    }

    public function test_the_registration_page_button_copies_the_files_and_reports_what_failed(): void
    {
        $registration = PlayerRegistration::factory()->create(['photo_url' => self::PHOTO_LINK, 'payment_proof_url' => self::PROOF_LINK])
            ->assignRegistrationNumber();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.player-registrations.show', $registration))
            ->assertSee('Copy files from Google Drive');

        // The photo is shared, the screenshot is not.
        Http::fake([
            '*id=PHOTOFILE123*' => Http::response($this->png(), 200),
            // The first attempt is refused, the retry succeeds.
            '*id=PROOFFILE123*' => Http::sequence()->push('', 403)->push($this->png(), 200),
        ]);

        $this->actingAs($admin)->post(route('admin.player-registrations.fetch-files', $registration))
            ->assertSessionHas('warning', fn (string $message) => str_contains($message, 'Copied from Google Drive: photo.')
                && str_contains($message, 'The payment screenshot could not be copied')
                && str_contains($message, 'status 403'));

        $registration->refresh();
        $this->assertNotNull($registration->photo_path);
        $this->assertNull($registration->payment_proof_path);

        // Nothing left to copy for the photo; only the screenshot is retried.
        $this->actingAs($admin)->post(route('admin.player-registrations.fetch-files', $registration))
            ->assertSessionHas('success');
        $this->assertNotNull($registration->fresh()->payment_proof_path);

        $this->actingAs($admin)->post(route('admin.player-registrations.fetch-files', $registration))
            ->assertSessionHas('info');
    }

    public function test_only_an_admin_can_copy_files_or_set_a_profile_photo(): void
    {
        Http::fake();
        $registration = PlayerRegistration::factory()->create(['photo_url' => self::PHOTO_LINK]);
        $scorer = User::factory()->create(['role_id' => Role::firstWhere('slug', 'scorer')->id]);

        $this->actingAs($scorer)->post(route('admin.player-registrations.fetch-files', $registration))->assertForbidden();
        $this->actingAs($scorer)->post(route('admin.player-registrations.profile-photo', $registration))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_the_submitted_photo_becomes_the_players_resized_public_profile_photo(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('The GD extension is needed to build a large test image.');
        }

        $big = imagecreatetruecolor(2000, 1000);
        ob_start();
        imagejpeg($big);
        $bytes = (string) ob_get_clean();

        $path = 'player-registrations/photos/big.jpg';
        Storage::disk('local')->put($path, $bytes);
        Storage::disk('public')->put('players/old.jpg', 'old');
        $player = Player::factory()->create(['photo_path' => 'players/old.jpg']);
        $registration = PlayerRegistration::factory()->create(['player_id' => $player->id, 'photo_path' => $path]);

        $this->actingAs($this->admin())
            ->post(route('admin.player-registrations.profile-photo', $registration))
            ->assertSessionHas('success');

        $player->refresh();
        $this->assertStringStartsWith('players/', $player->photo_path);
        $this->assertNotSame('players/old.jpg', $player->photo_path);
        Storage::disk('public')->assertMissing('players/old.jpg');

        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($player->photo_path));
        $this->assertSame(800, $width);
        $this->assertSame(400, $height);
        // The private original is untouched.
        Storage::disk('local')->assertExists($path);
    }

    public function test_setting_a_profile_photo_without_a_stored_photo_is_a_clean_error(): void
    {
        $registration = PlayerRegistration::factory()->create(['photo_path' => null]);

        $this->actingAs($this->admin())
            ->post(route('admin.player-registrations.profile-photo', $registration))
            ->assertSessionHas('error');

        $this->assertNull($registration->player->fresh()->photo_path);
    }

    public function test_a_queue_problem_never_undoes_the_import(): void
    {
        Queue::shouldReceive('push', 'later', 'pushOn', 'bulk', 'connection', 'size')->andThrow(new \RuntimeException('queue down'));
        Http::fake();

        $this->import($this->sheet())->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(1, PlayerRegistration::count());
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function links(): array
    {
        return [
            'google form upload' => ['https://drive.google.com/open?id=1AbC_dEf-9', '1AbC_dEf-9'],
            'file view link' => ['https://drive.google.com/file/d/1AbC_dEf-9/view?usp=sharing', '1AbC_dEf-9'],
            'uc link' => ['https://drive.google.com/uc?export=download&id=1AbC_dEf-9', '1AbC_dEf-9'],
            'a folder' => ['https://drive.google.com/drive/folders/', null],
            'an id with odd characters' => ['https://drive.google.com/open?id=abc/../../x', null],
            'not a google host' => ['https://evil.example/open?id=1AbC_dEf-9', null],
            'an array id' => ['https://drive.google.com/open?id[]=1AbC_dEf-9', null],
        ];
    }

    #[DataProvider('links')]
    public function test_the_file_id_is_read_from_the_link_shapes_drive_uses(string $url, ?string $id): void
    {
        $this->assertSame($id, DriveLink::fileId($url));
    }
}
