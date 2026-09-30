<?php

namespace Tests\Feature\Admin;

use App\Models\Contributor;
use App\Models\DataCleanupLog;
use App\Models\News;
use App\Models\NewsImage;
use App\Models\Photo;
use App\Models\Player;
use App\Models\Role;
use App\Models\Rule;
use App\Models\Team;
use App\Models\User;
use App\Models\Video;
use App\Services\DataCleanup\MediaFileCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Data Cleanup -> Media Files: removes ORPHANED uploads only (a file in a
 * known upload folder that no row references). Proves that referenced and
 * inactive content is preserved, that recent uploads and unexpected files
 * are never touched, that nothing outside the intended folders can be
 * deleted, and that the action is admin-only, confirmed and logged.
 */
class DataCleanupMediaFilesTest extends TestCase
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
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    private function scorer(): User
    {
        return User::factory()->create(['role_id' => $this->scorerRole->id]);
    }

    /**
     * A file on the fake public disk, back-dated so it is old enough to be
     * an orphan candidate (recent files are deliberately protected).
     */
    private function file(string $path, int $ageHours = 72): string
    {
        Storage::disk('public')->put($path, 'content');
        touch(Storage::disk('public')->path($path), time() - $ageHours * 3600);

        return $path;
    }

    private function cleanup(string $category)
    {
        return $this->actingAs($this->admin())->delete(route('admin.data-cleanup.media-files.destroy', $category));
    }

    // ----- Orphan detection per module -----

    public function test_orphaned_video_and_thumbnail_files_are_detected_and_referenced_ones_kept(): void
    {
        $keptVideo = $this->file('videos/kept.mp4');
        $keptThumb = $this->file('videos/thumbnails/kept.jpg');
        Video::factory()->create(['video_path' => $keptVideo, 'thumbnail_path' => $keptThumb]);

        $orphanVideo = $this->file('videos/orphan.mp4');
        $orphanThumb = $this->file('videos/thumbnails/orphan.jpg');

        $scan = app(MediaFileCleanupService::class)->scanAll();

        // videos/ and videos/thumbnails/ are separate categories; one never
        // lists the other's files.
        $this->assertSame([$orphanVideo], $scan['videos']['orphans']);
        $this->assertSame([$orphanThumb], $scan['video-thumbnails']['orphans']);
        $this->assertSame(2, $scan['videos']['scanned']);

        $this->cleanup('videos')->assertRedirect(route('admin.data-cleanup.index', ['tab' => 'media-files']));
        $this->cleanup('video-thumbnails');

        Storage::disk('public')->assertMissing($orphanVideo);
        Storage::disk('public')->assertMissing($orphanThumb);
        Storage::disk('public')->assertExists($keptVideo);
        Storage::disk('public')->assertExists($keptThumb);
    }

    public function test_orphaned_photo_news_rule_player_team_and_contributor_files_are_detected(): void
    {
        $cases = [
            'photos' => ['photos/', fn (string $p) => Photo::factory()->create(['photo_path' => $p])],
            'news-images' => ['news/', fn (string $p) => NewsImage::factory()->create(['image_path' => $p])],
            'rule-images' => ['rules/', fn (string $p) => Rule::factory()->create(['image_path' => $p])],
            'player-photos' => ['players/', fn (string $p) => Player::factory()->create(['photo_path' => $p])],
            'team-logos' => ['teams/', fn (string $p) => Team::factory()->create(['logo_path' => $p])],
            'contributor-photos' => ['contributors/', fn (string $p) => Contributor::factory()->create(['photo_path' => $p])],
        ];

        foreach ($cases as $category => [$folder, $makeRecord]) {
            $kept = $this->file($folder.'kept-'.$category.'.jpg');
            $orphan = $this->file($folder.'orphan-'.$category.'.jpg');
            $makeRecord($kept);

            $scan = app(MediaFileCleanupService::class)->scan($category);
            $this->assertSame([$orphan], $scan['orphans'], $category);

            $this->cleanup($category);

            Storage::disk('public')->assertMissing($orphan);
            Storage::disk('public')->assertExists($kept);
        }
    }

    // ----- Preservation rules -----

    public function test_inactive_and_unpublished_content_keeps_its_files(): void
    {
        $video = $this->file('videos/inactive.mp4');
        Video::factory()->inactive()->create(['video_path' => $video]);

        $photo = $this->file('photos/inactive.jpg');
        Photo::factory()->inactive()->create(['photo_path' => $photo]);

        $image = $this->file('news/scheduled.jpg');
        $news = News::factory()->inactive()->scheduled()->create();
        NewsImage::create(['news_id' => $news->id, 'image_path' => $image, 'sort_order' => 0]);

        foreach (['videos', 'photos', 'news-images'] as $category) {
            $this->assertSame([], app(MediaFileCleanupService::class)->scan($category)['orphans'], $category);
            $this->cleanup($category);
        }

        Storage::disk('public')->assertExists($video);
        Storage::disk('public')->assertExists($photo);
        Storage::disk('public')->assertExists($image);
    }

    public function test_a_file_referenced_by_several_records_or_another_category_is_kept(): void
    {
        $shared = $this->file('photos/shared.jpg');
        Photo::factory()->create(['photo_path' => $shared]);
        Photo::factory()->create(['photo_path' => $shared]);

        // Referenced from a DIFFERENT module's column than its folder's own.
        $crossReferenced = $this->file('photos/cross.jpg');
        Team::factory()->create(['logo_path' => $crossReferenced]);

        $this->assertSame([], app(MediaFileCleanupService::class)->scan('photos')['orphans']);

        // Deleting one of the two referencing records keeps the file for the other.
        Photo::where('photo_path', $shared)->first()->delete();
        $this->assertSame([], app(MediaFileCleanupService::class)->scan('photos')['orphans']);

        $this->cleanup('photos');

        Storage::disk('public')->assertExists($shared);
        Storage::disk('public')->assertExists($crossReferenced);
    }

    public function test_recent_uploads_are_protected_from_being_treated_as_orphans(): void
    {
        // The upload services store the file BEFORE the DB row exists, so a
        // just-uploaded file must never be a candidate.
        $recent = $this->file('photos/just-uploaded.jpg', ageHours: 1);
        $old = $this->file('photos/old-orphan.jpg', ageHours: MediaFileCleanupService::MIN_AGE_HOURS + 1);

        $scan = app(MediaFileCleanupService::class)->scan('photos');

        $this->assertSame([$old], $scan['orphans']);
        $this->assertSame(1, $scan['recent']);

        $this->cleanup('photos');

        Storage::disk('public')->assertExists($recent);
        Storage::disk('public')->assertMissing($old);
    }

    // ----- Path safety -----

    public function test_files_outside_the_intended_folders_and_unexpected_names_are_never_deleted(): void
    {
        $outside = $this->file('branding/logo.png');
        $registrationLike = $this->file('player-registrations/aadhaar/doc.jpg');
        $unrelated = $this->file('other/orphan.jpg');
        $nested = $this->file('photos/nested/orphan.jpg');
        $oddName = $this->file('photos/bad name (1).jpg');
        $htmlName = $this->file('photos/semi;colon.jpg');

        $scan = app(MediaFileCleanupService::class)->scan('photos');

        $this->assertSame([], $scan['orphans']);
        $this->assertSame(2, $scan['skipped']);

        foreach (['photos', 'videos', 'news-images', 'team-logos'] as $category) {
            $this->cleanup($category);
        }

        foreach ([$outside, $registrationLike, $unrelated, $nested, $oddName, $htmlName] as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_malicious_database_paths_cannot_widen_what_is_deleted(): void
    {
        // Paths come from the filesystem scan, never from the database, so
        // odd reference values can only ever PROTECT a file, not add one.
        $protected = $this->file('photos/protected.jpg');
        $victim = $this->file('photos/victim.jpg');
        Photo::factory()->create(['photo_path' => '../photos/../branding/logo.png']);
        Photo::factory()->create(['photo_path' => '/photos/protected.jpg']);
        $outside = $this->file('branding/logo.png');

        $scan = app(MediaFileCleanupService::class)->scan('photos');

        $this->assertSame([$victim], $scan['orphans']);

        $this->cleanup('photos');

        Storage::disk('public')->assertExists($protected);
        Storage::disk('public')->assertExists($outside);
        Storage::disk('public')->assertMissing($victim);
    }

    public function test_a_record_pointing_at_a_missing_file_does_not_break_cleanup(): void
    {
        Photo::factory()->create(['photo_path' => 'photos/does-not-exist.jpg']);
        $orphan = $this->file('photos/orphan.jpg');

        $this->cleanup('photos')->assertSessionHas('success');

        Storage::disk('public')->assertMissing($orphan);
        $this->assertDatabaseCount('photos', 1);
    }

    // ----- Preview, deletion scope and logging -----

    public function test_the_tab_previews_counts_without_deleting_anything(): void
    {
        $orphan = $this->file('photos/orphan.jpg');
        $kept = $this->file('photos/kept.jpg');
        Photo::factory()->create(['photo_path' => $kept]);

        $this->actingAs($this->admin())
            ->get(route('admin.data-cleanup.index', ['tab' => 'media-files']))
            ->assertOk()
            ->assertSee('Media Files')
            ->assertSee('Photos')
            ->assertSee('photos/')
            ->assertSee($orphan)
            ->assertDontSee($kept)
            ->assertSee('Delete orphans')
            ->assertSee('data-confirm-action', false);

        Storage::disk('public')->assertExists($orphan);
        Storage::disk('public')->assertExists($kept);
        $this->assertSame(0, DataCleanupLog::count());
    }

    public function test_cleanup_deletes_only_the_chosen_category_and_never_any_record(): void
    {
        $photoOrphan = $this->file('photos/orphan.jpg');
        $newsOrphan = $this->file('news/orphan.jpg');
        $photo = Photo::factory()->create();
        $video = Video::factory()->inactive()->create();

        $this->cleanup('photos');

        Storage::disk('public')->assertMissing($photoOrphan);
        Storage::disk('public')->assertExists($newsOrphan);
        $this->assertModelExists($photo);
        $this->assertModelExists($video);
    }

    public function test_cleanup_is_logged_with_counts_and_no_file_paths(): void
    {
        $this->file('photos/orphan-a.jpg');
        $this->file('photos/orphan-b.jpg');
        $this->file('photos/recent.jpg', ageHours: 1);

        $admin = $this->admin();
        $this->actingAs($admin)
            ->delete(route('admin.data-cleanup.media-files.destroy', 'photos'))
            ->assertSessionHas('success', 'Photos: 2 orphaned file(s) deleted.');

        $log = DataCleanupLog::sole();
        $this->assertSame('media_files', $log->category);
        $this->assertSame('delete_orphan_media_files', $log->action);
        $this->assertSame($admin->id, $log->admin_user_id);
        $this->assertSame(2, $log->files_deleted);
        $this->assertSame('photos', $log->criteria['category']);
        $this->assertSame(3, $log->criteria['scanned']);
        $this->assertSame(2, $log->criteria['orphans']);
        $this->assertSame(2, $log->criteria['deleted']);
        $this->assertSame(0, $log->criteria['failed']);
        $this->assertSame(1, $log->criteria['protected_recent']);
        $this->assertStringNotContainsString('orphan-a', json_encode($log->criteria));
    }

    public function test_running_cleanup_twice_is_safe(): void
    {
        $orphan = $this->file('photos/orphan.jpg');

        $this->cleanup('photos');
        $this->cleanup('photos')->assertSessionHas('success', 'Photos: 0 orphaned file(s) deleted.');

        Storage::disk('public')->assertMissing($orphan);
    }

    // ----- Authorization and input -----

    public function test_only_admins_can_see_or_run_media_cleanup(): void
    {
        $orphan = $this->file('photos/orphan.jpg');
        $scorer = $this->scorer();

        $this->actingAs($scorer)->get(route('admin.data-cleanup.index', ['tab' => 'media-files']))->assertForbidden();
        $this->actingAs($scorer)->delete(route('admin.data-cleanup.media-files.destroy', 'photos'))->assertForbidden();

        auth()->logout();
        $this->get(route('admin.data-cleanup.index', ['tab' => 'media-files']))->assertRedirect(route('admin.login'));
        $this->delete(route('admin.data-cleanup.media-files.destroy', 'photos'))->assertRedirect(route('admin.login'));

        Storage::disk('public')->assertExists($orphan);
        $this->assertSame(0, DataCleanupLog::count());
    }

    public function test_an_unknown_category_is_rejected_and_cannot_name_a_directory(): void
    {
        $outside = $this->file('branding/logo.png');

        foreach (['branding', '..', 'registration-documents', 'photos%2F..'] as $category) {
            $this->cleanup($category)->assertNotFound();
        }

        Storage::disk('public')->assertExists($outside);
        $this->assertSame(0, DataCleanupLog::count());
    }

    public function test_existing_cleanup_tabs_still_render(): void
    {
        $admin = $this->admin();

        foreach (['notifications', 'registration-documents', 'system', 'media-files'] as $tab) {
            $this->actingAs($admin)
                ->get(route('admin.data-cleanup.index', ['tab' => $tab]))
                ->assertOk();
        }
    }
}
