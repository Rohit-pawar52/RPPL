<?php

namespace Tests\Feature\Admin;

use App\Models\Photo;
use App\Models\Role;
use App\Models\User;
use App\Models\Video;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin Photos CRUD plus the one-click Active/Inactive toggle shared by
 * the Photos and Videos tables. Proves authorization (PhotoPolicy /
 * VideoPolicy), upload validation, and that file store/replace/delete
 * through PhotoService keeps the public disk in sync with the table.
 */
class PhotoManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $scorerRole;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->scorerRole = Role::create(['name' => 'Scorer', 'slug' => 'scorer']);

        app(SettingsService::class)->flush();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    private function scorer(): User
    {
        return User::factory()->create(['role_id' => $this->scorerRole->id]);
    }

    private function fakeImage(string $name = 'match.jpg', int $kilobytes = 200): UploadedFile
    {
        // No GD on some machines, so no ->image(); a typed fake file is
        // enough for the mime/size rules (same as the Video thumbnail tests).
        $mime = str_ends_with($name, '.png') ? 'image/png' : 'image/jpeg';

        return UploadedFile::fake()->create($name, $kilobytes, $mime);
    }

    /**
     * A Photo whose photo_path points at a real file on the fake disk.
     */
    private function storedPhoto(array $attributes = []): Photo
    {
        $path = UploadedFile::fake()->create('seed.jpg', 50, 'image/jpeg')->store('photos', 'public');

        return Photo::factory()->create(array_merge(['photo_path' => $path], $attributes));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Trophy Ceremony',
            'description' => 'Captains with the trophy',
            'status' => 'active',
            'priority' => 10,
        ], $overrides);
    }

    // ----- Authorization -----

    public function test_non_admin_cannot_access_any_photo_management_route(): void
    {
        $scorer = $this->scorer();
        $photo = $this->storedPhoto();

        $this->actingAs($scorer)->get(route('admin.photos.index'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.photos.create'))->assertForbidden();
        $this->actingAs($scorer)->post(route('admin.photos.store'), $this->validPayload(['photo' => $this->fakeImage()]))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.photos.edit', $photo))->assertForbidden();
        $this->actingAs($scorer)->put(route('admin.photos.update', $photo), $this->validPayload())->assertForbidden();
        $this->actingAs($scorer)->patch(route('admin.photos.toggle-status', $photo))->assertForbidden();
        $this->actingAs($scorer)->delete(route('admin.photos.destroy', $photo))->assertForbidden();

        $this->assertDatabaseCount('photos', 1);
        $this->assertSame('active', $photo->refresh()->status);
        Storage::disk('public')->assertExists($photo->photo_path);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.photos.index'))->assertRedirect(route('admin.login'));
        $this->patch(route('admin.photos.toggle-status', Photo::factory()->create()))->assertRedirect(route('admin.login'));
    }

    // ----- List / create -----

    public function test_admin_sees_active_and_inactive_photos_in_priority_order(): void
    {
        Photo::factory()->create(['title' => 'Later Photo', 'priority' => 50]);
        Photo::factory()->inactive()->create(['title' => 'Hidden Photo', 'priority' => 5]);

        $this->actingAs($this->admin())
            ->get(route('admin.photos.index'))
            ->assertOk()
            ->assertSeeInOrder(['Hidden Photo', 'Later Photo']);
    }

    public function test_admin_can_upload_a_valid_photo(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('admin.photos.store'), $this->validPayload(['photo' => $this->fakeImage()]));

        $response->assertRedirect(route('admin.photos.index'));
        $response->assertSessionHasNoErrors();

        $photo = Photo::sole();
        $this->assertSame('Trophy Ceremony', $photo->title);
        $this->assertSame('active', $photo->status);
        $this->assertSame(10, $photo->priority);
        $this->assertStringStartsWith('photos/', $photo->photo_path);
        $this->assertStringNotContainsString('match', $photo->photo_path);
        Storage::disk('public')->assertExists($photo->photo_path);
    }

    public function test_non_image_svg_and_oversized_files_are_rejected(): void
    {
        $admin = $this->admin();

        $rejected = [
            UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->create('shell.php', 10, 'text/x-php'),
            $this->fakeImage('huge.jpg', 6000),
        ];

        foreach ($rejected as $file) {
            $this->actingAs($admin)
                ->post(route('admin.photos.store'), $this->validPayload(['photo' => $file]))
                ->assertSessionHasErrors('photo');
        }

        $this->assertDatabaseCount('photos', 0);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_title_and_priority_are_validated(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.photos.store'), $this->validPayload([
                'photo' => $this->fakeImage(),
                'title' => '',
                'priority' => 0,
                'status' => 'archived',
            ]))
            ->assertSessionHasErrors(['title', 'priority', 'status']);

        $this->assertDatabaseCount('photos', 0);
    }

    // ----- Update -----

    public function test_editing_metadata_without_reupload_keeps_the_existing_photo(): void
    {
        $photo = $this->storedPhoto();
        $path = $photo->photo_path;

        $this->actingAs($this->admin())
            ->put(route('admin.photos.update', $photo), $this->validPayload(['title' => 'Renamed', 'status' => 'inactive', 'priority' => 3]))
            ->assertRedirect(route('admin.photos.index'));

        $photo->refresh();
        $this->assertSame('Renamed', $photo->title);
        $this->assertSame('inactive', $photo->status);
        $this->assertSame(3, $photo->priority);
        $this->assertSame($path, $photo->photo_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_replacing_the_photo_stores_the_new_file_and_deletes_the_old_one(): void
    {
        $photo = $this->storedPhoto();
        $oldPath = $photo->photo_path;

        $this->actingAs($this->admin())
            ->put(route('admin.photos.update', $photo), $this->validPayload(['photo' => $this->fakeImage('new.png')]))
            ->assertSessionHasNoErrors();

        $photo->refresh();
        $this->assertNotSame($oldPath, $photo->photo_path);
        Storage::disk('public')->assertExists($photo->photo_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_an_invalid_replacement_leaves_the_existing_photo_untouched(): void
    {
        $photo = $this->storedPhoto();
        $oldPath = $photo->photo_path;

        $this->actingAs($this->admin())
            ->put(route('admin.photos.update', $photo), $this->validPayload([
                'photo' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('photo');

        $this->assertSame($oldPath, $photo->refresh()->photo_path);
        Storage::disk('public')->assertExists($oldPath);
    }

    // ----- Delete -----

    public function test_deleting_a_photo_removes_the_row_and_its_file(): void
    {
        $photo = $this->storedPhoto();
        $path = $photo->photo_path;

        $this->actingAs($this->admin())
            ->delete(route('admin.photos.destroy', $photo))
            ->assertRedirect(route('admin.photos.index'));

        $this->assertModelMissing($photo);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_deleting_a_photo_whose_file_is_already_missing_still_works(): void
    {
        $photo = Photo::factory()->create(['photo_path' => 'photos/already-gone.jpg']);

        $this->actingAs($this->admin())
            ->delete(route('admin.photos.destroy', $photo))
            ->assertRedirect(route('admin.photos.index'));

        $this->assertModelMissing($photo);
    }

    // ----- Table status toggle (Photos) -----

    public function test_admin_can_toggle_a_photo_from_the_table_endpoint(): void
    {
        $admin = $this->admin();
        $photo = Photo::factory()->create(['title' => 'Toggle Me', 'priority' => 7]);

        $this->actingAs($admin)
            ->from(route('admin.photos.index'))
            ->patch(route('admin.photos.toggle-status', $photo))
            ->assertRedirect(route('admin.photos.index'))
            ->assertSessionHas('success', 'Photo deactivated successfully.');
        $this->assertSame('inactive', $photo->refresh()->status);

        $this->actingAs($admin)
            ->patch(route('admin.photos.toggle-status', $photo))
            ->assertSessionHas('success', 'Photo activated successfully.');
        $this->assertSame('active', $photo->refresh()->status);

        // Only the status changed.
        $this->assertSame('Toggle Me', $photo->title);
        $this->assertSame(7, $photo->priority);
    }

    public function test_repeated_toggling_always_leaves_a_valid_status(): void
    {
        $admin = $this->admin();
        $photo = Photo::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->actingAs($admin)->patch(route('admin.photos.toggle-status', $photo));
            $this->assertContains($photo->refresh()->status, Photo::STATUSES);
        }

        $this->assertSame('inactive', $photo->status);
    }

    public function test_the_table_renders_a_toggle_control_and_ignores_client_supplied_status(): void
    {
        $photo = Photo::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.photos.index'))
            ->assertSee(route('admin.photos.toggle-status', $photo), false)
            ->assertSee('Click to deactivate');

        $this->actingAs($this->admin())
            ->patch(route('admin.photos.toggle-status', $photo), ['status' => 'bogus'])
            ->assertSessionHasNoErrors();
        $this->assertSame('inactive', $photo->refresh()->status);
    }

    // ----- Table status toggle (Videos) -----

    public function test_admin_can_activate_and_deactivate_a_video_from_the_table_endpoint(): void
    {
        $admin = $this->admin();
        $video = Video::factory()->create(['title' => 'Keep My Title', 'priority' => 4]);

        $this->actingAs($admin)
            ->from(route('admin.videos.index'))
            ->patch(route('admin.videos.toggle-status', $video))
            ->assertRedirect(route('admin.videos.index'))
            ->assertSessionHas('success', 'Video deactivated successfully.');
        $this->assertSame('inactive', $video->refresh()->status);

        $this->actingAs($admin)
            ->patch(route('admin.videos.toggle-status', $video))
            ->assertSessionHas('success', 'Video activated successfully.');
        $this->assertSame('active', $video->refresh()->status);
        $this->assertSame('Keep My Title', $video->title);
        $this->assertSame(4, $video->priority);
    }

    public function test_video_toggle_is_rejected_for_non_admins_and_guests(): void
    {
        $video = Video::factory()->create();

        $this->actingAs($this->scorer())->patch(route('admin.videos.toggle-status', $video))->assertForbidden();
        $this->assertSame('active', $video->refresh()->status);

        auth()->logout();
        $this->patch(route('admin.videos.toggle-status', $video))->assertRedirect(route('admin.login'));
        $this->assertSame('active', $video->refresh()->status);
    }

    public function test_video_table_shows_the_toggle_and_a_toggled_off_video_leaves_the_public_page(): void
    {
        $video = Video::factory()->create(['title' => 'Public Clip Title']);

        $this->actingAs($this->admin())
            ->get(route('admin.videos.index'))
            ->assertSee(route('admin.videos.toggle-status', $video), false);

        $this->get(route('public.videos.index'))->assertSee('Public Clip Title');

        $this->actingAs($this->admin())->patch(route('admin.videos.toggle-status', $video));

        $this->get(route('public.videos.index'))->assertDontSee('Public Clip Title');
    }

    public function test_admin_sidebar_links_to_photos(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.photos.index'))
            ->assertSee(route('admin.photos.index'), false);
    }
}
