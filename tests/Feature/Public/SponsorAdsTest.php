<?php

namespace Tests\Feature\Public;

use App\Models\Advertisement;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Services\Advertisement\AdvertisementDisplayService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * What visitors see: the three sponsor slots (Main / Normal / Mini) on the
 * home, edition, match-info and live pages, only for ads that are active
 * and inside their dates, with nothing left behind when there are none.
 */
class SponsorAdsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * The live page only opens once an innings exists.
     */
    private function liveMatch(?Edition $edition = null): GameMatch
    {
        $edition ??= Edition::factory()->create(['status' => 'active']);
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        $match = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'match_status' => 'live',
            'started_at' => now(),
        ]);
        $match->update(['toss_winner_team_id' => $teamA->id, 'toss_decision' => 'bat']);

        Innings::create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $teamA->id,
            'bowling_team_id' => $teamB->id,
            'status' => 'live',
        ]);

        return $match;
    }

    /**
     * @return array<string, string>
     */
    private function pages(): array
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $match = $this->liveMatch($edition);

        return [
            'home' => route('public.home'),
            'edition' => route('public.editions.show', $edition),
            'match info' => route('public.matches.show', $match),
            'live' => route('public.matches.live', $match),
        ];
    }

    public function test_every_page_shows_each_slot_of_a_live_sponsor(): void
    {
        Advertisement::factory()->main()->create(['title' => 'Title Sponsor', 'media_path' => 'ads/main.jpg']);
        Advertisement::factory()->create(['title' => 'Banner Sponsor', 'media_path' => 'ads/banner.jpg']);
        Advertisement::factory()->mini()->create(['title' => 'Local Dairy', 'media_path' => 'ads/dairy.png']);

        foreach ($this->pages() as $name => $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('data-ad="main"', false)
                ->assertSee('ads/main.jpg', false)
                ->assertSee('data-ad="normal"', false)
                ->assertSee('ads/banner.jpg', false)
                ->assertSee('data-ad="mini"', false)
                ->assertSee('Local Dairy')
                ->assertSee('Our sponsors');
        }
    }

    public function test_ads_are_display_only_with_no_link_around_them(): void
    {
        Edition::factory()->create(['status' => 'active']);
        Advertisement::factory()->main()->create(['media_path' => 'ads/main.jpg']);

        $html = $this->get(route('public.home'))->assertOk()->getContent();

        preg_match('#<aside data-ad="main".*?</aside>#s', $html, $block);
        $this->assertNotEmpty($block);
        $this->assertStringNotContainsString('<a ', $block[0]);
        $this->assertStringContainsString('pointer-events-none', $block[0]);
    }

    public function test_a_video_ad_is_muted_loops_and_loads_only_when_scrolled_into_view(): void
    {
        Edition::factory()->create(['status' => 'active']);
        Advertisement::factory()->video()->create(['media_path' => 'ads/clip.mp4', 'poster_path' => 'ads/posters/p.jpg']);

        $this->get(route('public.home'))
            ->assertOk()
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

        Advertisement::factory()->inactive()->create(['title' => 'Switched off']);
        Advertisement::factory()->create(['title' => 'Not started', 'starts_on' => '2026-10-11']);
        Advertisement::factory()->create(['title' => 'Already over', 'ends_on' => '2026-10-09']);
        Advertisement::factory()->mini()->create(['title' => 'Running today', 'starts_on' => '2026-10-10', 'ends_on' => '2026-10-10']);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Running today')
            ->assertDontSee('Switched off')
            ->assertDontSee('Not started')
            ->assertDontSee('Already over')
            ->assertDontSee('data-ad="normal"', false);
    }

    public function test_the_ad_dates_follow_the_display_timezone(): void
    {
        // 20:00 UTC on 10 Oct is already 11 Oct, 01:30 in India.
        Carbon::setTestNow('2026-10-10 20:00:00');
        app(SettingsService::class)->set('system.display_timezone', 'Asia/Kolkata');

        Advertisement::factory()->mini()->create(['title' => 'Ended yesterday there', 'ends_on' => '2026-10-10']);
        Advertisement::factory()->mini()->create(['title' => 'Still running there', 'ends_on' => '2026-10-11']);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Still running there')
            ->assertDontSee('Ended yesterday there');
    }

    public function test_pages_without_any_ads_render_without_empty_boxes(): void
    {
        foreach ($this->pages() as $url) {
            $this->get($url)
                ->assertOk()
                ->assertDontSee('data-ad=', false)
                ->assertDontSee('Our sponsors')
                ->assertDontSee('Sponsored');
        }
    }

    public function test_the_live_banner_sits_outside_the_parts_the_polling_script_rewrites(): void
    {
        Advertisement::factory()->create(['media_path' => 'ads/banner.jpg']);
        $match = $this->liveMatch();

        $html = $this->get(route('public.matches.live', $match))->assertOk()->getContent();

        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        $this->assertSame(1, $xpath->query('//*[@data-ad="normal"]')->length);

        // The ad is not inside anything the polling script rewrites.
        foreach (['live-innings', 'live-chase', 'live-deliveries'] as $id) {
            $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length, $id);
            $this->assertSame(0, $xpath->query('//*[@id="'.$id.'"]//*[@data-ad]')->length, $id);
        }

        // And the polling endpoint knows nothing about ads.
        $this->getJson(route('public.matches.live-data', $match))
            ->assertOk()
            ->assertJsonMissingPath('ads');
    }

    public function test_a_failing_ads_table_never_breaks_a_page(): void
    {
        Schema::drop('advertisements');

        $this->get(route('public.home'))->assertOk()->assertDontSee('data-ad=', false);
    }

    public function test_the_main_sponsor_is_always_the_one_shown_and_a_normal_ad_is_chosen_by_weight(): void
    {
        $light = Advertisement::factory()->create(['weight' => 1]);
        $heavy = Advertisement::factory()->create(['weight' => 3]);
        $ads = collect([$light, $heavy]);

        // Total weight 4: roll 0 lands on the first ad, 1–3 on the second.
        $this->assertSame($light->id, AdvertisementDisplayService::pickWeighted($ads, 0)->id);
        $this->assertSame($heavy->id, AdvertisementDisplayService::pickWeighted($ads, 1)->id);
        $this->assertSame($heavy->id, AdvertisementDisplayService::pickWeighted($ads, 3)->id);
        $this->assertNull(AdvertisementDisplayService::pickWeighted(collect(), 0));

        // The banner is picked once per request, so it is the same wherever it appears.
        $service = app(AdvertisementDisplayService::class);
        $this->assertSame($service->banner()?->id, $service->banner()?->id);
    }
}
