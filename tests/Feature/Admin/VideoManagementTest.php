<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Models\Video;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin CRUD for homepage videos. Proves authorization (VideoPolicy),
 * upload validation (type + configured max size), and that file
 * store/replace/delete through VideoService keeps the public disk in
 * sync with the videos table.
 */
class VideoManagementTest extends TestCase
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

    private function fakeVideo(string $name = 'clip.mp4', int $kilobytes = 1000): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'video/mp4');
    }

    /**
     * A Video row whose video_path/thumbnail_path point at real files on
     * the fake disk, so replace/delete cleanup can be asserted.
     */
    private function storedVideo(array $attributes = [], bool $withThumbnail = false): Video
    {
        $videoPath = $this->fakeVideo('old.mp4')->store('videos', 'public');
        $thumbnailPath = $withThumbnail
            ? UploadedFile::fake()->create('old.jpg', 100, 'image/jpeg')->store('videos/thumbnails', 'public')
            : null;

        return Video::factory()->create([
            'video_path' => $videoPath,
            'thumbnail_path' => $thumbnailPath,
            ...$attributes,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return [
            'title' => 'Final Over Highlights',
            'description' => 'Last over of the 2026 final.',
            'status' => 'active',
            'priority' => 10,
            ...$overrides,
        ];
    }

    // ----- Authorization -----

    public function test_non_admin_cannot_access_any_video_management_route(): void
    {
        $scorer = $this->scorer();
        $video = $this->storedVideo();

        $this->actingAs($scorer)->get(route('admin.videos.index'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.videos.create'))->assertForbidden();
        $this->actingAs($scorer)->post(route('admin.videos.store'), $this->validPayload(['video' => $this->fakeVideo()]))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.videos.edit', $video))->assertForbidden();
        $this->actingAs($scorer)->put(route('admin.videos.update', $video), $this->validPayload())->assertForbidden();
        $this->actingAs($scorer)->delete(route('admin.videos.destroy', $video))->assertForbidden();

        $this->assertDatabaseCount('videos', 1);
        Storage::disk('public')->assertExists($video->video_path);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.videos.index'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_the_video_list(): void
    {
        Video::factory()->create(['title' => 'Opening Ceremony']);
        Video::factory()->inactive()->create(['title' => 'Behind The Scenes']);

        $this->actingAs($this->admin())
            ->get(route('admin.videos.index'))
            ->assertOk()
            ->assertSee('Opening Ceremony')
            ->assertSee('Behind The Scenes');

        $this->actingAs($this->admin())->get(route('admin.videos.create'))->assertOk();

        $stored = $this->storedVideo([], withThumbnail: true);
        $this->actingAs($this->admin())
            ->get(route('admin.videos.edit', $stored))
            ->assertOk()
            ->assertSee(basename($stored->video_path));
    }

    // ----- Create -----

    public function test_admin_can_upload_a_valid_video(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.videos.store'), $this->validPayload([
            'video' => $this->fakeVideo(),
            'thumbnail' => UploadedFile::fake()->create('thumb.jpg', 100, 'image/jpeg'),
        ]));

        $response->assertRedirect(route('admin.videos.index'));
        $response->assertSessionHasNoErrors();

        $video = Video::sole();
        $this->assertSame('Final Over Highlights', $video->title);
        $this->assertSame('active', $video->status);
        $this->assertSame(10, $video->priority);
        $this->assertNotNull($video->video_path);
        $this->assertNotNull($video->thumbnail_path);
        Storage::disk('public')->assertExists($video->video_path);
        Storage::disk('public')->assertExists($video->thumbnail_path);
    }

    public function test_non_video_file_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.videos.store'), $this->validPayload([
                'video' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('video');

        $this->assertDatabaseCount('videos', 0);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_video_larger_than_configured_max_is_rejected_with_mb_message(): void
    {
        $maxMb = (int) config('videos.max_upload_mb');

        $this->actingAs($this->admin())
            ->post(route('admin.videos.store'), $this->validPayload([
                'video' => $this->fakeVideo('huge.mp4', $maxMb * 1024 + 1),
            ]))
            ->assertSessionHasErrors([
                'video' => "The video must not be larger than {$maxMb} MB.",
            ]);

        $this->assertDatabaseCount('videos', 0);
    }

    // ----- Update -----

    public function test_editing_metadata_without_reupload_keeps_the_existing_video(): void
    {
        $video = $this->storedVideo(['title' => 'Old title', 'priority' => 100]);
        $originalPath = $video->video_path;

        $this->actingAs($this->admin())
            ->put(route('admin.videos.update', $video), $this->validPayload([
                'title' => 'New title',
                'priority' => 5,
            ]))
            ->assertRedirect(route('admin.videos.index'))
            ->assertSessionHasNoErrors();

        $video->refresh();
        $this->assertSame('New title', $video->title);
        $this->assertSame(5, $video->priority);
        $this->assertSame($originalPath, $video->video_path);
        Storage::disk('public')->assertExists($originalPath);
    }

    public function test_replacing_the_video_stores_the_new_file_and_deletes_the_old_one(): void
    {
        $video = $this->storedVideo([], withThumbnail: true);
        $oldVideoPath = $video->video_path;
        $oldThumbnailPath = $video->thumbnail_path;

        $this->actingAs($this->admin())
            ->put(route('admin.videos.update', $video), $this->validPayload([
                'video' => $this->fakeVideo('new.mp4'),
            ]))
            ->assertSessionHasNoErrors();

        $video->refresh();
        $this->assertNotSame($oldVideoPath, $video->video_path);
        Storage::disk('public')->assertExists($video->video_path);
        Storage::disk('public')->assertMissing($oldVideoPath);

        // Thumbnail was not re-uploaded, so it must be untouched.
        $this->assertSame($oldThumbnailPath, $video->thumbnail_path);
        Storage::disk('public')->assertExists($oldThumbnailPath);
    }

    public function test_status_can_be_toggled_between_active_and_inactive(): void
    {
        $admin = $this->admin();
        $video = $this->storedVideo(['status' => 'active']);

        $this->actingAs($admin)
            ->put(route('admin.videos.update', $video), $this->validPayload(['status' => 'inactive']))
            ->assertSessionHasNoErrors();
        $this->assertSame('inactive', $video->refresh()->status);

        $this->actingAs($admin)
            ->put(route('admin.videos.update', $video), $this->validPayload(['status' => 'active']))
            ->assertSessionHasNoErrors();
        $this->assertSame('active', $video->refresh()->status);

        $this->actingAs($admin)
            ->put(route('admin.videos.update', $video), $this->validPayload(['status' => 'archived']))
            ->assertSessionHasErrors('status');
        $this->assertSame('active', $video->refresh()->status);
    }

    // ----- Delete -----

    public function test_deleting_a_video_removes_the_row_and_its_stored_files(): void
    {
        $video = $this->storedVideo([], withThumbnail: true);
        $videoPath = $video->video_path;
        $thumbnailPath = $video->thumbnail_path;

        $this->actingAs($this->admin())
            ->delete(route('admin.videos.destroy', $video))
            ->assertRedirect(route('admin.videos.index'));

        $this->assertModelMissing($video);
        Storage::disk('public')->assertMissing($videoPath);
        Storage::disk('public')->assertMissing($thumbnailPath);
    }
}
