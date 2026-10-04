<?php

namespace Tests\Feature\Auction;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionLot;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use App\Services\Auction\AuctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The auction rules: the pool, the bids, the purse and squad limits, the
 * keep-enough-for-a-minimum-squad limit and its override, hold rounds,
 * undo, reopening a sale and completing. Points are plain numbers here.
 */
class AuctionServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuctionService $service;

    private Edition $edition;

    private EditionTeam $alpha;

    private EditionTeam $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AuctionService::class);
        $this->edition = Edition::factory()->create(['status' => 'active']);
        $this->alpha = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
        $this->beta = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
    }

    private function registration(string $payment = 'paid', array $player = []): PlayerRegistration
    {
        return PlayerRegistration::factory()->create([
            'edition_id' => $this->edition->id,
            'player_id' => Player::factory()->create($player)->id,
            'payment_status' => $payment,
        ]);
    }

    /**
     * A live auction with $players paid players in the pool.
     */
    private function liveAuction(int $players = 3, array $settings = []): Auction
    {
        for ($i = 0; $i < $players; $i++) {
            $this->registration();
        }

        $auction = $this->service->create($this->edition, null, $settings);

        return $this->service->start($auction);
    }

    private function onTheBlock(Auction $auction): AuctionLot
    {
        return $this->service->callRandom($auction);
    }

    private function bid(Auction $auction, AuctionLot $lot, EditionTeam $team, ?int $amount = null, bool $override = false, ?string $key = null): AuctionBid
    {
        return $this->service->placeBid($auction, $lot, $team, $lot->fresh()->version, $amount, $key, $override);
    }

    private function fails(callable $action, string $key, ?string $contains = null): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
            if ($contains !== null) {
                $this->assertStringContainsString($contains, $e->errors()[$key][0]);
            }

            return;
        }

        $this->fail("Expected a '{$key}' rule failure.");
    }

    // ----- Setup and the pool ----------------------------------------------

    public function test_the_pool_is_paid_active_players_who_are_not_in_a_squad(): void
    {
        $paid = $this->registration();
        $this->registration('pending');
        $this->registration('failed');
        $this->registration('refunded');
        $this->registration('paid', ['is_active' => false]);
        $bought = $this->registration();
        TeamPlayer::factory()->create(['edition_team_id' => $this->alpha->id, 'player_registration_id' => $bought->id]);

        $auction = $this->service->create($this->edition);

        $this->assertSame(Auction::STATUS_DRAFT, $auction->status);
        $this->assertSame([$paid->id], $auction->lots()->pluck('player_registration_id')->all());
        $this->assertSame(['pending' => 1, 'live' => 0, 'sold' => 0, 'hold' => 0, 'unsold' => 0, 'total' => 1], $this->service->counts($auction));
        // The documented defaults.
        $this->assertSame([600000, 500, 500, 12, 15], [$auction->team_purse, $auction->min_bid, $auction->bid_step, $auction->min_squad, $auction->max_squad]);
    }

    public function test_refreshing_the_pool_adds_new_paid_players_and_drops_waiting_ones_who_are_no_longer_eligible(): void
    {
        $stays = $this->registration();
        $paysLater = $this->registration('pending');
        $auction = $this->service->create($this->edition);
        $this->assertSame(1, $auction->lots()->count());

        $paysLater->update(['payment_status' => 'paid']);
        $stays->update(['payment_status' => 'refunded']);

        $this->assertSame(['added' => 1, 'removed' => 1], $this->service->refreshPool($auction));
        $this->assertSame([$paysLater->id], $auction->lots()->pluck('player_registration_id')->all());
    }

    public function test_refreshing_never_touches_players_who_are_sold(): void
    {
        $auction = $this->liveAuction(1);
        $lot = $this->onTheBlock($auction);
        $this->bid($auction, $lot, $this->alpha);
        $this->service->sell($auction, $lot->fresh(), $lot->fresh()->version);

        $lot->playerRegistration->update(['payment_status' => 'refunded']);
        $this->service->refreshPool($auction);

        $this->assertSame(AuctionLot::SOLD, $lot->fresh()->status);
    }

    public function test_one_auction_per_season_and_none_for_a_completed_season(): void
    {
        $this->service->create($this->edition);
        $this->fails(fn () => $this->service->create($this->edition), 'auction', 'already has an auction');

        $done = Edition::factory()->create(['status' => 'completed']);
        $this->fails(fn () => $this->service->create($done), 'auction', 'completed');
    }

    public function test_settings_and_a_teams_own_purse_can_be_changed_and_the_squad_limits_must_make_sense(): void
    {
        $auction = $this->service->create($this->edition);

        $this->service->updateSettings($auction, ['team_purse' => 800000, 'bid_step' => 1000, 'min_bid' => 1000], [$this->alpha->id => 900000, $this->beta->id => null]);

        $auction->refresh();
        $this->assertSame(800000, $auction->team_purse);
        $this->assertSame(900000, $auction->purseFor($this->alpha->fresh()));
        $this->assertSame(800000, $auction->purseFor($this->beta->fresh()));

        $this->fails(fn () => $this->service->updateSettings($auction, ['min_squad' => 16]), 'min_squad');
    }

    public function test_starting_needs_teams_and_players(): void
    {
        $empty = Edition::factory()->create(['status' => 'active']);
        $auction = $this->service->create($empty);
        $this->fails(fn () => $this->service->start($auction), 'auction', 'teams');

        EditionTeam::factory()->create(['edition_id' => $empty->id]);
        $this->fails(fn () => $this->service->start($auction), 'auction', 'pool');
    }

    // ----- Bidding ----------------------------------------------------------

    public function test_the_first_bid_is_the_minimum_then_one_step_at_a_time_or_a_direct_amount_in_steps(): void
    {
        $auction = $this->liveAuction();
        $lot = $this->onTheBlock($auction);

        $this->assertSame(500, $this->bid($auction, $lot, $this->alpha)->amount);
        $this->assertSame(1000, $this->bid($auction, $lot, $this->beta)->amount);
        // A jump straight to 10,000.
        $this->assertSame(10000, $this->bid($auction, $lot, $this->alpha, 10000)->amount);
        // Above 10,000 the step is still 500.
        $this->assertSame(10500, $this->bid($auction, $lot, $this->beta)->amount);

        $lot->refresh();
        $this->assertSame(10500, $lot->current_bid);
        $this->assertSame($this->beta->id, $lot->leading_edition_team_id);
    }

    public function test_a_direct_amount_must_beat_the_standing_bid_and_be_in_steps(): void
    {
        $auction = $this->liveAuction();
        $lot = $this->onTheBlock($auction);
        $this->bid($auction, $lot, $this->alpha, 5000);

        $this->fails(fn () => $this->bid($auction, $lot, $this->beta, 5000), 'bid', 'at least');
        $this->fails(fn () => $this->bid($auction, $lot, $this->beta, 5750), 'bid', 'steps of');
        $this->fails(fn () => $this->bid($auction, $lot, $this->beta, 5200), 'bid');

        $this->assertSame(5000, $lot->fresh()->current_bid);
    }

    public function test_the_leading_team_cannot_raise_its_own_bid(): void
    {
        $auction = $this->liveAuction();
        $lot = $this->onTheBlock($auction);
        $this->bid($auction, $lot, $this->alpha);

        $this->fails(fn () => $this->bid($auction, $lot, $this->alpha), 'bid', 'already leading');
    }

    public function test_a_team_cannot_bid_more_than_it_has_left_even_with_the_override(): void
    {
        $auction = $this->liveAuction(3, ['team_purse' => 10000]);
        $lot = $this->onTheBlock($auction);

        $this->fails(fn () => $this->bid($auction, $lot, $this->alpha, 10500, true), 'bid', 'only 10,000 pts left');
    }

    public function test_a_team_must_keep_enough_to_reach_the_minimum_squad_unless_overridden(): void
    {
        // Fresh team: 11 more players needed after this one -> keep 11 x 500.
        $auction = $this->liveAuction(3, ['team_purse' => 100000]);
        $lot = $this->onTheBlock($auction);
        $limit = 100000 - 11 * 500;

        $standing = $this->service->teamStanding($auction, $this->alpha);
        $this->assertSame($limit, $standing['max_bid']);
        $this->assertSame(11 * 500, $standing['reserve']);

        // Right at the limit is fine.
        $this->assertSame($limit, $this->bid($auction, $lot, $this->alpha, $limit)->amount);

        // One step more is refused ...
        $lot2 = $this->swapTo($auction, $lot);
        $this->fails(fn () => $this->bid($auction, $lot2, $this->alpha, $limit + 500), 'reserve', 'must keep');

        // ... unless overridden, and the bid is flagged.
        $over = $this->bid($auction, $lot2, $this->alpha, $limit + 500, true);
        $this->assertTrue($over->is_override);
        $this->assertSame($limit + 500, $lot2->fresh()->current_bid);
    }

    /**
     * Sets the player on the block aside (dropping their bids) and calls the next one.
     */
    private function swapTo(Auction $auction, AuctionLot $current): AuctionLot
    {
        $this->service->hold($auction, $current->fresh(), $current->fresh()->version);

        return $this->service->callRandom($auction);
    }

    public function test_max_bid_has_no_reserve_once_the_squad_minimum_is_in_reach(): void
    {
        $auction = $this->liveAuction(1, ['team_purse' => 50000, 'min_squad' => 3, 'max_squad' => 4]);

        foreach ([1000, 2000] as $price) {
            TeamPlayer::factory()->create([
                'edition_team_id' => $this->alpha->id,
                'player_registration_id' => $this->registration()->id,
                'sold_amount' => $price,
            ]);
        }

        // Two bought for 3,000; one more reaches the minimum of 3 -> no reserve.
        $standing = $this->service->teamStanding($auction, $this->alpha);
        $this->assertSame(47000, $standing['left']);
        $this->assertSame(0, $standing['reserve']);
        $this->assertSame(47000, $standing['max_bid']);
        $this->assertSame(2, $standing['count']);
    }

    public function test_a_full_squad_cannot_bid(): void
    {
        $auction = $this->liveAuction(2, ['min_squad' => 1, 'max_squad' => 2]);

        for ($i = 0; $i < 2; $i++) {
            TeamPlayer::factory()->create(['edition_team_id' => $this->alpha->id, 'player_registration_id' => $this->registration()->id, 'sold_amount' => 500]);
        }

        $lot = $this->onTheBlock($auction);
        $this->assertTrue($this->service->teamStanding($auction, $this->alpha)['full']);
        $this->fails(fn () => $this->bid($auction, $lot, $this->alpha), 'bid', 'full squad');
    }

    public function test_an_out_of_date_screen_is_refused_and_a_double_tap_places_one_bid(): void
    {
        $auction = $this->liveAuction();
        $lot = $this->onTheBlock($auction);
        $seen = $lot->fresh()->version;

        $first = $this->service->placeBid($auction, $lot, $this->alpha, $seen, null, 'tap-1');
        // Same key again (a retry / double tap): the same bid back, nothing new.
        $again = $this->service->placeBid($auction, $lot, $this->alpha, $seen, null, 'tap-1');
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, AuctionBid::count());

        // Another console changed the player meanwhile; this tap used the old version.
        $this->fails(fn () => $this->service->placeBid($auction, $lot, $this->beta, $seen, null, 'tap-2'), 'stale');
        $this->assertSame(1, AuctionBid::count());
    }

    public function test_nothing_is_accepted_while_paused_and_it_resumes(): void
    {
        $auction = $this->liveAuction();
        $lot = $this->onTheBlock($auction);

        $this->service->pause($auction);
        $this->fails(fn () => $this->bid($auction, $lot, $this->alpha), 'auction', 'paused');

        $this->service->resume($auction);
        $this->assertSame(500, $this->bid($auction, $lot, $this->alpha)->amount);
    }

    public function test_a_player_cannot_be_called_or_a_sale_reopened_while_paused(): void
    {
        $auction = $this->liveAuction(2);
        $lot = $this->onTheBlock($auction);
        $this->bid($auction, $lot, $this->alpha);
        $this->service->sell($auction, $lot, $lot->fresh()->version);

        $this->service->pause($auction);

        $this->fails(fn () => $this->service->callRandom($auction), 'auction', 'paused');
        $this->fails(fn () => $this->service->reopenSold($auction, $lot->fresh()), 'auction', 'paused');

        // Next round and complete are still allowed while paused.
        $this->assertSame(0, $this->service->startNextRound($auction));
        $this->assertSame(Auction::STATUS_COMPLETED, $this->service->complete($auction) ? $auction->fresh()->status : '');
    }

    public function test_undo_takes_back_the_latest_bid_and_the_one_before_stands_again(): void
    {
        $auction = $this->liveAuction();
        $lot = $this->onTheBlock($auction);
        $this->bid($auction, $lot, $this->alpha);
        $second = $this->bid($auction, $lot, $this->beta);

        $this->service->undoBid($auction, $lot, $lot->fresh()->version);

        $lot->refresh();
        $this->assertSame(500, $lot->current_bid);
        $this->assertSame($this->alpha->id, $lot->leading_edition_team_id);
        // Kept in the history, just cancelled.
        $this->assertNotNull($second->fresh()->cancelled_at);
        $this->assertSame(2, AuctionBid::count());

        $this->service->undoBid($auction, $lot, $lot->fresh()->version);
        $this->assertNull($lot->fresh()->current_bid);
        $this->fails(fn () => $this->service->undoBid($auction, $lot, $lot->fresh()->version), 'bid', 'no bid to undo');
    }

    // ----- Selling, hold, rounds -------------------------------------------

    public function test_selling_puts_the_player_in_the_squad_at_the_bid_and_reduces_the_purse(): void
    {
        $auction = $this->liveAuction(2);
        $lot = $this->onTheBlock($auction);
        $this->bid($auction, $lot, $this->alpha, 12000);

        $teamPlayer = $this->service->sell($auction, $lot, $lot->fresh()->version);

        $this->assertSame($this->alpha->id, $teamPlayer->edition_team_id);
        $this->assertSame($lot->player_registration_id, $teamPlayer->player_registration_id);
        $this->assertSame('12000.00', $teamPlayer->sold_amount);

        $lot->refresh();
        $this->assertSame(AuctionLot::SOLD, $lot->status);
        $this->assertSame($teamPlayer->id, $lot->team_player_id);
        $this->assertNull($auction->fresh()->current_lot_id);

        $standing = $this->service->teamStanding($auction, $this->alpha);
        $this->assertSame([12000, 588000, 1], [$standing['spent'], $standing['left'], $standing['count']]);
    }

    public function test_a_player_cannot_be_sold_without_a_bid(): void
    {
        $auction = $this->liveAuction();
        $lot = $this->onTheBlock($auction);

        $this->fails(fn () => $this->service->sell($auction, $lot, $lot->fresh()->version), 'sell', 'no bid');
    }

    public function test_hold_sets_the_player_aside_and_the_next_round_brings_them_back(): void
    {
        $auction = $this->liveAuction(2);
        $lot = $this->onTheBlock($auction);
        $this->bid($auction, $lot, $this->alpha);

        $this->service->hold($auction, $lot, $lot->fresh()->version);

        $lot->refresh();
        $this->assertSame(AuctionLot::HOLD, $lot->status);
        $this->assertNull($lot->current_bid);
        $this->assertNull($lot->leading_edition_team_id);
        $this->assertNull($auction->fresh()->current_lot_id);
        // The bid stays in the history, cancelled.
        $this->assertNotNull(AuctionBid::first()->cancelled_at);

        // Even with a player still pending, the auctioneer may start the next round.
        $this->assertSame(1, $this->service->startNextRound($auction));
        $this->assertSame(2, $auction->fresh()->round);
        $this->assertSame(AuctionLot::PENDING, $lot->fresh()->status);
        $this->assertSame(2, $lot->fresh()->round);
    }

    public function test_any_waiting_or_hold_player_can_be_called_at_any_time(): void
    {
        $auction = $this->liveAuction(3);
        $first = $this->onTheBlock($auction);
        $this->service->hold($auction, $first, $first->fresh()->version);

        $target = $auction->lots()->where('status', AuctionLot::PENDING)->first();
        $this->assertSame($target->id, $this->service->callLot($auction, $target)->id);

        // Calling the held one while the target has no bid quietly puts the target back.
        $called = $this->service->callLot($auction, $first->fresh());
        $this->assertTrue($called->isLive());
        $this->assertSame(AuctionLot::PENDING, $target->fresh()->status);
        $this->assertSame($first->id, $auction->fresh()->current_lot_id);
    }

    public function test_a_player_with_a_standing_bid_must_be_sold_or_held_before_another_is_called(): void
    {
        $auction = $this->liveAuction(2);
        $lot = $this->onTheBlock($auction);
        $this->bid($auction, $lot, $this->alpha);
        $other = $auction->lots()->where('status', AuctionLot::PENDING)->first();

        $this->fails(fn () => $this->service->callLot($auction, $other), 'auction', 'Sell or hold');
        $this->assertSame($lot->id, $auction->fresh()->current_lot_id);
    }

    public function test_release_puts_a_wrongly_called_player_back_among_the_waiting(): void
    {
        $auction = $this->liveAuction(1);
        $lot = $this->onTheBlock($auction);

        $this->service->release($auction, $lot, $lot->fresh()->version);

        $this->assertSame(AuctionLot::PENDING, $lot->fresh()->status);
        $this->assertNull($auction->fresh()->current_lot_id);
    }

    public function test_calling_random_returns_nothing_when_nobody_is_waiting(): void
    {
        $auction = $this->liveAuction(1);
        $lot = $this->onTheBlock($auction);
        $this->bid($auction, $lot, $this->alpha);
        $this->service->sell($auction, $lot, $lot->fresh()->version);

        $this->assertNull($this->service->callRandom($auction));
    }

    // ----- Reopen and complete ---------------------------------------------

    public function test_a_sale_can_be_reopened_and_the_last_bid_still_stands(): void
    {
        $auction = $this->liveAuction(2);
        $lot = $this->onTheBlock($auction);
        $this->bid($auction, $lot, $this->alpha, 4000);
        $this->service->sell($auction, $lot, $lot->fresh()->version);
        $this->assertSame(1, TeamPlayer::count());

        $reopened = $this->service->reopenSold($auction, $lot->fresh());

        $this->assertTrue($reopened->isLive());
        $this->assertSame(0, TeamPlayer::count());
        $this->assertNull($reopened->team_player_id);
        $this->assertSame(4000, $reopened->current_bid);
        $this->assertSame($lot->id, $auction->fresh()->current_lot_id);

        // ... and it can now be sold to the other team instead.
        $this->bid($auction, $lot, $this->beta, 4500);
        $sold = $this->service->sell($auction, $lot, $lot->fresh()->version);
        $this->assertSame($this->beta->id, $sold->edition_team_id);
    }

    public function test_a_sale_cannot_be_reopened_once_the_player_has_played_a_match(): void
    {
        $auction = $this->liveAuction(1);
        $lot = $this->onTheBlock($auction);
        $this->bid($auction, $lot, $this->alpha);
        $teamPlayer = $this->service->sell($auction, $lot, $lot->fresh()->version);
        MatchPlayer::factory()->create(['team_player_id' => $teamPlayer->id]);

        $this->fails(fn () => $this->service->reopenSold($auction, $lot->fresh()), 'auction', 'played a match');
        $this->assertSame(AuctionLot::SOLD, $lot->fresh()->status);
        $this->assertSame(1, TeamPlayer::count());
    }

    public function test_completing_marks_everyone_left_unsold_and_lists_teams_short_of_the_minimum(): void
    {
        $auction = $this->liveAuction(3, ['min_squad' => 2, 'max_squad' => 3]);
        $sold = $this->onTheBlock($auction);
        $this->bid($auction, $sold, $this->alpha);
        $this->service->sell($auction, $sold, $sold->fresh()->version);
        $onBlock = $this->onTheBlock($auction);
        $this->bid($auction, $onBlock, $this->beta, 3000);

        $result = $this->service->complete($auction);

        $this->assertSame(2, $result['unsold']);
        $this->assertSame(0, $auction->lots()->whereIn('status', ['pending', 'hold', 'live'])->count());
        $this->assertSame(Auction::STATUS_COMPLETED, $auction->fresh()->status);
        $this->assertNull($auction->fresh()->current_lot_id);
        // The unsold one on the block lost its bid.
        $this->assertNull($onBlock->fresh()->current_bid);

        // alpha has 1 of the minimum 2, beta has 0 of 2.
        $short = $result['short_teams']->mapWithKeys(fn ($row) => [$row['edition_team']->id => $row['missing']]);
        $this->assertEquals([$this->alpha->id => 1, $this->beta->id => 2], $short->all());

        // A completed auction takes nothing more.
        $this->fails(fn () => $this->service->startNextRound($auction), 'auction');
        $this->fails(fn () => $this->service->refreshPool($auction), 'auction');
        $this->fails(fn () => $this->service->updateSettings($auction, ['team_purse' => 1]), 'auction');
    }
}
