<?php

namespace Tests\Feature\Auction;

use App\Models\Auction;
use App\Models\AuctionLot;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Services\Auction\AuctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The public auction page: which auction it shows, what it shows (and what it
 * must never show), the hidden-bids switch, the SOLD moment, the results, the
 * Hindi and projector versions, and the homepage / header pointers to it.
 */
class PublicAuctionTest extends TestCase
{
    use RefreshDatabase;

    private AuctionService $service;

    private Edition $edition;

    private EditionTeam $alpha;

    private EditionTeam $beta;

    protected function setUp(): void
    {
        parent::setUp();

        // Always show the latest picture in tests (the cache has its own test).
        config(['auction.public_cache_seconds' => 0]);

        $this->service = app(AuctionService::class);
        $this->edition = Edition::factory()->create(['status' => 'active', 'name' => 'RPPL Test Season', 'year' => 2026]);
        $this->alpha = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
        $this->beta = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function paid(array $player = [], array $registration = []): PlayerRegistration
    {
        return PlayerRegistration::factory()->create([
            'edition_id' => $this->edition->id,
            'player_id' => Player::factory()->create($player)->id,
            'payment_status' => 'paid',
            ...$registration,
        ]);
    }

    private function liveAuction(array $settings = []): Auction
    {
        return $this->service->start($this->service->create($this->edition, null, $settings));
    }

    /**
     * Puts one player on the block and bids on them.
     */
    private function bidOn(Auction $auction, ?int $amount = null, ?EditionTeam $team = null): AuctionLot
    {
        $lot = $this->service->callRandom($auction);
        $this->service->placeBid($auction, $lot, $team ?? $this->alpha, $lot->fresh()->version, $amount);

        return $lot->fresh();
    }

    private function state(): array
    {
        return $this->getJson(route('public.auction.data'))->assertOk()->json('state');
    }

    // ----- Which auction ---------------------------------------------------

    public function test_with_no_auction_the_page_says_so_and_a_draft_is_not_public(): void
    {
        $this->get(route('public.auction.show'))->assertOk()->assertSee('There is no auction to show right now.');
        $this->getJson(route('public.auction.data'))->assertOk()->assertJsonPath('state', null);

        $this->paid();
        $this->service->create($this->edition);

        $this->get(route('public.auction.show'))->assertOk()->assertSee('There is no auction to show right now.');
        $this->getJson(route('public.auction.data'))->assertJsonPath('state', null);
    }

    public function test_a_running_auction_is_shown_before_a_finished_one_and_a_newer_season_before_an_older_one(): void
    {
        // An old, finished auction ...
        $old = Edition::factory()->create(['status' => 'completed', 'name' => 'Old Season', 'year' => 2020]);
        Auction::factory()->create(['edition_id' => $old->id, 'status' => Auction::STATUS_COMPLETED, 'completed_at' => now()->subYear()]);

        $this->get(route('public.auction.show'))->assertOk()->assertSee('Old Season');

        // ... gives way to a running one, even for an older season than a finished newer one.
        $this->paid();
        $this->liveAuction();
        $this->assertSame('RPPL Test Season', $this->state()['auction']['edition']);
    }

    // ----- Live ---------------------------------------------------------------

    public function test_the_live_page_carries_the_state_and_its_words_for_the_script(): void
    {
        $this->paid();
        $this->liveAuction();

        $this->get(route('public.auction.show'))
            ->assertOk()
            ->assertSee('public-auction-data', false)
            ->assertSee('RPPL Test Season')
            ->assertSee(str_replace('/', '\/', route('public.auction.data')), false)
            // The words the script draws with, in this language.
            ->assertSee('Waiting for the next player', false)
            ->assertSee('Big screen');
    }

    public function test_the_player_on_the_block_and_the_standing_bid_are_public_when_bids_are_live(): void
    {
        $this->paid(['name' => 'Raja Bhoj', 'primary_role' => 'all_rounder', 'bowling_style' => 'left_arm'], ['village' => 'Chicholi']);
        $auction = $this->liveAuction();
        $this->bidOn($auction, 4000);

        $state = $this->state();

        $this->assertSame('live', $state['auction']['status']);
        $this->assertSame(['Raja Bhoj', 'All-rounder', 'Left arm', 'Chicholi'], [$state['lot']['name'], $state['lot']['role'], $state['lot']['bowling'], $state['lot']['village']]);
        $this->assertSame(4000, $state['lot']['current_bid']);
        $this->assertSame($this->alpha->team->name, $state['lot']['leading_team']);
        $this->assertSame([['team' => $this->alpha->team->name, 'amount' => 4000]], $state['lot']['bids']);
        $this->assertFalse($state['lot']['bids_hidden']);
        $this->assertNull($state['lot']['stats']);
    }

    public function test_when_live_bids_are_off_no_bid_is_in_the_data_at_all_until_the_sale(): void
    {
        $this->paid(['name' => 'Quiet Bidder']);
        $auction = $this->liveAuction(['show_live_bids' => false]);
        $lot = $this->bidOn($auction, 12500);

        $response = $this->getJson(route('public.auction.data'))->assertOk();
        $state = $response->json('state');

        $this->assertTrue($state['lot']['bids_hidden']);
        $this->assertNull($state['lot']['current_bid']);
        $this->assertNull($state['lot']['leading_team']);
        $this->assertSame([], $state['lot']['bids']);
        $this->assertSame(500, $state['lot']['base_price']);
        // Not hidden by the screen — absent from what is sent.
        $this->assertStringNotContainsString('12500', $response->getContent());

        // Once sold, the result is public.
        $this->service->sell($auction, $lot, $lot->fresh()->version);
        $after = $this->state();
        $this->assertNull($after['lot']);
        $this->assertSame([12500, 'Quiet Bidder'], [$after['sales'][0]['amount'], $after['sales'][0]['name']]);
    }

    public function test_nothing_private_or_internal_is_ever_in_the_public_data(): void
    {
        $this->paid(
            ['name' => 'Private Person', 'phone' => '9876543210', 'email' => 'secret@example.test', 'date_of_birth' => '1999-02-03'],
            ['village' => 'Chicholi', 'tehsil' => 'Betul', 'district' => 'Betul District', 'age' => 27, 'aadhaar_document_path' => 'registrations/a.jpg', 'payment_reference' => 'UTR123456'],
        );
        $auction = $this->liveAuction();
        $lot = $this->bidOn($auction, 6000);
        $this->service->placeBid($auction, $lot, $this->beta, $lot->fresh()->version, 90000);

        // On the block, and again after the sale.
        $onTheBlock = $this->getJson(route('public.auction.data'))->assertOk()->getContent();
        $this->service->sell($auction, $lot->fresh(), $lot->fresh()->version);
        $afterSale = $this->getJson(route('public.auction.data'))->assertOk()->getContent();

        foreach ([$onTheBlock, $afterSale] as $json) {
            foreach (['9876543210', 'secret@example.test', '1999-02-03', 'Betul', 'registrations/a.jpg', 'UTR123456', 'registration_id', 'override', 'idempotency', '"age"'] as $private) {
                $this->assertStringNotContainsString($private, $json, $private);
            }
        }

        // The village is shown while the player is on the block.
        $this->assertStringContainsString('Chicholi', $onTheBlock);
    }

    public function test_just_sold_is_announced_for_a_short_while_and_the_teams_show_the_new_numbers(): void
    {
        $this->paid(['name' => 'Fresh Sale']);
        $this->paid(['name' => 'Next Up']);
        $auction = $this->liveAuction();
        $lot = $this->bidOn($auction, 8000);
        $this->service->sell($auction, $lot, $lot->fresh()->version);

        $state = $this->state();
        $this->assertSame([$lot->playerRegistration->player->name, 8000, $this->alpha->team->name], [$state['last_sale']['name'], $state['last_sale']['amount'], $state['last_sale']['team']]);

        $alpha = collect($state['teams'])->firstWhere('name', $this->alpha->team->name);
        $this->assertSame([8000, 592000, 1], [$alpha['spent'], $alpha['left'], $alpha['count']]);
        $this->assertSame(8000, $alpha['players'][0]['amount']);
        $this->assertSame(1, $state['counts']['waiting']);

        // A minute later it is no longer announced, but stays in the sales list.
        Carbon::setTestNow(now()->addMinute());
        $later = $this->state();
        $this->assertNull($later['last_sale']);
        $this->assertCount(1, $later['sales']);
    }

    public function test_a_paused_auction_is_shown_as_paused(): void
    {
        $this->paid();
        $auction = $this->liveAuction();
        $this->service->pause($auction);

        $this->assertSame('paused', $this->state()['auction']['status']);
        $this->get(route('public.auction.show'))->assertOk()->assertSee('The auction is paused for a moment', false);
    }

    public function test_players_on_hold_are_listed_by_name(): void
    {
        $this->paid(['name' => 'Held Back']);
        $auction = $this->liveAuction();
        $lot = $this->service->callRandom($auction);
        $this->service->hold($auction, $lot, $lot->fresh()->version);

        $this->assertSame(['Held Back'], $this->state()['hold']);
    }

    // ----- Results ---------------------------------------------------------------

    public function test_once_completed_the_page_is_the_results_with_points_and_the_unsold(): void
    {
        $this->paid(['name' => 'Star Buy', 'primary_role' => 'batter']);
        $this->paid(['name' => 'Cheap Buy']);
        $this->paid(['name' => 'Left Over']);
        $auction = $this->liveAuction(['min_squad' => 1, 'max_squad' => 5]);

        // Sell two named players; the third is left over.
        foreach ([['Star Buy', 60000, $this->alpha], ['Cheap Buy', 1500, $this->beta]] as [$name, $amount, $team]) {
            $lot = $auction->lots()->whereHas('playerRegistration.player', fn ($query) => $query->where('name', $name))->first();
            $this->service->callLot($auction, $lot);
            $this->service->placeBid($auction, $lot, $team, $lot->fresh()->version, $amount);
            $this->service->sell($auction, $lot->fresh(), $lot->fresh()->version);
        }
        $this->service->complete($auction);

        $response = $this->get(route('public.auction.show'))->assertOk();
        $response->assertSee('Auction results')
            ->assertSee('Players sold')
            ->assertSee('61,500')           // points spent, lakh grouping
            ->assertSee('60,000')           // the most expensive
            ->assertSee('Left Over')        // unsold
            ->assertDontSee('₹')
            ->assertDontSee('public-auction-data', false);
        // Every team's squad is listed with prices.
        $response->assertSee($this->alpha->team->name)->assertSee($this->beta->team->name);

        $state = $this->state();
        $this->assertSame(2, $state['results']['sold_count']);
        $this->assertSame(['Left Over'], $state['results']['unsold']);
    }

    // ----- Languages and screens -------------------------------------------------

    public function test_the_page_and_its_words_follow_the_visitors_language(): void
    {
        $this->paid();
        $this->liveAuction();

        $this->withCookie('rppl_locale', 'hi')->get(route('public.auction.show'))
            ->assertOk()
            ->assertSee('अगले खिलाड़ी का इंतज़ार', false)
            ->assertSee('बड़ी स्क्रीन')
            ->assertDontSee('Waiting for the next player', false);
    }

    public function test_the_big_screen_is_a_bare_dark_layout(): void
    {
        $this->paid();
        $this->liveAuction();

        $big = $this->get(route('public.auction.show', ['display' => 'big']))->assertOk();
        $big->assertSee('data-big="1"', false)->assertSee('bg-slate-950', false);
        $big->assertDontSee(route('public.matches.index'), false); // no site header

        $normal = $this->get(route('public.auction.show'))->assertOk();
        $normal->assertSee('data-big="0"', false)->assertSee(route('public.matches.index'), false);
    }

    // ----- Pointers to it ----------------------------------------------------------

    public function test_the_homepage_card_follows_the_auction(): void
    {
        $this->paid(['name' => 'On The Block']);
        $this->get(route('public.home'))->assertDontSee('The player auction is');

        $auction = $this->liveAuction();
        $this->get(route('public.home'))
            ->assertSee('The player auction is LIVE')
            ->assertSee('Waiting for the next player')
            ->assertSee(route('public.auction.show'), false);

        $this->service->callRandom($auction);
        $this->get(route('public.home'))->assertSee('Now on the block: On The Block');

        $this->service->pause($auction);
        $this->get(route('public.home'))->assertSee('The player auction is paused');

        $this->service->complete($auction);
        $this->get(route('public.home'))->assertSee('The player auction is over');

        Carbon::setTestNow(now()->addDays(8));
        $this->get(route('public.home'))->assertDontSee('The player auction is');
    }

    public function test_the_header_links_to_the_auction_only_when_there_is_one(): void
    {
        $this->paid();
        $link = 'href="'.route('public.auction.show').'"';

        $this->get(route('public.home'))->assertDontSee($link, false);

        $auction = $this->liveAuction();
        $live = $this->get(route('public.home'))->getContent();
        $this->assertStringContainsString($link, $live);
        // A live auction is a top-level link with the live dot.
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('public.auction.show'), '#').'"[^>]*>\s*Auction\s*<span class="live-dot#', $live);

        $this->service->complete($auction);
        $this->get(route('public.home'))->assertSee($link, false);
    }

    // ----- Load ------------------------------------------------------------------------

    public function test_the_public_data_is_kept_for_a_moment_so_a_crowd_does_not_hit_the_database(): void
    {
        config(['auction.public_cache_seconds' => 30]);
        Cache::flush();

        $this->paid(['name' => 'First']);
        $this->paid(['name' => 'Second']);
        $auction = $this->liveAuction();

        $this->assertSame(2, $this->state()['counts']['waiting']);

        $this->service->callRandom($auction);
        // Still the picture from a moment ago ...
        $this->assertNull($this->state()['lot']);

        // ... until it expires.
        Cache::flush();
        $this->assertNotNull($this->state()['lot']);
    }
}
