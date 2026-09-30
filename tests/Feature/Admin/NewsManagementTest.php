<?php

namespace Tests\Feature\Admin;

use App\Models\News;
use App\Models\NewsImage;
use App\Models\Role;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin News CRUD with multiple optional images, plus the one-click
 * Active/Inactive toggle. Proves authorization (NewsPolicy), image
 * validation and the 10-image cap, slug generation/stability, the
 * display-timezone published_at handling, and that file store/remove/
 * delete through NewsService keeps the public disk in sync with
 * news_images.
 */
class NewsManagementTest extends TestCase
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

    private function fakeImage(string $name = 'pic.jpg', int $kilobytes = 200): UploadedFile
    {
        // No GD on some machines, so no ->image(); a typed fake file is
        // enough for the mime/size rules (same as the Photos tests).
        $mime = str_ends_with($name, '.png') ? 'image/png' : 'image/jpeg';

        return UploadedFile::fake()->create($name, $kilobytes, $mime);
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function fakeImages(int $count): array
    {
        return array_map(fn (int $i) => $this->fakeImage("pic{$i}.jpg"), range(1, $count));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'RPPL Season 3 Registration Starts',
            'content' => "First paragraph.\n\nSecond paragraph.",
            'status' => 'active',
            'priority' => 10,
        ], $overrides);
    }

    /**
     * A News row with real files on the fake disk and matching
     * news_images rows, in the order given.
     */
    private function storedNews(int $imageCount = 2, array $attributes = []): News
    {
        $news = News::factory()->create($attributes);

        foreach (range(0, $imageCount - 1) as $order) {
            if ($imageCount === 0) {
                break;
            }
            $path = $this->fakeImage("seed{$order}.jpg")->store('news', 'public');
            NewsImage::create(['news_id' => $news->id, 'image_path' => $path, 'sort_order' => $order]);
        }

        return $news->load('images');
    }

    // ----- Authorization -----

    public function test_admin_can_view_the_news_index(): void
    {
        News::factory()->create(['title' => 'Shown Headline']);
        News::factory()->inactive()->create(['title' => 'Hidden Headline']);

        $this->actingAs($this->admin())
            ->get(route('admin.news.index'))
            ->assertOk()
            ->assertSee('Shown Headline')
            ->assertSee('Hidden Headline');
    }

    public function test_scorer_cannot_manage_news(): void
    {
        $scorer = $this->scorer();
        $news = $this->storedNews(1);

        $this->actingAs($scorer)->get(route('admin.news.index'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.news.create'))->assertForbidden();
        $this->actingAs($scorer)->post(route('admin.news.store'), $this->validPayload())->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.news.edit', $news))->assertForbidden();
        $this->actingAs($scorer)->put(route('admin.news.update', $news), $this->validPayload())->assertForbidden();
        $this->actingAs($scorer)->patch(route('admin.news.toggle-status', $news))->assertForbidden();
        $this->actingAs($scorer)->delete(route('admin.news.destroy', $news))->assertForbidden();

        $this->assertDatabaseCount('news', 1);
        $this->assertSame('active', $news->refresh()->status);
        Storage::disk('public')->assertExists($news->images->first()->image_path);
    }

    public function test_guest_cannot_manage_news(): void
    {
        $news = News::factory()->create();

        $this->get(route('admin.news.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.news.store'), $this->validPayload())->assertRedirect(route('admin.login'));
        $this->patch(route('admin.news.toggle-status', $news))->assertRedirect(route('admin.login'));
        $this->delete(route('admin.news.destroy', $news))->assertRedirect(route('admin.login'));
        $this->assertSame('active', $news->refresh()->status);
    }

    // ----- Create -----

    public function test_admin_can_create_text_only_news(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.news.store'), $this->validPayload(['priority' => 7]))
            ->assertRedirect(route('admin.news.index'))
            ->assertSessionHasNoErrors();

        $news = News::sole();
        $this->assertSame('RPPL Season 3 Registration Starts', $news->title);
        $this->assertSame("First paragraph.\n\nSecond paragraph.", $news->content);
        $this->assertSame('active', $news->status);
        $this->assertSame(7, $news->priority);
        $this->assertSame(0, $news->images()->count());
    }

    public function test_admin_can_create_news_with_one_image(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.news.store'), $this->validPayload(['images' => [$this->fakeImage('ceremony.jpg')]]))
            ->assertSessionHasNoErrors();

        $image = News::sole()->images->sole();
        $this->assertSame(0, $image->sort_order);
        $this->assertStringStartsWith('news/', $image->image_path);
        $this->assertStringNotContainsString('ceremony', $image->image_path);
        Storage::disk('public')->assertExists($image->image_path);
    }

    public function test_admin_can_create_news_with_multiple_images_in_upload_order(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.news.store'), $this->validPayload(['images' => $this->fakeImages(3)]))
            ->assertSessionHasNoErrors();

        $images = News::sole()->images;
        $this->assertCount(3, $images);
        $this->assertSame([0, 1, 2], $images->pluck('sort_order')->all());
        $this->assertCount(3, $images->pluck('image_path')->unique());
        foreach ($images as $image) {
            Storage::disk('public')->assertExists($image->image_path);
        }
    }

    public function test_invalid_oversized_and_too_many_images_are_rejected_without_creating_anything(): void
    {
        $admin = $this->admin();

        $rejected = [
            [UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')],
            [UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
            [UploadedFile::fake()->create('shell.php', 10, 'text/x-php')],
            [$this->fakeImage('huge.jpg', 6000)],
            // One good file plus one bad file: the whole request fails.
            [$this->fakeImage('ok.jpg'), UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')],
            $this->fakeImages(News::MAX_IMAGES + 1),
        ];

        foreach ($rejected as $files) {
            $this->actingAs($admin)
                ->post(route('admin.news.store'), $this->validPayload(['images' => $files]))
                ->assertSessionHasErrors();
        }

        $this->assertDatabaseCount('news', 0);
        $this->assertDatabaseCount('news_images', 0);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_exactly_the_maximum_number_of_images_is_allowed(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.news.store'), $this->validPayload(['images' => $this->fakeImages(News::MAX_IMAGES)]))
            ->assertSessionHasNoErrors();

        $this->assertSame(News::MAX_IMAGES, NewsImage::count());
    }

    public function test_title_content_status_and_priority_are_validated(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.news.store'), $this->validPayload(['title' => '', 'content' => '', 'status' => 'archived', 'priority' => 0]))
            ->assertSessionHasErrors(['title', 'content', 'status', 'priority']);

        $this->assertDatabaseCount('news', 0);
    }

    // ----- published_at -----

    public function test_published_at_is_entered_in_display_timezone_and_stored_as_utc(): void
    {
        app(SettingsService::class)->set('system.display_timezone', 'Asia/Kolkata');

        $this->actingAs($this->admin())
            ->post(route('admin.news.store'), $this->validPayload(['published_at' => '2027-01-10T10:00']))
            ->assertSessionHasNoErrors();

        $news = News::sole();
        $this->assertSame('2027-01-10 04:30:00', $news->published_at->utc()->format('Y-m-d H:i:s'));

        $this->actingAs($this->admin())
            ->get(route('admin.news.edit', $news))
            ->assertSee('2027-01-10T10:00', false);
    }

    public function test_blank_published_at_publishes_now_on_create_and_keeps_the_time_on_edit(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00', 'UTC'));

        $this->actingAs($this->admin())
            ->post(route('admin.news.store'), $this->validPayload(['published_at' => '']))
            ->assertSessionHasNoErrors();

        $news = News::sole();
        $this->assertTrue($news->published_at->equalTo(Carbon::parse('2026-10-05 10:00:00', 'UTC')));

        $this->travelTo(Carbon::parse('2026-10-09 10:00:00', 'UTC'));

        $this->actingAs($this->admin())
            ->put(route('admin.news.update', $news), $this->validPayload(['title' => 'Edited', 'published_at' => '']))
            ->assertSessionHasNoErrors();

        $this->assertTrue($news->refresh()->published_at->equalTo(Carbon::parse('2026-10-05 10:00:00', 'UTC')));
    }

    public function test_malformed_published_at_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.news.store'), $this->validPayload(['published_at' => 'yesterday']))
            ->assertSessionHasErrors('published_at');
    }

    // ----- Slug -----

    public function test_a_unique_slug_is_generated_from_the_title_and_duplicates_get_a_suffix(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.news.store'), $this->validPayload());
        $this->actingAs($admin)->post(route('admin.news.store'), $this->validPayload());
        $this->actingAs($admin)->post(route('admin.news.store'), $this->validPayload());

        $this->assertSame(
            ['rppl-season-3-registration-starts', 'rppl-season-3-registration-starts-2', 'rppl-season-3-registration-starts-3'],
            News::orderBy('id')->pluck('slug')->all(),
        );
    }

    public function test_a_title_with_no_usable_characters_falls_back_to_a_generic_slug(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.news.store'), $this->validPayload(['title' => '!!!']));
        $this->actingAs($admin)->post(route('admin.news.store'), $this->validPayload(['title' => '???']));

        $this->assertSame(['news', 'news-2'], News::orderBy('id')->pluck('slug')->all());
    }

    public function test_the_slug_stays_the_same_when_the_title_is_edited(): void
    {
        $news = News::factory()->create(['title' => 'Original Title', 'slug' => 'original-title']);

        $this->actingAs($this->admin())
            ->put(route('admin.news.update', $news), $this->validPayload(['title' => 'A Completely New Title']))
            ->assertSessionHasNoErrors();

        $news->refresh();
        $this->assertSame('A Completely New Title', $news->title);
        $this->assertSame('original-title', $news->slug);
    }

    // ----- Update: fields and images -----

    public function test_admin_can_edit_title_content_status_and_priority_keeping_images(): void
    {
        $news = $this->storedNews(2);
        $paths = $news->images->pluck('image_path')->all();

        $this->actingAs($this->admin())
            ->put(route('admin.news.update', $news), $this->validPayload([
                'title' => 'Renamed',
                'content' => 'New body',
                'status' => 'inactive',
                'priority' => 3,
            ]))
            ->assertRedirect(route('admin.news.index'));

        $news->refresh();
        $this->assertSame('Renamed', $news->title);
        $this->assertSame('New body', $news->content);
        $this->assertSame('inactive', $news->status);
        $this->assertSame(3, $news->priority);
        $this->assertSame($paths, $news->images()->pluck('image_path')->all());
        foreach ($paths as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_images_can_be_added_during_edit_after_the_existing_ones(): void
    {
        $news = $this->storedNews(2);
        $existing = $news->images->pluck('image_path')->all();

        $this->actingAs($this->admin())
            ->put(route('admin.news.update', $news), $this->validPayload(['images' => $this->fakeImages(2)]))
            ->assertSessionHasNoErrors();

        $images = $news->images()->get();
        $this->assertCount(4, $images);
        $this->assertSame($existing, $images->take(2)->pluck('image_path')->all());
        $this->assertSame([0, 1, 2, 3], $images->pluck('sort_order')->all());
    }

    public function test_one_existing_image_can_be_removed_and_its_file_is_deleted(): void
    {
        $news = $this->storedNews(3);
        [$first, $second, $third] = $news->images->all();

        $this->actingAs($this->admin())
            ->put(route('admin.news.update', $news), $this->validPayload(['remove_images' => [$second->id]]))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($second);
        Storage::disk('public')->assertMissing($second->image_path);
        $this->assertSame([$first->id, $third->id], $news->images()->pluck('id')->all());
        Storage::disk('public')->assertExists($first->image_path);
        Storage::disk('public')->assertExists($third->image_path);
    }

    public function test_removing_an_image_whose_file_is_already_missing_still_works(): void
    {
        $news = News::factory()->create();
        $image = NewsImage::create(['news_id' => $news->id, 'image_path' => 'news/already-gone.jpg', 'sort_order' => 0]);

        $this->actingAs($this->admin())
            ->put(route('admin.news.update', $news), $this->validPayload(['remove_images' => [$image->id]]))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($image);
    }

    public function test_an_image_id_from_another_news_item_cannot_be_removed(): void
    {
        $mine = $this->storedNews(1);
        $other = $this->storedNews(1);
        $othersImage = $other->images->first();

        $this->actingAs($this->admin())
            ->put(route('admin.news.update', $mine), $this->validPayload(['remove_images' => [$othersImage->id]]))
            ->assertSessionHasNoErrors();

        $this->assertModelExists($othersImage);
        Storage::disk('public')->assertExists($othersImage->image_path);
    }

    public function test_the_image_cap_applies_to_the_total_after_an_edit(): void
    {
        $admin = $this->admin();
        $news = $this->storedNews(8);

        // 8 existing + 3 new = 11 > 10.
        $this->actingAs($admin)
            ->put(route('admin.news.update', $news), $this->validPayload(['images' => $this->fakeImages(3)]))
            ->assertSessionHasErrors('images');
        $this->assertSame(8, $news->images()->count());

        // Removing 2 first makes room: 8 - 2 + 3 = 9.
        $this->actingAs($admin)
            ->put(route('admin.news.update', $news), $this->validPayload([
                'images' => $this->fakeImages(3),
                'remove_images' => $news->images->take(2)->pluck('id')->all(),
            ]))
            ->assertSessionHasNoErrors();
        $this->assertSame(9, $news->images()->count());
    }

    public function test_an_invalid_new_image_on_edit_changes_nothing(): void
    {
        $news = $this->storedNews(1);

        $this->actingAs($this->admin())
            ->put(route('admin.news.update', $news), $this->validPayload([
                'title' => 'Should Not Save',
                'images' => [UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')],
            ]))
            ->assertSessionHasErrors();

        $this->assertNotSame('Should Not Save', $news->refresh()->title);
        $this->assertSame(1, $news->images()->count());
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    // ----- Delete -----

    public function test_deleting_news_removes_its_rows_and_all_its_files(): void
    {
        $news = $this->storedNews(3);
        $paths = $news->images->pluck('image_path')->all();
        $other = $this->storedNews(1);

        $this->actingAs($this->admin())
            ->delete(route('admin.news.destroy', $news))
            ->assertRedirect(route('admin.news.index'));

        $this->assertModelMissing($news);
        $this->assertSame(0, NewsImage::where('news_id', $news->id)->count());
        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }

        // Another post's image is untouched.
        Storage::disk('public')->assertExists($other->images->first()->image_path);
    }

    public function test_deleting_news_whose_files_are_already_missing_still_works(): void
    {
        $news = News::factory()->create();
        NewsImage::create(['news_id' => $news->id, 'image_path' => 'news/already-gone.jpg', 'sort_order' => 0]);

        $this->actingAs($this->admin())
            ->delete(route('admin.news.destroy', $news))
            ->assertRedirect(route('admin.news.index'));

        $this->assertModelMissing($news);
        $this->assertDatabaseCount('news_images', 0);
    }

    // ----- Table status toggle -----

    public function test_admin_can_deactivate_and_activate_news_from_the_table_endpoint(): void
    {
        $admin = $this->admin();
        $news = News::factory()->create(['title' => 'Keep My Title', 'priority' => 7]);

        $this->actingAs($admin)
            ->from(route('admin.news.index'))
            ->patch(route('admin.news.toggle-status', $news))
            ->assertRedirect(route('admin.news.index'))
            ->assertSessionHas('success', 'News deactivated successfully.');
        $this->assertSame('inactive', $news->refresh()->status);

        $this->actingAs($admin)
            ->patch(route('admin.news.toggle-status', $news))
            ->assertSessionHas('success', 'News activated successfully.');
        $this->assertSame('active', $news->refresh()->status);

        // Only the status changed.
        $this->assertSame('Keep My Title', $news->title);
        $this->assertSame(7, $news->priority);
    }

    public function test_toggle_ignores_a_client_supplied_status_and_always_flips(): void
    {
        $news = News::factory()->create();

        $this->actingAs($this->admin())
            ->patch(route('admin.news.toggle-status', $news), ['status' => 'active'])
            ->assertSessionHasNoErrors();
        $this->assertSame('inactive', $news->refresh()->status);

        $this->actingAs($this->admin())
            ->patch(route('admin.news.toggle-status', $news), ['status' => 'bogus'])
            ->assertSessionHasNoErrors();
        $this->assertSame('active', $news->refresh()->status);
    }

    public function test_the_table_renders_the_toggle_control_and_a_scheduled_marker(): void
    {
        $news = News::factory()->scheduled()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.news.index'))
            ->assertSee(route('admin.news.toggle-status', $news), false)
            ->assertSee('Click to deactivate')
            ->assertSee('Scheduled');
    }

    public function test_the_admin_sidebar_links_to_news(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.news.index'))
            ->assertSee(route('admin.news.index'), false);
    }
}
