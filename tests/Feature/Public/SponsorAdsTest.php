<?php

namespace Tests\Feature\Public;

use App\Models\Advertisement;
use App\Services\Advertisement\AdvertisementDisplayService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * What the sponsor slot (<x-ad-slot tier="main|normal|mini" />) renders: only
 * ads that are active and inside their dates, display-only, and nothing at
 * all when there are none. The slot is tested on its own; which public pages
 * carry it is decided in those pages' views.
 */
class SponsorAdsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function slot(string $tier): string
    {
        return (string) $this->blade('<x-ad-slot tier="'.$tier.'" />');
    }

    public function test_each_tier_renders_its_own_slot(): void
    {
        Advertisement::factory()->main()->create(['title' => 'Title Sponsor', 'media_path' => 'ads/main.jpg']);
        Advertisement::factory()->create(['title' => 'Banner Sponsor', 'media_path' => 'ads/banner.jpg']);
        Advertisement::factory()->mini()->create(['title' => 'Local Dairy', 'media_path' => 'ads/dairy.png']);

        $this->blade('<x-ad-slot tier="main" />')
            ->assertSee('data-ad="main"', false)
            ->assertSee('ads/main.jpg', false)
            ->assertSee('Sponsored')
            ->assertDontSee('ads/banner.jpg', false);

        $this->blade('<x-ad-slot tier="normal" />')
            ->assertSee('data-ad="normal"', false)
            ->assertSee('ads/banner.jpg', false);

        $this->blade('<x-ad-slot tier="mini" />')
            ->assertSee('data-ad="mini"', false)
            ->assertSee('Local Dairy')
            ->assertSee('Our sponsors');
    }

    public function test_ads_are_display_only_with_no_link_around_them(): void
    {
        Advertisement::factory()->main()->create(['media_path' => 'ads/main.jpg']);

        $html = $this->slot('main');

        $this->assertStringNotContainsString('<a ', $html);
        $this->assertStringContainsString('pointer-events-none', $html);
        // A fixed compact height, so a tall image can never stretch the page.
        $this->assertStringContainsString('height: 5.5rem', $html);
    }

    public function test_a_banner_folds_away_and_returns_on_a_timer_but_a_card_and_logos_stay(): void
    {
        config(['ads.banner_visible_seconds' => 8, 'ads.banner_hidden_seconds' => 30]);
        Advertisement::factory()->main()->create(['media_path' => 'ads/main.jpg']);
        Advertisement::factory()->card()->create(['media_path' => 'ads/card.jpg']);
        Advertisement::factory()->mini()->create(['title' => 'Local Dairy']);

        // The banner carries its show / hide times (in milliseconds).
        $this->blade('<x-ad-slot tier="main" />')
            ->assertSee('data-ad-cycle', false)
            ->assertSee('data-ad-visible="8000"', false)
            ->assertSee('data-ad-hidden="30000"', false);

        // A tile in a row and the logo strip do not fold.
        $this->blade('<x-ad-slot tier="normal" variant="card" />')
            ->assertSee('ads/card.jpg', false)
            ->assertDontSee('data-ad-visible', false);
        $this->blade('<x-ad-slot tier="mini" />')->assertDontSee('data-ad-visible', false);
    }

    public function test_banners_can_be_kept_permanently_visible_from_the_config(): void
    {
        config(['ads.banner_hidden_seconds' => 0]);
        Advertisement::factory()->main()->create();

        $this->blade('<x-ad-slot tier="main" />')
            ->assertSee('data-ad="main"', false)
            ->assertDontSee('data-ad-visible', false);
    }

    public function test_a_normal_ad_only_shows_in_the_spot_it_was_made_for(): void
    {
        Advertisement::factory()->create(['title' => 'Strip Ad', 'media_path' => 'ads/strip.jpg']);
        Advertisement::factory()->card()->create(['title' => 'Tile Ad', 'media_path' => 'ads/tile.jpg']);

        $this->blade('<x-ad-slot tier="normal" />')
            ->assertSee('ads/strip.jpg', false)
            ->assertDontSee('ads/tile.jpg', false);

        $this->blade('<x-ad-slot tier="normal" variant="card" />')
            ->assertSee('ads/tile.jpg', false)
            ->assertSee('Tile Ad')
            ->assertDontSee('ads/strip.jpg', false);
    }

    public function test_a_banner_image_is_backed_by_a_blurred_copy_so_it_always_looks_full_width(): void
    {
        Advertisement::factory()->main()->create(['media_path' => 'ads/main.jpg']);

        $html = $this->slot('main');

        $this->assertStringContainsString('data-ad-backdrop', $html);
        $this->assertStringContainsString('ads/main.jpg', $html);
        $this->assertStringContainsString('blur(', $html);
        // The picture itself is never cropped.
        $this->assertStringContainsString('object-fit: contain', $html);
    }

    public function test_a_video_ad_is_muted_loops_and_loads_only_when_scrolled_into_view(): void
    {
        Advertisement::factory()->video()->create(['media_path' => 'ads/clip.mp4', 'poster_path' => 'ads/posters/p.jpg']);

        $this->blade('<x-ad-slot tier="normal" />')
            ->assertSee('data-ad-src="', false)
            ->assertSee('ads/clip.mp4', false)
            ->assertSee('ads/posters/p.jpg', false)
            ->assertSee('muted', false)
            ->assertSee('loop', false)
            ->assertSee('IntersectionObserver', false)
            // No eager src and no player controls.
            ->assertDontSee('controls', false)
            ->assertDontSee(' src="'.url('storage/ads/clip.mp4'), false);
    }

    public function test_inactive_and_out_of_date_ads_are_not_shown(): void
    {
        Carbon::setTestNow('2026-10-10 06:00:00');

        Advertisement::factory()->inactive()->mini()->create(['title' => 'Switched off']);
        Advertisement::factory()->mini()->create(['title' => 'Not started', 'starts_on' => '2026-10-11']);
        Advertisement::factory()->mini()->create(['title' => 'Already over', 'ends_on' => '2026-10-09']);
        Advertisement::factory()->mini()->create(['title' => 'Running today', 'starts_on' => '2026-10-10', 'ends_on' => '2026-10-10']);

        $this->blade('<x-ad-slot tier="mini" />')
            ->assertSee('Running today')
            ->assertDontSee('Switched off')
            ->assertDontSee('Not started')
            ->assertDontSee('Already over');
    }

    public function test_the_ad_dates_follow_the_display_timezone(): void
    {
        // 20:00 UTC on 10 Oct is already 11 Oct, 01:30 in India.
        Carbon::setTestNow('2026-10-10 20:00:00');
        app(SettingsService::class)->set('system.display_timezone', 'Asia/Kolkata');

        Advertisement::factory()->mini()->create(['title' => 'Ended yesterday there', 'ends_on' => '2026-10-10']);
        Advertisement::factory()->mini()->create(['title' => 'Still running there', 'ends_on' => '2026-10-11']);

        $this->blade('<x-ad-slot tier="mini" />')
            ->assertSee('Still running there')
            ->assertDontSee('Ended yesterday there');
    }

    public function test_a_slot_with_no_ads_renders_nothing_at_all(): void
    {
        foreach (['main', 'normal', 'mini'] as $tier) {
            $this->assertSame('', trim($this->slot($tier)), $tier);
        }
    }

    public function test_a_failing_ads_table_renders_nothing_instead_of_an_error(): void
    {
        Schema::drop('advertisements');

        $this->assertSame('', trim($this->slot('main')));
    }

    public function test_a_normal_ad_is_chosen_by_weight_and_no_ad_repeats_on_a_page(): void
    {
        $light = Advertisement::factory()->create(['weight' => 1]);
        $heavy = Advertisement::factory()->create(['weight' => 3]);
        $ads = collect([$light, $heavy]);

        // Total weight 4: roll 0 lands on the first ad, 1–3 on the second.
        $this->assertSame($light->id, AdvertisementDisplayService::pickWeighted($ads, 0)->id);
        $this->assertSame($heavy->id, AdvertisementDisplayService::pickWeighted($ads, 1)->id);
        $this->assertSame($heavy->id, AdvertisementDisplayService::pickWeighted($ads, 3)->id);
        $this->assertNull(AdvertisementDisplayService::pickWeighted(collect(), 0));

        // Each Normal slot on a page gets a different ad; once every live
        // Normal ad is on the page, further slots stay empty.
        $service = app(AdvertisementDisplayService::class);
        $first = $service->nextNormal();
        $second = $service->nextNormal();
        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNotSame($first->id, $second->id);
        $this->assertNull($service->nextNormal());
    }
}
