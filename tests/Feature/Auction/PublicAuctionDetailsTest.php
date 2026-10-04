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
use Tests\TestCase;

/**
 * The detail on the public auction page: the headline numbers, the lists of
 * sold / upcoming / on-hold / unsold players, how many bids each sale took
 * and — opened one sale at a time — how the bidding went. Everything that
 * is a bid follows the "show live bids" switch.
 */
class PublicAuctionDetailsTest extends TestCase
{
    use RefreshDatabase;

    private AuctionService $service;

    private Edition $edition;

    private EditionTeam $alpha;

    private EditionTeam $beta;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auction.public_cache_seconds' => 0]);

        $this->service = app(AuctionService::class);
        $this->edition = Edition::factory()->create(['status' => 'active', 'name' => 'RPPL Test Season', 'year' => 2026]);
        $this->alpha = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
        $this->beta = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
    }

    private function paid(string $name, ?string $role = null): PlayerRegistration
    {
        return PlayerRegistration::factory()->create([
            'edition_id' => $this->edition->id,
            'player_id' => Player::factory()->create(['name' => $name, 'primary_role' => $role])->id,
            'payment_status' => 'paid',
        ]);
    }

    private function lotOf(Auction $auction, string $name): AuctionLot
    {
        return $auction->lots()->whereHas('playerRegistration.player', fn ($query) => $query->where('name', $name))->firstOrFail();
    }

    /**
     * Calls the named player, makes the bids ([team, amount] pairs, in order)
     * and sells them to whoever leads.
     *
     * @param  list<array{0: EditionTeam, 1: int}>  $bids
     */
    private function sellAfter(Auction $auction, string $name, array $bids): AuctionLot
    {
        $lot = $this->service->callLot($auction, $this->lotOf($auction, $name));

        foreach ($bids as [$team, $amount]) {
            $this->service->placeBid($auction, $lot, $team, $lot->fresh()->version, $amount);
        }

        $this->service->sell($auction, $lot, $lot->fresh()->version);

        return $lot->fresh();
    }

    private function state(): array
    {
        return $this->getJson(route('public.auction.data'))->assertOk()->json('state');
    }

    private function liveAuction(array $settings = []): Auction
    {
        return $this->service->start($this->service->create($this->edition, null, $settings));
    }

    // ----- The numbers and the lists ---------------------------------------------------------

    public function test_the_state_carries_the_headline_numbers_and_every_list(): void
    {
        foreach ([['Aman Sold', 'batter'], ['Bhola Sold', 'bowler'], ['Chintu Held', null], ['Dev Next', 'all_rounder'], ['Esha Next', 'wicket_keeper'], ['Farid Unsold', null]] as [$name, $role]) {
            $this->paid($name, $role);
        }
        $auction = $this->liveAuction();

        $this->sellAfter($auction, 'Aman Sold', [[$this->alpha, 4000]]);
        $this->sellAfter($auction, 'Bhola Sold', [[$this->alpha, 500], [$this->beta, 1000], [$this->alpha, 8000]]);
        $held = $this->service->callLot($auction, $this->lotOf($auction, 'Chintu Held'));
        $this->service->hold($auction, $held, $held->fresh()->version);
        $this->lotOf($auction, 'Farid Unsold')->update(['status' => AuctionLot::UNSOLD]);

        $state = $this->state();

        $this->assertSame(['total' => 6, 'waiting' => 2, 'hold' => 1, 'sold' => 2, 'unsold' => 1], $state['counts']);
        $this->assertSame(
            ['points_spent' => 12000, 'average' => 6000, 'highest' => ['name' => 'Bhola Sold', 'team' => $this->alpha->team->name, 'amount' => 8000]],
            $state['stats'],
        );
        $this->assertSame([['name' => 'Dev Next', 'role' => 'All-rounder'], ['name' => 'Esha Next', 'role' => 'Wicket Keeper']], $state['upcoming']);
        $this->assertSame(['Chintu Held'], $state['hold']);
        $this->assertSame(['Farid Unsold'], $state['unsold']);

        // Newest sale first, each with what it went for and how many bids it took.
        $this->assertSame(['Bhola Sold', 'Aman Sold'], array_column($state['sales'], 'name'));
        $this->assertSame([8000, 4000], array_column($state['sales'], 'amount'));
        $this->assertSame([3, 1], array_column($state['sales'], 'bids'));
        $this->assertSame('Bowler', $state['sales'][0]['role']);
    }

    public function test_every_sale_is_listed_not_only_the_latest_few(): void
    {
        foreach (range(1, 16) as $i) {
            $this->paid('Player '.$i);
        }
        $auction = $this->liveAuction();

        foreach (range(1, 16) as $i) {
            $this->sellAfter($auction, 'Player '.$i, [[$i % 2 ? $this->alpha : $this->beta, 500]]);
        }

        $state = $this->state();
        $this->assertCount(16, $state['sales']);
        $this->assertSame(16, $state['counts']['sold']);
    }

    public function test_an_empty_auction_has_zero_numbers_and_empty_lists(): void
    {
        $this->paid('Only One');
        $this->liveAuction();

        $state = $this->state();
        $this->assertSame(['points_spent' => 0, 'average' => 0, 'highest' => null], $state['stats']);
        $this->assertSame([], $state['sales']);
        $this->assertSame([], $state['hold']);
        $this->assertSame([], $state['unsold']);
        $this->assertSame([['name' => 'Only One', 'role' => null]], $state['upcoming']);
    }

    public function test_the_upcoming_list_is_only_names_and_roles(): void
    {
        $this->paid('Private Person', 'batter');
        $this->liveAuction();

        $this->assertSame([['name' => 'Private Person', 'role' => 'Batter']], $this->state()['upcoming']);
    }

    // ----- How the bidding went --------------------------------------------------------------

    public function test_a_sale_shows_its_standing_bids_and_not_the_ones_that_were_undone(): void
    {
        $this->paid('Contested');
        $auction = $this->liveAuction();
        $lot = $this->service->callLot($auction, $this->lotOf($auction, 'Contested'));
        $this->service->placeBid($auction, $lot, $this->alpha, $lot->fresh()->version, 500);
        $this->service->placeBid($auction, $lot, $this->beta, $lot->fresh()->version, 1500);
        $this->service->placeBid($auction, $lot, $this->alpha, $lot->fresh()->version, 9000);
        $this->service->undoBid($auction, $lot, $lot->fresh()->version);
        $this->service->sell($auction, $lot, $lot->fresh()->version);

        $state = $this->state();
        $this->assertSame(1500, $state['sales'][0]['amount']);
        $this->assertSame(2, $state['sales'][0]['bids']);

        $this->getJson(route('public.auction.sale', $lot))
            ->assertOk()
            ->assertJsonPath('bids', [
                ['team' => $this->alpha->team->name, 'amount' => 500],
                ['team' => $this->beta->team->name, 'amount' => 1500],
            ]);
    }

    public function test_with_live_bids_off_no_bid_count_or_history_is_given_but_the_result_still_is(): void
    {
        $this->paid('Quiet Sale');
        $auction = $this->liveAuction(['show_live_bids' => false]);
        $lot = $this->sellAfter($auction, 'Quiet Sale', [[$this->alpha, 500], [$this->beta, 7000]]);

        $state = $this->state();
        $this->assertSame(7000, $state['sales'][0]['amount']);
        $this->assertNull($state['sales'][0]['bids']);

        $this->getJson(route('public.auction.sale', $lot))->assertNotFound();
    }

    public function test_the_history_is_only_for_players_who_are_sold_in_the_auction_on_show(): void
    {
        $this->paid('On The Block');
        $this->paid('Waiting');
        $auction = $this->liveAuction();
        $onTheBlock = $this->service->callLot($auction, $this->lotOf($auction, 'On The Block'));
        $this->service->placeBid($auction, $onTheBlock, $this->alpha, $onTheBlock->fresh()->version, 500);

        // Not sold yet, so its bids stay on the live view only.
        $this->getJson(route('public.auction.sale', $onTheBlock))->assertNotFound();
        $this->getJson(route('public.auction.sale', $this->lotOf($auction, 'Waiting')))->assertNotFound();

        // A sold player of some other auction is not reachable here.
        $otherEdition = Edition::factory()->create(['status' => 'draft', 'year' => 2025]);
        $other = Auction::factory()->create(['edition_id' => $otherEdition->id, 'status' => Auction::STATUS_COMPLETED]);
        $foreign = AuctionLot::factory()->create(['auction_id' => $other->id, 'status' => AuctionLot::SOLD]);
        $this->getJson(route('public.auction.sale', $foreign))->assertNotFound();

        $this->getJson('/auction/sales/not-a-number')->assertNotFound();
    }

    public function test_nothing_can_be_looked_up_when_no_auction_is_public(): void
    {
        $this->paid('Draft Only');
        $auction = $this->service->create($this->edition);

        $this->getJson(route('public.auction.sale', $this->lotOf($auction, 'Draft Only')))->assertNotFound();
    }

    // ----- The page ------------------------------------------------------------------------------

    public function test_the_page_hands_the_script_its_words_and_the_address_for_the_history(): void
    {
        $this->paid('Anyone');
        $this->liveAuction();

        $this->get(route('public.auction.show'))
            ->assertOk()
            ->assertSee('How the bidding went')
            ->assertSee('Highest sale')
            ->assertSee(str_replace('/', '\/', route('public.auction.sale', ['lot' => '__LOT__'])), false);

        $this->withCookie('rppl_locale', 'hi')->get(route('public.auction.show'))
            ->assertOk()
            ->assertSee('बोली कैसे चली')
            ->assertSee('सबसे महँगी बिक्री');
    }
}
