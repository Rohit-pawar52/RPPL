<?php

namespace Tests\Feature\Auction;

use App\Models\Auction;
use App\Models\AuctionLot;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\Auction\AuctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The player pool follows the registrations by itself: a player who becomes
 * paid joins the waiting players, one who is refunded or put in a squad
 * leaves, and nobody who is already live, sold or unsold is ever disturbed.
 * The console also tells the auctioneer when the pool is out of step (for
 * example after a direct database change) and fixes it on one button.
 */
class AuctionPoolSyncTest extends TestCase
{
    use RefreshDatabase;

    private AuctionService $service;

    private Edition $edition;

    private EditionTeam $alpha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AuctionService::class);
        $this->edition = Edition::factory()->create(['status' => 'active']);
        $this->alpha = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
        EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
    }

    private function registration(string $payment = 'paid', array $player = [], ?Edition $edition = null): PlayerRegistration
    {
        return PlayerRegistration::factory()->create([
            'edition_id' => ($edition ?? $this->edition)->id,
            'player_id' => Player::factory()->create($player)->id,
            'payment_status' => $payment,
        ]);
    }

    private function auction(int $paidPlayers = 2): Auction
    {
        foreach (range(1, $paidPlayers) as $ignored) {
            $this->registration();
        }

        return $this->service->create($this->edition);
    }

    /**
     * @return list<int>
     */
    private function pool(Auction $auction): array
    {
        return $auction->lots()->orderBy('player_registration_id')->pluck('player_registration_id')->all();
    }

    private function lotOf(Auction $auction, PlayerRegistration $registration): ?AuctionLot
    {
        return $auction->lots()->where('player_registration_id', $registration->id)->first();
    }

    // ----- A player joins ---------------------------------------------------------------

    public function test_a_player_who_pays_after_the_auction_was_created_joins_the_pool(): void
    {
        $auction = $this->auction();
        $late = $this->registration('pending');
        $this->assertNull($this->lotOf($auction, $late));

        $late->update(['payment_status' => 'paid']);

        $this->assertSame(AuctionLot::PENDING, $this->lotOf($auction, $late)->status);
        $this->assertSame(3, $auction->lots()->count());
    }

    public function test_a_registration_that_is_created_already_paid_joins_the_pool_and_only_once(): void
    {
        $auction = $this->auction();

        $paid = $this->registration();
        $this->assertSame(1, $auction->lots()->where('player_registration_id', $paid->id)->count());

        // Refunded and paid again: back in, still a single row.
        $paid->update(['payment_status' => 'refunded']);
        $paid->update(['payment_status' => 'paid']);
        $this->assertSame(1, $auction->lots()->where('player_registration_id', $paid->id)->count());
    }

    public function test_the_pool_keeps_following_while_the_auction_is_live_or_paused(): void
    {
        $auction = $this->service->start($this->auction());
        $this->registration();
        $this->assertSame(3, $auction->lots()->count());

        $this->service->pause($auction);
        $this->registration();
        $this->assertSame(4, $auction->lots()->count());
    }

    public function test_an_inactive_player_does_not_join(): void
    {
        $auction = $this->auction(1);

        $this->registration('paid', ['is_active' => false]);

        $this->assertSame(1, $auction->lots()->count());
    }

    public function test_a_walk_in_player_is_in_the_pool_exactly_once(): void
    {
        $auction = $this->service->start($this->auction(1));

        $lot = $this->service->addWalkInPlayer($auction, 'Walk In', '9876543210');

        $this->assertSame(2, $auction->lots()->count());
        $this->assertSame(1, $auction->lots()->where('player_registration_id', $lot->player_registration_id)->count());
    }

    // ----- A player leaves --------------------------------------------------------------

    public function test_a_waiting_player_who_is_refunded_leaves_the_pool(): void
    {
        $auction = $this->auction();
        $leaving = $this->registration();
        $this->assertNotNull($this->lotOf($auction, $leaving));

        $leaving->update(['payment_status' => 'refunded']);

        $this->assertNull($this->lotOf($auction, $leaving));
    }

    public function test_a_player_put_in_a_squad_leaves_the_pool_and_returns_when_taken_out(): void
    {
        $auction = $this->auction();
        $registration = $this->registration();

        $squadRow = TeamPlayer::factory()->create(['edition_team_id' => $this->alpha->id, 'player_registration_id' => $registration->id]);
        $this->assertNull($this->lotOf($auction, $registration));

        $squadRow->delete();
        $this->assertSame(AuctionLot::PENDING, $this->lotOf($auction, $registration)->status);
    }

    public function test_a_player_on_hold_who_is_refunded_leaves_too(): void
    {
        $auction = $this->service->start($this->auction(1));
        $held = $this->registration();
        $this->lotOf($auction, $held)->update(['status' => AuctionLot::HOLD]);

        $held->update(['payment_status' => 'refunded']);

        $this->assertNull($this->lotOf($auction, $held));
    }

    public function test_players_who_are_live_sold_or_unsold_are_never_removed(): void
    {
        $auction = $this->auction(1);
        $statuses = [AuctionLot::LIVE, AuctionLot::SOLD, AuctionLot::UNSOLD];
        $registrations = [];

        foreach ($statuses as $status) {
            $registrations[$status] = $this->registration();
            $this->lotOf($auction, $registrations[$status])->update(['status' => $status]);
        }

        foreach ($registrations as $registration) {
            $registration->update(['payment_status' => 'refunded']);
        }

        foreach ($registrations as $status => $registration) {
            $this->assertSame($status, $this->lotOf($auction, $registration)->status, "A {$status} player must stay.");
        }
    }

    public function test_selling_a_player_through_the_auction_still_works_with_the_sync_on(): void
    {
        $auction = $this->service->start($this->auction(1));
        $lot = $this->service->callRandom($auction);
        $this->service->placeBid($auction, $lot, $this->alpha, $lot->fresh()->version);

        $this->service->sell($auction, $lot, $lot->fresh()->version);

        $this->assertSame(AuctionLot::SOLD, $lot->fresh()->status);
        $this->assertSame(1, TeamPlayer::count());
        $this->assertSame(1, $auction->lots()->count());

        // Taking the sale back puts the player back on the block, not in a second row.
        $this->service->reopenSold($auction, $lot->fresh(), $lot->fresh()->version);
        $this->assertSame(1, $auction->lots()->count());
        $this->assertSame(0, TeamPlayer::count());
    }

    // ----- What is left alone -----------------------------------------------------------------

    public function test_a_completed_auction_is_never_changed(): void
    {
        $auction = $this->service->start($this->auction());
        $this->service->complete($auction);
        $before = $this->pool($auction);

        $this->registration();

        $this->assertSame($before, $this->pool($auction));
    }

    public function test_a_registration_of_another_season_changes_nothing(): void
    {
        $auction = $this->auction();
        $otherSeason = Edition::factory()->create(['status' => 'active']);

        $this->registration('paid', [], $otherSeason);

        $this->assertSame(2, $auction->lots()->count());
    }

    public function test_with_no_auction_for_the_season_a_payment_just_works(): void
    {
        $registration = $this->registration('pending');

        $registration->update(['payment_status' => 'paid']);

        $this->assertSame(0, AuctionLot::count());
    }

    // ----- The console notice and its button ----------------------------------------------------

    private function adminUser(): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id]);
    }

    private function url(string $name): string
    {
        return route('admin.auctions.console.'.$name, $this->edition);
    }

    public function test_the_console_says_who_is_missing_from_the_pool_and_one_button_fixes_it(): void
    {
        $admin = $this->adminUser();
        $auction = $this->service->start($this->auction(1));
        $stays = $this->registration(player: ['name' => 'Stale Sam']);

        // Changed behind the application's back, so the automatic sync missed them.
        $missing = PlayerRegistration::withoutEvents(fn () => $this->registration(player: ['name' => 'Missing Mo']));
        PlayerRegistration::withoutEvents(fn () => $stays->update(['payment_status' => 'refunded']));

        $check = $this->actingAs($admin)->getJson($this->url('state'))->assertOk()->json('state.pool_check');
        $this->assertSame(['Missing Mo'], $check['missing']);
        $this->assertSame(['Stale Sam'], $check['stale']);

        $this->actingAs($admin)->postJson($this->url('pool'))
            ->assertOk()
            ->assertJsonPath('message', 'Pool updated: 1 added, 1 removed.')
            ->assertJsonPath('state.pool_check', ['missing' => [], 'stale' => []]);

        $this->assertNotNull($this->lotOf($auction, $missing));
        $this->assertNull($this->lotOf($auction, $stays));
    }

    public function test_nothing_is_flagged_when_the_pool_is_right(): void
    {
        $this->service->start($this->auction());

        $this->actingAs($this->adminUser())->getJson($this->url('state'))
            ->assertJsonPath('state.pool_check', ['missing' => [], 'stale' => []]);
    }

    public function test_the_pool_button_is_for_those_who_run_the_auction_and_not_after_it_is_over(): void
    {
        $auction = $this->service->start($this->auction(1));
        $scorer = User::factory()->create(['role_id' => Role::firstOrCreate(['slug' => 'scorer'], ['name' => 'Scorer'])->id]);

        $this->actingAs($scorer)->postJson($this->url('pool'))->assertForbidden();

        $this->service->complete($auction);
        $this->actingAs($this->adminUser())->postJson($this->url('pool'))
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }
}
