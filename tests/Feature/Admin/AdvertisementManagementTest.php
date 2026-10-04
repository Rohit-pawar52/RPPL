<?php

namespace Tests\Feature\Admin;

use App\Models\Advertisement;
use App\Models\Role;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin side of the sponsor ads: who may manage them, what files are
 * accepted, the single Main sponsor slot, and that stored files follow the
 * rows (replaced / removed together with them).
 */
class AdvertisementManagementTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Sharma Tractors',
            'tier' => 'normal',
            'media_type' => 'image',
            'media' => UploadedFile::fake()->create('banner.jpg', 200, 'image/jpeg'),
            'status' => 'active',
            'weight' => 2,
            ...$overrides,
        ];
    }

    private function storedAd(array $attributes = []): Advertisement
    {
        $path = UploadedFile::fake()->create('old.jpg', 100, 'image/jpeg')->store('ads', 'public');

        return Advertisement::factory()->create(['media_path' => $path, ...$attributes]);
    }

    public function test_only_admins_can_manage_advertisements(): void
    {
        $scorer = User::factory()->create(['role_id' => $this->scorerRole->id]);
        $ad = Advertisement::factory()->create();

        $this->actingAs($scorer)->get(route('admin.advertisements.index'))->assertForbidden();
        $this->actingAs($scorer)->post(route('admin.advertisements.store'), $this->payload())->assertForbidden();
        $this->actingAs($scorer)->patch(route('admin.advertisements.toggle-status', $ad))->assertForbidden();
        $this->actingAs($scorer)->delete(route('admin.advertisements.destroy', $ad))->assertForbidden();

        $this->assertSame('active', $ad->fresh()->status);

        $this->actingAs($this->admin())->get(route('admin.advertisements.index'))->assertOk();
    }

    public function test_the_form_tells_the_admin_the_picture_size_for_every_spot(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.advertisements.create'))
            ->assertOk()
            ->assertSee('Picture size for each spot')
            ->assertSeeInOrder(['Main sponsor — top banner', '1600 × 200 px', '8 : 1'])
            ->assertSeeInOrder(['Auction sponsor — pop-up', '1600 × 360 px', '4.4 : 1'])
            ->assertSeeInOrder(['Normal sponsor — banner', '1600 × 200 px', '8 : 1'])
            ->assertSeeInOrder(['Normal sponsor — card', '1040 × 400 px', '2.6 : 1'])
            ->assertSeeInOrder(['Mini sponsor — logo', '400 × 150 px', '8 : 3'])
            ->assertSee('Normal sponsor spot');

        // The list repeats the guide and says which spot each ad is for.
        Advertisement::factory()->card()->create(['title' => 'Tile']);
        $this->actingAs($admin)->get(route('admin.advertisements.index'))
            ->assertOk()
            ->assertSee('Picture size for each spot')
            ->assertSee('Normal sponsor — card');
    }

    public function test_only_a_normal_sponsor_keeps_a_spot_choice_and_it_defaults_to_the_banner(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.advertisements.store'), $this->payload(['title' => 'A card', 'format' => 'card']))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.advertisements.store'), $this->payload(['title' => 'No choice']))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.advertisements.store'), $this->payload(['title' => 'Main', 'tier' => 'main', 'format' => 'card']))->assertSessionHasNoErrors();

        $this->assertSame('card', Advertisement::firstWhere('title', 'A card')->format);
        $this->assertSame('banner', Advertisement::firstWhere('title', 'No choice')->format);
        $this->assertNull(Advertisement::firstWhere('title', 'Main')->format);

        $this->actingAs($admin)->post(route('admin.advertisements.store'), $this->payload(['format' => 'poster']))->assertSessionHasErrors('format');

        // Moving a Normal ad to another level clears its spot.
        $card = Advertisement::firstWhere('title', 'A card');
        $this->actingAs($admin)->put(route('admin.advertisements.update', $card), $this->payload(['title' => 'A card', 'tier' => 'mini', 'media' => null, 'format' => 'card']))->assertSessionHasNoErrors();
        $this->assertNull($card->fresh()->format);
    }

    public function test_an_image_ad_is_stored_with_its_file(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.advertisements.store'), $this->payload(['starts_on' => '2026-10-10', 'ends_on' => '2026-10-20']))
            ->assertRedirect(route('admin.advertisements.index'))
            ->assertSessionHasNoErrors();

        $ad = Advertisement::firstWhere('title', 'Sharma Tractors');
        $this->assertSame('normal', $ad->tier);
        $this->assertSame('2026-10-10', $ad->starts_on->format('Y-m-d'));
        $this->assertSame('2026-10-20', $ad->ends_on->format('Y-m-d'));
        Storage::disk('public')->assertExists($ad->media_path);
        $this->assertStringStartsWith('ads/', $ad->media_path);
    }

    public function test_a_video_ad_keeps_its_preview_image_and_an_image_ad_drops_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.advertisements.store'), $this->payload([
            'title' => 'Clip',
            'media_type' => 'video',
            'media' => UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
            'poster' => UploadedFile::fake()->create('poster.jpg', 100, 'image/jpeg'),
        ]))->assertSessionHasNoErrors();

        $video = Advertisement::firstWhere('title', 'Clip');
        $this->assertTrue($video->isVideo());
        Storage::disk('public')->assertExists($video->media_path);
        Storage::disk('public')->assertExists($video->poster_path);

        // Replacing it with an image removes the video and its preview.
        $oldVideo = $video->media_path;
        $oldPoster = $video->poster_path;
        $this->actingAs($admin)->put(route('admin.advertisements.update', $video), $this->payload([
            'title' => 'Clip',
        ]))->assertSessionHasNoErrors();

        $video->refresh();
        $this->assertFalse($video->isVideo());
        $this->assertNull($video->poster_path);
        Storage::disk('public')->assertMissing($oldVideo);
        Storage::disk('public')->assertMissing($oldPoster);
        Storage::disk('public')->assertExists($video->media_path);
    }

    public function test_file_type_and_size_are_checked_and_mini_sponsors_must_be_images(): void
    {
        $admin = $this->admin();

        // A video file sent as an "image" ad.
        $this->actingAs($admin)->post(route('admin.advertisements.store'), $this->payload([
            'media' => UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
        ]))->assertSessionHasErrors('media');

        // A video over the configured limit.
        config(['ads.max_video_mb' => 1]);
        $this->actingAs($admin)->post(route('admin.advertisements.store'), $this->payload([
            'media_type' => 'video',
            'media' => UploadedFile::fake()->create('big.mp4', 2000, 'video/mp4'),
        ]))->assertSessionHasErrors('media');

        // Mini logos are images only.
        $this->actingAs($admin)->post(route('admin.advertisements.store'), $this->payload([
            'tier' => 'mini',
            'media_type' => 'video',
            'media' => UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
        ]))->assertSessionHasErrors('media_type');

        $this->assertSame(0, Advertisement::count());
    }

    public function test_the_end_date_cannot_be_before_the_start_date(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.advertisements.store'), $this->payload(['starts_on' => '2026-10-20', 'ends_on' => '2026-10-10']))
            ->assertSessionHasErrors('ends_on');
    }

    public function test_editing_without_a_new_file_keeps_the_file_but_changing_type_needs_one(): void
    {
        $admin = $this->admin();
        $ad = $this->storedAd();
        $path = $ad->media_path;

        $this->actingAs($admin)->put(route('admin.advertisements.update', $ad), $this->payload([
            'title' => 'Renamed',
            'media' => null,
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $ad->fresh()->title);
        $this->assertSame($path, $ad->fresh()->media_path);
        Storage::disk('public')->assertExists($path);

        // The current file is an image, so turning it into a video needs a video.
        $this->actingAs($admin)->put(route('admin.advertisements.update', $ad), $this->payload([
            'media_type' => 'video',
            'media' => null,
        ]))->assertSessionHasErrors('media');
        $this->assertFalse($ad->fresh()->isVideo());
    }

    public function test_replacing_the_file_removes_the_old_one_and_deleting_removes_both(): void
    {
        $admin = $this->admin();
        $ad = $this->storedAd();
        $old = $ad->media_path;

        $this->actingAs($admin)->put(route('admin.advertisements.update', $ad), $this->payload())->assertSessionHasNoErrors();

        $ad->refresh();
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($ad->media_path);

        $this->actingAs($admin)->delete(route('admin.advertisements.destroy', $ad))->assertRedirect();

        $this->assertModelMissing($ad);
        Storage::disk('public')->assertMissing($ad->media_path);
    }

    public function test_the_status_toggle_flips_an_ad_on_and_off(): void
    {
        $admin = $this->admin();
        $ad = Advertisement::factory()->create();

        $this->actingAs($admin)->patch(route('admin.advertisements.toggle-status', $ad))->assertRedirect();
        $this->assertSame('inactive', $ad->fresh()->status);

        $this->actingAs($admin)->patch(route('admin.advertisements.toggle-status', $ad))->assertRedirect();
        $this->assertSame('active', $ad->fresh()->status);
    }

    public function test_there_is_only_one_main_sponsor_at_a_time(): void
    {
        $admin = $this->admin();
        Advertisement::factory()->main()->create(['title' => 'Title Sponsor']);

        // A second active Main with open dates clashes.
        $this->actingAs($admin)
            ->post(route('admin.advertisements.store'), $this->payload(['tier' => 'main']))
            ->assertSessionHasErrors(['tier' => 'There is only one Main sponsor slot, and "Title Sponsor" already holds it (Always). Deactivate it first or choose dates that do not overlap.']);
        $this->assertSame(1, Advertisement::count());

        // Saved as inactive it is fine, but switching it on is refused.
        $this->actingAs($admin)
            ->post(route('admin.advertisements.store'), $this->payload(['tier' => 'main', 'status' => 'inactive', 'title' => 'Waiting']))
            ->assertSessionHasNoErrors();
        $waiting = Advertisement::firstWhere('title', 'Waiting');

        $this->actingAs($admin)->patch(route('admin.advertisements.toggle-status', $waiting))
            ->assertSessionHas('error');
        $this->assertSame('inactive', $waiting->fresh()->status);
    }

    public function test_an_auction_sponsor_is_an_image_or_a_video_and_has_no_spot_choice(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.advertisements.store'), $this->payload(['title' => 'Auction Partner', 'tier' => 'auction', 'format' => 'card']))
            ->assertSessionHasNoErrors();
        $ad = Advertisement::firstWhere('title', 'Auction Partner');
        $this->assertSame('auction', $ad->tier);
        $this->assertNull($ad->format);
        $this->assertSame('Auction sponsor — pop-up', $ad->spotLabel());
        Storage::disk('public')->assertExists($ad->media_path);

        // A clip is fine too (it plays muted while the pop-up is open).
        $this->actingAs($admin)->post(route('admin.advertisements.store'), $this->payload([
            'title' => 'Auction Clip',
            'tier' => 'auction',
            'status' => 'inactive',
            'media_type' => 'video',
            'media' => UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
        ]))->assertSessionHasNoErrors();
        $this->assertTrue(Advertisement::firstWhere('title', 'Auction Clip')->isVideo());

        // The level is offered on the form and explained.
        $this->actingAs($admin)->get(route('admin.advertisements.create'))
            ->assertOk()
            ->assertSee('Auction sponsor')
            ->assertSee('the pop-up on the player auction page');
    }

    public function test_there_is_only_one_auction_sponsor_at_a_time_and_it_does_not_compete_with_the_main_one(): void
    {
        $admin = $this->admin();
        Advertisement::factory()->auction()->create(['title' => 'Auction Partner']);
        Advertisement::factory()->main()->create(['title' => 'Title Sponsor']);

        $this->actingAs($admin)
            ->post(route('admin.advertisements.store'), $this->payload(['tier' => 'auction']))
            ->assertSessionHasErrors(['tier' => 'There is only one Auction sponsor slot, and "Auction Partner" already holds it (Always). Deactivate it first or choose dates that do not overlap.']);

        // Saved as inactive it is fine, but switching it on is refused.
        $this->actingAs($admin)
            ->post(route('admin.advertisements.store'), $this->payload(['tier' => 'auction', 'status' => 'inactive', 'title' => 'Next Partner']))
            ->assertSessionHasNoErrors();
        $next = Advertisement::firstWhere('title', 'Next Partner');

        $this->actingAs($admin)->patch(route('admin.advertisements.toggle-status', $next))->assertSessionHas('error');
        $this->assertSame('inactive', $next->fresh()->status);

        // The Main slot is a different slot: it was free alongside the Auction one.
        $this->assertSame(1, Advertisement::where('tier', 'main')->count());
    }

    public function test_the_list_shows_main_then_auction_then_normal_then_mini(): void
    {
        Advertisement::factory()->mini()->create(['title' => 'Mini One']);
        Advertisement::factory()->create(['title' => 'Normal One']);
        Advertisement::factory()->auction()->create(['title' => 'Auction One']);
        Advertisement::factory()->main()->create(['title' => 'Main One']);

        $this->actingAs($this->admin())->get(route('admin.advertisements.index'))
            ->assertOk()
            ->assertSeeInOrder(['Main One', 'Auction One', 'Normal One', 'Mini One']);
    }

    public function test_two_main_sponsors_are_fine_when_their_dates_do_not_overlap(): void
    {
        Advertisement::factory()->main()->create(['starts_on' => '2026-10-01', 'ends_on' => '2026-10-31']);

        $this->actingAs($this->admin())
            ->post(route('admin.advertisements.store'), $this->payload([
                'tier' => 'main',
                'starts_on' => '2026-11-01',
                'ends_on' => '2026-11-30',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Advertisement::where('tier', 'main')->count());

        // Editing the existing one without changing anything never clashes with itself.
        $only = Advertisement::where('tier', 'main')->orderBy('id')->first();
        $this->actingAs($this->admin())
            ->put(route('admin.advertisements.update', $only), $this->payload([
                'tier' => 'main',
                'media' => null,
                'starts_on' => '2026-10-01',
                'ends_on' => '2026-10-31',
            ]))
            ->assertSessionHasNoErrors();
    }
}
