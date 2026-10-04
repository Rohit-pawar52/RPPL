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
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\Auction\AuctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The live console's endpoints: who may use them, the state they return
 * (what each team may do, the player on the block, what is safe to show),
 * and every action, including the refusals that come back as a 422 with the
 * fresh state so the screen can correct itself.
 */
class AuctionConsoleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $auctioneer;

    private Edition $edition;

    private EditionTeam $alpha;

    private EditionTeam $beta;

    private Auction $auction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['name' => 'Admin', 'slug' => 'admin'])->id]);
        $this->auctioneer = User::factory()->create(['role_id' => Role::firstWhere('slug', 'auctioneer')->id]);

        $this->edition = Edition::factory()->create(['status' => 'active']);
        $this->alpha = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
        $this->beta = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
    }

    private function liveAuction(int $players = 3, array $settings = []): Auction
    {
        for ($i = 0; $i < $players; $i++) {
            PlayerRegistration::factory()->create(['edition_id' => $this->edition->id, 'payment_status' => 'paid']);
        }

        $service = app(AuctionService::class);
        $this->auction = $service->start($service->create($this->edition, null, $settings));

        return $this->auction;
    }

    private function url(string $name): string
    {
        return route('admin.auctions.console.'.$name, $this->edition);
    }

    private function act(string $name, array $body = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin)->postJson($this->url($name), $body);
    }

    /**
     * Puts a player on the block through the endpoint and returns the lot.
     *
     * @return array<string, mixed>
     */
    private function callOne(): array
    {
        return $this->act('random')->assertOk()->json('state.lot');
    }

    private function bid(array $lot, EditionTeam $team, array $extra = []): TestResponse
    {
        return $this->act('bid', ['lot_id' => $lot['id'], 'version' => $lot['version'], 'team_id' => $team->id, ...$extra]);
    }

    private function team(TestResponse $response, EditionTeam $team): array
    {
        return collect($response->json('state.teams'))->firstWhere('id', $team->id);
    }

    // ----- Access ------------------------------------------------------------

    public function test_admin_and_auctioneer_open_the_console_and_a_scorer_does_not(): void
    {
        $this->liveAuction();
        $scorer = User::factory()->create(['role_id' => Role::firstOrCreate(['slug' => 'scorer'], ['name' => 'Scorer'])->id]);

        foreach ([$this->admin, $this->auctioneer] as $user) {
            $this->actingAs($user)->get(route('admin.auctions.console', $this->edition))
                ->assertOk()
                ->assertSee('auction-console-data', false)
                ->assertSee(str_replace('/', '\/', $this->url('bid')), false);
            $this->actingAs($user)->getJson($this->url('state'))->assertOk()->assertJsonPath('ok', true);
        }

        $this->actingAs($scorer)->get(route('admin.auctions.console', $this->edition))->assertForbidden();
        $this->actingAs($scorer)->getJson($this->url('state'))->assertForbidden();
        $this->actingAs($scorer)->postJson($this->url('random'))->assertForbidden();
        $this->assertSame(0, AuctionLot::where('status', 'live')->count());
    }

    public function test_a_guest_cannot_use_the_console_endpoints(): void
    {
        $this->liveAuction();

        $this->postJson($this->url('random'))->assertUnauthorized();
        $this->getJson($this->url('state'))->assertUnauthorized();
    }

    public function test_the_console_needs_a_started_auction(): void
    {
        $this->actingAs($this->admin)->get(route('admin.auctions.console', $this->edition))->assertNotFound();

        Auction::factory()->create(['edition_id' => $this->edition->id]);

        $this->actingAs($this->admin)->get(route('admin.auctions.console', $this->edition))
            ->assertRedirect(route('admin.auctions.show', $this->edition))
            ->assertSessionHas('error');
    }

    // ----- State -------------------------------------------------------------

    public function test_the_state_describes_the_auction_the_teams_and_who_is_waiting(): void
    {
        $this->liveAuction(3);

        $state = $this->actingAs($this->admin)->getJson($this->url('state'))->assertOk()->json('state');

        $this->assertSame('live', $state['auction']['status']);
        $this->assertSame([500, 500, 12, 15], [$state['auction']['min_bid'], $state['auction']['bid_step'], $state['auction']['min_squad'], $state['auction']['max_squad']]);
        $this->assertSame(3, $state['counts']['pending']);
        $this->assertNull($state['lot']);
        $this->assertCount(3, $state['waiting']);

        $team = collect($state['teams'])->firstWhere('id', $this->alpha->id);
        $this->assertSame([600000, 0, 600000, 0], [$team['purse'], $team['spent'], $team['left'], $team['count']]);
        // 11 more players needed after this one -> keeps 11 x 500.
        $this->assertSame(600000 - 11 * 500, $team['max_bid']);
        $this->assertSame('idle', $team['state']);
    }

    public function test_nothing_private_about_a_player_is_in_the_state(): void
    {
        $player = Player::factory()->create([
            'name' => 'Secret Sharma',
            'phone' => '9876543210',
            'email' => 'private@example.test',
            'date_of_birth' => '1999-01-02',
            'primary_role' => 'batter',
            'batting_style' => 'left_hand',
        ]);
        PlayerRegistration::factory()->create([
            'edition_id' => $this->edition->id,
            'player_id' => $player->id,
            'payment_status' => 'paid',
            'village' => 'Chicholi',
            'aadhaar_document_path' => 'registrations/aadhaar.jpg',
        ]);
        // The only paid player in the pool.
        $this->liveAuction(0);

        $json = $this->act('random')->assertOk()->getContent();

        foreach (['9876543210', 'private@example.test', '1999-01-02', 'aadhaar'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }

        $lot = json_decode($json, true)['state']['lot'];
        $this->assertSame('Secret Sharma', $lot['name']);
        $this->assertSame(['Batter', 'Left-hand', 'Chicholi'], [$lot['role'], $lot['batting'], $lot['village']]);
        // First time in RPPL: no past record.
        $this->assertNull($lot['stats']);
    }

    public function test_each_teams_state_says_why_it_cannot_just_be_tapped(): void
    {
        $this->liveAuction(3, ['team_purse' => 10000, 'min_squad' => 2, 'max_squad' => 2]);
        $gamma = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
        $delta = EditionTeam::factory()->create(['edition_id' => $this->edition->id, 'auction_purse' => 600]);
        // alpha is full: two players already.
        foreach ([1, 2] as $i) {
            TeamPlayer::factory()->create([
                'edition_team_id' => $this->alpha->id,
                'player_registration_id' => PlayerRegistration::factory()->create(['edition_id' => $this->edition->id])->id,
                'sold_amount' => 100,
            ]);
        }

        $lot = $this->callOne();
        $response = $this->bid($lot, $this->beta, ['amount' => 500])->assertOk();

        $this->assertSame('full', $this->team($response, $this->alpha)['state']);
        $this->assertSame('leading', $this->team($response, $this->beta)['state']);
        $this->assertSame('ok', $this->team($response, $gamma)['state']);
        // Next bid is 1,000 but delta only has 600.
        $this->assertSame('purse', $this->team($response, $delta)['state']);

        // gamma: with a minimum squad of 2 it must keep 500 for the second
        // player, so it can bid 9,500 at most; the next bid after a jump to
        // 9,500 would be 10,000 -> over the limit but within the purse.
        $jump = $this->bid($response->json('state.lot'), $gamma, ['amount' => 9500])->assertOk();
        $this->assertSame('reserve', $this->team($jump, $this->beta)['state']);
    }

    // ----- Calling players ---------------------------------------------------

    public function test_a_random_player_is_called_and_nobody_waiting_is_said_plainly(): void
    {
        $this->liveAuction(1);

        $lot = $this->callOne();
        $this->assertNotEmpty($lot['name']);
        $this->assertNull($lot['current_bid']);
        $this->assertSame(500, $lot['next_bid']);

        // The only player is on the block, so nobody is waiting: a plain
        // message, not an error, and the player stays where they are.
        $empty = $this->act('random')->assertOk();
        $this->assertStringContainsString('Nobody is waiting', $empty->json('message'));
        $this->assertSame($lot['id'], $empty->json('state.lot.id'));
    }

    public function test_a_chosen_player_can_be_called_and_one_with_a_bid_must_be_dealt_with_first(): void
    {
        $this->liveAuction(2);
        $first = $this->callOne();
        $other = collect($this->actingAs($this->admin)->getJson($this->url('state'))->json('state.waiting'))->first();

        // Calling someone else while the first has no bid quietly puts the first back.
        $this->act('call', ['lot_id' => $other['id']])->assertOk()->assertJsonPath('state.lot.id', $other['id']);

        $lot = $this->actingAs($this->admin)->getJson($this->url('state'))->json('state.lot');
        $this->bid($lot, $this->alpha)->assertOk();

        $this->act('call', ['lot_id' => $first['id']])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('key', 'auction')
            ->assertJsonPath('state.lot.id', $other['id']);
    }

    // ----- Bidding -----------------------------------------------------------

    public function test_bids_go_up_a_step_or_jump_and_the_screen_gets_the_fresh_state(): void
    {
        $this->liveAuction();
        $lot = $this->callOne();

        $first = $this->bid($lot, $this->alpha)->assertOk();
        $this->assertSame(500, $first->json('state.lot.current_bid'));
        $this->assertSame($this->alpha->id, $first->json('state.lot.leading_team.id'));

        $second = $this->bid($first->json('state.lot'), $this->beta)->assertOk();
        $this->assertSame(1000, $second->json('state.lot.current_bid'));

        $jump = $this->bid($second->json('state.lot'), $this->alpha, ['amount' => 25000])->assertOk();
        $this->assertSame(25000, $jump->json('state.lot.current_bid'));
        $this->assertSame(25500, $jump->json('state.lot.next_bid'));
        $this->assertCount(3, $jump->json('state.lot.bids'));
    }

    public function test_a_tap_on_an_out_of_date_screen_is_refused_and_the_screen_is_corrected(): void
    {
        $this->liveAuction();
        $lot = $this->callOne();
        $this->bid($lot, $this->alpha)->assertOk();

        // The same (old) version again, from another console.
        $this->bid($lot, $this->beta)
            ->assertStatus(422)
            ->assertJsonPath('key', 'stale')
            ->assertJsonPath('state.lot.current_bid', 500);
    }

    public function test_a_double_tap_with_the_same_key_places_one_bid(): void
    {
        $this->liveAuction();
        $lot = $this->callOne();

        $this->bid($lot, $this->alpha, ['key' => 'tap-1'])->assertOk();
        $this->bid($lot, $this->alpha, ['key' => 'tap-1'])->assertOk();

        $this->assertSame(1, AuctionBid::count());
    }

    public function test_the_reserve_limit_comes_back_as_an_offer_to_override(): void
    {
        $this->liveAuction(2, ['team_purse' => 100000]);
        $lot = $this->callOne();
        $over = 100000 - 11 * 500 + 500;

        $refused = $this->bid($lot, $this->alpha, ['amount' => $over])
            ->assertStatus(422)
            ->assertJsonPath('key', 'reserve');
        $this->assertStringContainsString('must keep', $refused->json('message'));
        $this->assertNull($refused->json('state.lot.current_bid'));

        $allowed = $this->bid($refused->json('state.lot'), $this->alpha, ['amount' => $over, 'override' => true])->assertOk();
        $this->assertSame($over, $allowed->json('state.lot.current_bid'));
        $this->assertTrue($allowed->json('state.lot.bids.0.override'));
    }

    public function test_a_team_not_in_the_season_or_a_bad_amount_is_refused(): void
    {
        $this->liveAuction();
        $lot = $this->callOne();
        $stranger = EditionTeam::factory()->create();

        $this->bid($lot, $stranger)->assertStatus(422)->assertJsonPath('key', 'bid');
        $this->bid($lot, $this->alpha, ['amount' => 750])->assertStatus(422)->assertJsonPath('key', 'bid');
        $this->bid($lot, $this->alpha, ['amount' => 0])->assertStatus(422); // validation
        $this->bid($lot, $this->alpha, ['amount' => 700000])->assertStatus(422)->assertJsonPath('key', 'bid');
    }

    // ----- Sell, hold, undo and the rest ------------------------------------

    public function test_undo_sell_and_the_squad_in_the_state(): void
    {
        $this->liveAuction(2);
        $lot = $this->callOne();
        $one = $this->bid($lot, $this->alpha, ['amount' => 4000])->assertOk();
        $two = $this->bid($one->json('state.lot'), $this->beta)->assertOk();

        $undone = $this->act('undo', ['lot_id' => $lot['id'], 'version' => $two->json('state.lot.version')])->assertOk();
        $this->assertSame(4000, $undone->json('state.lot.current_bid'));
        $this->assertSame($this->alpha->id, $undone->json('state.lot.leading_team.id'));

        $sold = $this->act('sell', ['lot_id' => $lot['id'], 'version' => $undone->json('state.lot.version')])->assertOk();
        $this->assertNull($sold->json('state.lot'));
        $this->assertSame(1, $sold->json('state.counts.sold'));

        $alpha = $this->team($sold, $this->alpha);
        $this->assertSame([4000, 596000, 1], [$alpha['spent'], $alpha['left'], $alpha['count']]);
        $this->assertSame(4000, $alpha['players'][0]['amount']);

        $sale = $sold->json('state.sold.0');
        $this->assertSame([4000, $lot['name']], [$sale['amount'], $sale['name']]);
        $this->assertSame(1, TeamPlayer::count());
    }

    public function test_the_sold_list_has_every_sale_newest_first_and_says_which_can_be_undone(): void
    {
        $this->liveAuction(5);

        $names = [];
        foreach ([$this->alpha, $this->beta, $this->alpha] as $team) {
            $lot = $this->callOne();
            $names[] = $lot['name'];
            $bid = $this->bid($lot, $team, ['amount' => 2000])->assertOk();
            $this->act('sell', ['lot_id' => $lot['id'], 'version' => $bid->json('state.lot.version')])->assertOk();

            // Sales a few seconds apart, as in a real hall (the time of a sale is kept to the second).
            $this->travel(5)->seconds();
        }

        $state = $this->actingAs($this->admin)->getJson($this->url('state'))->assertOk()->json('state');
        $this->assertCount(3, $state['sold']);
        // Newest first, numbered in the order they happened.
        $this->assertSame([3, 2, 1], array_column($state['sold'], 'number'));
        $this->assertSame(array_reverse($names), array_column($state['sold'], 'name'));
        $this->assertSame([2000, 1, false, false], [$state['sold'][0]['amount'], $state['sold'][0]['bids'], $state['sold'][0]['locked'], $state['sold'][0]['orphan']]);
        $this->assertSame($this->alpha->team->name, $state['sold'][0]['team']);

        // A player who has played a match is marked: the sale can no longer be undone.
        MatchPlayer::factory()->create(['team_player_id' => TeamPlayer::first()->id]);
        $locked = collect($this->actingAs($this->admin)->getJson($this->url('state'))->json('state.sold'))->where('locked', true);
        $this->assertCount(1, $locked);
    }

    public function test_an_old_sale_is_taken_back_from_the_console(): void
    {
        $this->liveAuction(4);
        $first = $this->callOne();
        $bid = $this->bid($first, $this->alpha, ['amount' => 4000])->assertOk();
        $this->act('sell', ['lot_id' => $first['id'], 'version' => $bid->json('state.lot.version')])->assertOk();
        $second = $this->callOne();
        $bid = $this->bid($second, $this->beta, ['amount' => 1000])->assertOk();
        $this->act('sell', ['lot_id' => $second['id'], 'version' => $bid->json('state.lot.version')])->assertOk();

        $response = $this->act('take-back', ['lot_id' => $first['id']])->assertOk();

        $this->assertSame($first['name'].' is back among the waiting players.', $response->json('message'));
        $this->assertSame(1, $response->json('state.counts.sold'));
        $this->assertSame(0, TeamPlayer::where('edition_team_id', $this->alpha->id)->count());
        $this->assertSame(600000, $this->team($response, $this->alpha)['left']);
        $this->assertContains($first['name'], collect($response->json('state.waiting'))->pluck('name')->all());

        // A player who is not sold is refused, with the fresh state.
        $this->act('take-back', ['lot_id' => $first['id']])
            ->assertStatus(422)
            ->assertJsonPath('key', 'auction');
    }

    public function test_taking_a_sale_back_is_for_those_who_run_the_auction(): void
    {
        $this->liveAuction(2);
        $lot = $this->callOne();
        $scorer = User::factory()->create(['role_id' => Role::firstOrCreate(['slug' => 'scorer'], ['name' => 'Scorer'])->id]);

        $this->act('take-back', ['lot_id' => $lot['id']], $scorer)->assertForbidden();
        $this->act('take-back', ['lot_id' => $lot['id']], $this->auctioneer)->assertStatus(422);
    }

    public function test_selling_without_a_bid_is_refused(): void
    {
        $this->liveAuction();
        $lot = $this->callOne();

        $this->act('sell', ['lot_id' => $lot['id'], 'version' => $lot['version']])
            ->assertStatus(422)
            ->assertJsonPath('key', 'sell');
    }

    public function test_hold_then_the_next_round_brings_the_player_back_and_a_sale_can_be_reopened(): void
    {
        $this->liveAuction(2);
        $held = $this->callOne();
        $this->bid($held, $this->alpha)->assertOk();
        $heldVersion = $this->actingAs($this->admin)->getJson($this->url('state'))->json('state.lot.version');

        $afterHold = $this->act('hold', ['lot_id' => $held['id'], 'version' => $heldVersion])->assertOk();
        $this->assertSame(1, $afterHold->json('state.counts.hold'));
        $this->assertNull($afterHold->json('state.lot'));

        $round = $this->act('next-round')->assertOk();
        $this->assertStringContainsString('Round 2 started', $round->json('message'));
        $this->assertSame([0, 2], [$round->json('state.counts.hold'), $round->json('state.counts.pending')]);

        // Sell one, then reopen it.
        $lot = $this->callOne();
        $bid = $this->bid($lot, $this->beta)->assertOk();
        $this->act('sell', ['lot_id' => $lot['id'], 'version' => $bid->json('state.lot.version')])->assertOk();

        $reopened = $this->act('reopen', ['lot_id' => $lot['id']])->assertOk();
        $this->assertSame($lot['id'], $reopened->json('state.lot.id'));
        $this->assertSame(0, TeamPlayer::count());
    }

    public function test_a_wrongly_called_player_goes_back_and_the_live_bid_switch_and_pause_work(): void
    {
        $this->liveAuction();
        $lot = $this->callOne();

        $this->act('release', ['lot_id' => $lot['id'], 'version' => $lot['version']])->assertOk()->assertJsonPath('state.lot', null);

        $this->act('live-bids', ['show' => false])->assertOk()->assertJsonPath('state.auction.show_live_bids', false);
        $this->act('live-bids', ['show' => true])->assertOk()->assertJsonPath('state.auction.show_live_bids', true);

        $this->act('pause')->assertOk()->assertJsonPath('state.auction.status', 'paused');
        $this->act('random')->assertStatus(422)->assertJsonPath('state.auction.status', 'paused');
        $this->act('resume')->assertOk()->assertJsonPath('state.auction.status', 'live');
    }

    // ----- Walk-ins ------------------------------------------------------------

    public function test_someone_who_turns_up_on_the_day_is_registered_as_paid_and_waits_with_the_others(): void
    {
        $this->liveAuction(1);

        $response = $this->act('walk-in', ['name' => 'Late Comer', 'phone' => '+91 98765 43210'])->assertOk();

        $this->assertStringContainsString('Late Comer', $response->json('message'));
        $this->assertSame(2, $response->json('state.counts.pending'));

        $player = Player::firstWhere('phone', '9876543210');
        $this->assertSame('Late Comer', $player->name);
        $registration = PlayerRegistration::where('player_id', $player->id)->first();
        $this->assertSame('paid', $registration->payment_status);
        $this->assertSame($this->edition->id, $registration->edition_id);
        $this->assertSame(AuctionLot::PENDING, AuctionLot::firstWhere('player_registration_id', $registration->id)->status);
    }

    public function test_a_walk_in_who_is_already_registered_or_inactive_is_refused(): void
    {
        $this->liveAuction(1);
        $known = Player::factory()->create(['phone' => '9000000001', 'name' => 'Already Here']);
        PlayerRegistration::factory()->create(['edition_id' => $this->edition->id, 'player_id' => $known->id, 'payment_status' => 'pending']);
        Player::factory()->inactive()->create(['phone' => '9000000002']);

        $this->act('walk-in', ['name' => 'Already Here', 'phone' => '9000000001'])
            ->assertStatus(422)->assertJsonPath('key', 'phone');
        $this->act('walk-in', ['name' => 'Gone', 'phone' => '9000000002'])
            ->assertStatus(422)->assertJsonPath('key', 'phone');
        $this->act('walk-in', ['name' => 'No Phone', 'phone' => 'abc'])
            ->assertStatus(422)->assertJsonPath('key', 'phone');

        // Only the one player the auction started with.
        $this->assertSame(1, AuctionLot::count());
    }
}
