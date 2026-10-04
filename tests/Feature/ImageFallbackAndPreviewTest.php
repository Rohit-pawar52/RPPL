<?php

namespace Tests\Feature;

use App\Models\Contributor;
use App\Models\Edition;
use App\Models\Player;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Two promises about pictures, on the public site and in the admin panel:
 *
 *  1. Wherever a picture is shown and there is none (no path, or the file is
 *     gone from the disk) the default picture appears - default-user.jpeg for
 *     a person, default.png for anything else - never a broken image or its
 *     alt text.
 *  2. Wherever a picture is uploaded, the field is a box showing the saved
 *     picture (or the default one), that opens the file chooser when clicked
 *     and previews the chosen file - not a bare "Choose file" control.
 */
class ImageFallbackAndPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        app(SettingsService::class)->flush();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id]);
    }

    // ----- The helper -----

    public function test_a_stored_picture_gives_its_own_url_and_anything_else_the_default(): void
    {
        Storage::disk('public')->put('players/real.jpg', 'picture');

        $this->assertSame(Storage::disk('public')->url('players/real.jpg'), media_url('players/real.jpg', 'user'));

        // Missing file, empty path, no path: the default for the kind.
        foreach (['players/gone.jpg', '', '   ', null] as $nothing) {
            $this->assertSame(asset('images/default-user.jpeg'), media_url($nothing, 'user'));
            $this->assertSame(asset('images/default.png'), media_url($nothing));
        }

        $this->assertNull(Media::existingUrl('players/gone.jpg'));
        $this->assertNull(Media::existingUrl(null));
        $this->assertNotNull(Media::existingUrl('players/real.jpg'));
    }

    public function test_the_two_default_pictures_are_in_the_public_folder(): void
    {
        $this->assertFileExists(public_path('images/default.png'));
        $this->assertFileExists(public_path('images/default-user.jpeg'));
    }

    // ----- The public site -----

    public function test_every_layout_carries_the_script_that_replaces_a_picture_that_fails_to_load(): void
    {
        $admin = $this->admin();

        foreach ([
            $this->get(route('public.home')),
            $this->get(route('admin.login')),
            $this->actingAs($admin)->get(route('admin.dashboard')),
        ] as $response) {
            $response->assertOk()
                ->assertSee('name="default-image"', false)
                ->assertSee('name="default-user-image"', false)
                ->assertSee(asset('images/default-user.jpeg'), false)
                ->assertSee('fallbackApplied', false);
        }
    }

    public function test_players_and_teams_without_a_picture_show_the_default_one_and_no_broken_link(): void
    {
        Player::factory()->create(['name' => 'No Photo Player', 'photo_path' => null, 'is_active' => true]);
        Player::factory()->create(['name' => 'Lost Photo Player', 'photo_path' => 'players/lost.jpg', 'is_active' => true]);
        Team::factory()->create(['name' => 'Lost Logo Team', 'logo_path' => 'teams/lost.png', 'is_active' => true]);

        $players = $this->get(route('public.players.index'))->assertOk()->getContent();
        $this->assertSame(2, substr_count($players, 'src="'.asset('images/default-user.jpeg').'"'));
        $this->assertStringNotContainsString('players/lost.jpg', $players);

        $teams = $this->get(route('public.teams.index'))->assertOk()->getContent();
        $this->assertStringContainsString('src="'.asset('images/default.png').'"', $teams);
        $this->assertStringNotContainsString('teams/lost.png', $teams);
    }

    public function test_a_picture_that_is_there_is_shown_as_it_is(): void
    {
        Storage::disk('public')->put('players/here.jpg', 'picture');
        Player::factory()->create(['name' => 'With Photo Player', 'photo_path' => 'players/here.jpg', 'is_active' => true]);

        $html = $this->get(route('public.players.index'))->assertOk()->getContent();

        $this->assertStringContainsString(Storage::disk('public')->url('players/here.jpg'), $html);
        $this->assertStringNotContainsString('src="'.asset('images/default-user.jpeg').'"', $html);
    }

    public function test_a_logo_whose_file_is_gone_is_treated_as_no_logo(): void
    {
        app(SettingsService::class)->set('general.logo_path', 'branding/gone.png');

        $this->get(route('public.home'))
            ->assertOk()
            ->assertDontSee('branding/gone.png', false);
    }

    public function test_the_registration_qr_code_is_only_shown_when_its_file_is_there(): void
    {
        Edition::factory()->create(['status' => 'active', 'registration_open' => true, 'registration_fee' => 400]);
        app(SettingsService::class)->set('payment.upi_qr_path', 'payments/qr.png');

        // The setting points at a file that is not there: no broken QR picture.
        $this->get(route('public.player-registration.create'))->assertOk()->assertDontSee('Scan this QR code');

        Storage::disk('public')->put('payments/qr.png', 'picture');
        $this->get(route('public.player-registration.create'))->assertOk()->assertSee('Scan this QR code');
    }

    // ----- Uploading: the preview box -----

    /**
     * @return array<string, array{string}>
     */
    public static function uploadPages(): array
    {
        return [
            'players' => ['admin.players.create'],
            'teams' => ['admin.teams.create'],
            'contributors' => ['admin.contributors.create'],
            'photos' => ['admin.photos.create'],
            'rules' => ['admin.rules.create'],
            'videos' => ['admin.videos.create'],
            'news' => ['admin.news.create'],
            'advertisements' => ['admin.advertisements.create'],
        ];
    }

    #[DataProvider('uploadPages')]
    public function test_every_admin_upload_is_a_clickable_preview_box(string $route): void
    {
        $this->actingAs($this->admin())->get(route($route))
            ->assertOk()
            ->assertSee('data-image-upload', false)
            ->assertSee('data-default-src="http', false)
            // No bare file-name line or "Choose file" prompt.
            ->assertDontSee('No file chosen')
            ->assertDontSee('Upload photo (optional)');
    }

    public function test_the_settings_and_season_team_uploads_are_preview_boxes_too(): void
    {
        $admin = $this->admin();

        foreach (['general', 'payments'] as $tab) {
            $this->actingAs($admin)->get(route('admin.settings.index', ['tab' => $tab]))
                ->assertOk()
                ->assertSee('data-image-upload', false);
        }

        $this->actingAs($admin)->get(route('admin.editions.teams.index', Edition::factory()->create(['status' => 'active'])))
            ->assertOk()
            ->assertSee('data-image-upload', false);
    }

    public function test_a_person_without_a_photo_gets_the_default_person_picture_in_the_box_and_a_saved_photo_is_shown(): void
    {
        $admin = $this->admin();
        $without = Player::factory()->create(['photo_path' => null]);

        $this->actingAs($admin)->get(route('admin.players.edit', $without))
            ->assertOk()
            ->assertSee('src="'.asset('images/default-user.jpeg').'"', false)
            ->assertSee('Click the picture to add a photo');

        Storage::disk('public')->put('players/saved.jpg', 'picture');
        $with = Player::factory()->create(['photo_path' => 'players/saved.jpg']);

        $this->actingAs($admin)->get(route('admin.players.edit', $with))
            ->assertOk()
            ->assertSee('src="'.Storage::disk('public')->url('players/saved.jpg').'"', false)
            ->assertSee('Click the picture to change it');
    }

    public function test_a_saved_picture_with_a_remove_option_offers_it(): void
    {
        Storage::disk('public')->put('branding/logo.png', 'picture');
        app(SettingsService::class)->set('general.logo_path', 'branding/logo.png');

        $this->actingAs($this->admin())->get(route('admin.settings.index', ['tab' => 'general']))
            ->assertOk()
            ->assertSee('name="remove_logo"', false)
            ->assertSee(Storage::disk('public')->url('branding/logo.png'), false);
    }

    public function test_the_public_registration_form_uses_the_preview_box_for_the_photo_and_the_screenshot(): void
    {
        Edition::factory()->create(['status' => 'active', 'registration_open' => true, 'registration_fee' => 400]);

        $html = $this->get(route('public.player-registration.create'))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'data-default-src='));
        $this->assertStringContainsString('name="photo"', $html);
        $this->assertStringContainsString('name="payment_proof"', $html);
        // The photo box starts with the default person picture.
        $this->assertStringContainsString('src="'.asset('images/default-user.jpeg').'"', $html);
    }

    public function test_admin_lists_show_the_default_picture_for_a_contributor_without_a_photo(): void
    {
        Contributor::factory()->create(['name' => 'Plain Contributor', 'photo_path' => null]);

        $this->actingAs($this->admin())->get(route('admin.contributors.index'))
            ->assertOk()
            ->assertSee('images/default-user.jpeg', false);
    }
}
