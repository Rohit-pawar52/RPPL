<?php

namespace Tests\Feature\Auction;

use App\Models\Advertisement;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\PlayerRegistration;
use App\Services\Advertisement\AdvertisementDisplayService;
use App\Services\Auction\AuctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The Main sponsor pops up now and then on the player auction page (live
 * view, big screen and results), on a timer set in config/ads.php. It is
 * only there when a Main sponsor is live and the timings are on.
 */
class AuctionAdPopupTest extends TestCase
{
    use RefreshDatabase;

    private AuctionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auction.public_cache_seconds' => 0]);

        $this->service = app(AuctionService::class);
        $edition = Edition::factory()->create(['status' => 'active', 'year' => 2026]);
        EditionTeam::factory()->count(2)->create(['edition_id' => $edition->id]);
        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'paid']);
        $this->service->start($this->service->create($edition));
    }

    private function popup(array $query = []): string
    {
        return $this->get(route('public.auction.show', $query))->assertOk()->getContent();
    }

    public function test_the_main_sponsor_pops_up_on_the_timer_from_the_config(): void
    {
        config(['ads.popup_first_seconds' => 15, 'ads.popup_visible_seconds' => 6, 'ads.popup_interval_seconds' => 90]);
        $ad = Advertisement::factory()->main()->create(['title' => 'Premier Sponsor']);

        $html = $this->popup();

        $this->assertStringContainsString('data-ad-popup', $html);
        $this->assertStringContainsString('Premier Sponsor', $html);
        $this->assertStringContainsString(Storage::disk('public')->url($ad->media_path), $html);
        $this->assertStringContainsString('data-ad-first="15000"', $html);
        $this->assertStringContainsString('data-ad-visible="6000"', $html);
        $this->assertStringContainsString('data-ad-interval="90000"', $html);
    }

    public function test_it_is_also_on_the_big_screen_and_the_results(): void
    {
        Advertisement::factory()->main()->create(['title' => 'Premier Sponsor']);

        $this->assertStringContainsString('data-ad-popup', $this->popup(['display' => 'big']));

        $edition = Edition::first();
        $this->service->complete($edition->auction);
        $this->assertStringContainsString('data-ad-popup', $this->popup());
    }

    public function test_without_a_live_main_sponsor_there_is_no_popup(): void
    {
        Advertisement::factory()->create(['title' => 'Normal Only']);
        Advertisement::factory()->mini()->create(['title' => 'Mini Only']);
        Advertisement::factory()->main()->inactive()->create(['title' => 'Switched Off']);
        Advertisement::factory()->main()->create(['title' => 'Ended', 'ends_on' => now()->subDays(3)->toDateString()]);

        $this->assertStringNotContainsString('data-ad-popup', $this->popup());
    }

    public function test_the_popup_can_be_switched_off_with_zero_seconds(): void
    {
        Advertisement::factory()->main()->create();

        config(['ads.popup_interval_seconds' => 0]);
        $this->assertStringNotContainsString('data-ad-popup', $this->popup());

        config(['ads.popup_interval_seconds' => 120, 'ads.popup_visible_seconds' => 0]);
        $this->assertStringNotContainsString('data-ad-popup', $this->popup());
    }

    public function test_the_close_button_is_in_the_visitors_language(): void
    {
        Advertisement::factory()->main()->create();

        $this->assertStringContainsString('aria-label="Close"', $this->popup());

        $this->withCookie('rppl_locale', 'hi')->get(route('public.auction.show'))
            ->assertOk()
            ->assertSee('aria-label="बंद करें"', false);
    }

    public function test_the_popup_sponsor_is_the_main_sponsor_for_now(): void
    {
        $main = Advertisement::factory()->main()->create();
        Advertisement::factory()->create();

        $this->assertSame($main->id, app(AdvertisementDisplayService::class)->auctionPopup()?->id);
    }
}
