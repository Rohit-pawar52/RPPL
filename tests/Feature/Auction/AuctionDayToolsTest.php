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
use App\Services\Auction\AuctionReadinessService;
use App\Services\Auction\AuctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * What the auctioneer needs on the day: a log of who did what, a ready-for-the-day check, corrections made from the
 * console (player details, a bid on the wrong team, a sold price), a role-wise random call, a rehearsal reset and
 * downloads. Rules live in AuctionService; the endpoints are checked through the console and the setup page.
 */
class AuctionDayToolsTest extends TestCase
{
    use RefreshDatabase;

    private AuctionService $service;

    private User $admin;

    private Edition $edition;

    private EditionTeam $alpha;

    private EditionTeam $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AuctionService::class);
        $this->admin = User::factory()->create(['role_id' => Role::create(['name' => 'Admin', 'slug' => 'admin'])->id]);
        $this->edition = Edition::factory()->create(['status' => 'active']);
        $this->alpha = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
        $this->beta = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
    }

    private function registration(string $name = 'Player', array $player = [], array $registration = []): PlayerRegistration
    {
        return PlayerRegistration::factory()->create($registration + [
            'edition_id' => $this->edition->id,
            'player_id' => Player::factory()->create($player + ['name' => $name])->id,
            'payment_status' => 'paid',
        ]);
    }

    private function liveAuction(int $players = 4, array $settings = []): Auction
    {
        foreach (range(1, $players) as $i) {
            $this->registration('Player '.$i);
        }

        return $this->service->start($this->service->create($this->edition, null, $settings));
    }

    private function callAndSell(Auction $auction, EditionTeam $team, int $amount = 1000): AuctionLot
    {
        $lot = $this->service->callRandom($auction);
        $this->service->placeBid($auction, $lot, $team, $lot->fresh()->version, $amount);
        $this->service->sell($auction, $lot, $lot->fresh()->version);

        return $lot->fresh();
    }

    private function fails(callable $action, string $key): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());

            return;
        }

        $this->fail("Expected a '{$key}' rule failure.");
    }

    // ----- The activity log -------------------------------------------------------------------

    public function test_every_step_is_logged_with_who_did_it_and_reads_as_a_sentence(): void
    {
        $this->actingAs($this->admin);
        $auction = $this->liveAuction(2);

        $lot = $this->service->callRandom($auction);
        $this->service->placeBid($auction, $lot, $this->alpha, $lot->fresh()->version, 500);
        $this->service->placeBid($auction, $lot, $this->beta, $lot->fresh()->version, 1000);
        $this->service->undoBid($auction, $lot, $lot->fresh()->version);
        $this->service->hold($auction, $lot, $lot->fresh()->version);

        $types = $auction->events()->orderBy('id')->pluck('type')->all();
        // Creating the auction filled the pool (logged), then it started.
        $this->assertSame(['pool', 'started', 'called', 'bid', 'bid', 'bid_undone', 'held'], $types);
        $this->assertSame($this->admin->id, $auction->events()->where('type', 'held')->value('user_id'));

        $sentences = $auction->events()->orderBy('id')->get()->map->describe()->all();
        $this->assertStringContainsString($lot->playerRegistration->player->name.' was put on hold.', $sentences[6]);
        $this->assertStringContainsString('The bid of 1,000 pts by '.$this->beta->team->name, $sentences[5]);
    }

    public function test_a_sale_and_its_reversal_are_logged_and_the_setup_page_shows_the_log(): void
    {
        $this->actingAs($this->admin);
        $auction = $this->liveAuction(2);
        $lot = $this->callAndSell($auction, $this->alpha, 1500);
        $this->service->returnToWaiting($auction, $lot);

        $this->assertTrue($auction->events()->where('type', 'sold')->where('amount', 1500)->exists());
        $this->assertTrue($auction->events()->where('type', 'taken_back')->exists());

        $this->get(route('admin.auctions.show', $this->edition))
            ->assertOk()
            ->assertSee('Activity log')
            ->assertSee('was sold to '.$this->alpha->team->name.' for 1,500 pts.')
            ->assertSee('was taken back from their team');
    }

    // ----- Console corrections ----------------------------------------------------------------

    public function test_player_details_can_be_corrected_and_the_public_page_follows(): void
    {
        $this->actingAs($this->admin);
        $auction = $this->liveAuction(1);
        $lot = $this->service->callRandom($auction);

        $this->postJson(route('admin.auctions.console.player', $this->edition), [
            'lot_id' => $lot->id,
            'name' => 'Ramesh Patil',
            'village' => '  Shirur ',
            'primary_role' => 'bowler',
        ])->assertOk()->assertJsonPath('ok', true);

        $player = $lot->fresh()->playerRegistration->player->fresh();
        $this->assertSame('Ramesh Patil', $player->name);
        $this->assertSame('bowler', $player->primary_role);
        $this->assertSame('Shirur', $lot->fresh()->playerRegistration->fresh()->village);
        $this->assertTrue($auction->events()->where('type', 'player_edited')->where('note', 'like', '%name%')->exists());

        $this->getJson(route('public.auction.data'))->assertOk()
            ->assertJsonPath('state.lot.name', 'Ramesh Patil')
            ->assertJsonPath('state.lot.village', 'Shirur');

        $this->postJson(route('admin.auctions.console.player', $this->edition), ['lot_id' => $lot->id, 'name' => 'X', 'primary_role' => 'umpire'])
            ->assertStatus(422);
    }

    public function test_a_bid_on_the_wrong_team_moves_to_the_right_one_at_the_same_amount(): void
    {
        $this->actingAs($this->admin);
        $auction = $this->liveAuction(1);
        $lot = $this->service->callRandom($auction);
        $this->service->placeBid($auction, $lot, $this->alpha, $lot->fresh()->version, 2000);

        $this->postJson(route('admin.auctions.console.fix-bid', $this->edition), [
            'lot_id' => $lot->id, 'version' => $lot->fresh()->version, 'team_id' => $this->beta->id,
        ])->assertOk()->assertJsonPath('ok', true);

        $lot = $lot->fresh();
        $this->assertSame($this->beta->id, $lot->leading_edition_team_id);
        $this->assertSame(2000, $lot->current_bid);
        $this->assertSame(1, $lot->bids()->standing()->count());
        $this->assertTrue($auction->events()->where('type', 'bid_team_changed')->where('amount', 2000)->exists());

        // Already with that team: refused, nothing changes.
        $this->postJson(route('admin.auctions.console.fix-bid', $this->edition), [
            'lot_id' => $lot->id, 'version' => $lot->version, 'team_id' => $this->beta->id,
        ])->assertStatus(422);
        $this->assertSame(1, $lot->fresh()->bids()->standing()->count());
    }

    public function test_a_bid_cannot_move_to_a_team_that_cannot_afford_it_and_nothing_changes(): void
    {
        $this->actingAs($this->admin);
        $auction = $this->liveAuction(1, ['team_purse' => 100000, 'min_squad' => 1, 'max_squad' => 5]);
        $this->beta->update(['auction_purse' => 1000]);
        $lot = $this->service->callRandom($auction);
        $this->service->placeBid($auction, $lot, $this->alpha, $lot->fresh()->version, 5000);

        $this->fails(fn () => $this->service->moveLatestBid($auction, $lot, $this->beta, $lot->fresh()->version), 'bid');

        $lot = $lot->fresh();
        $this->assertSame($this->alpha->id, $lot->leading_edition_team_id);
        $this->assertSame(1, $lot->bids()->standing()->count());
    }

    public function test_a_sold_price_can_be_corrected_within_the_steps_and_the_teams_purse(): void
    {
        $this->actingAs($this->admin);
        $auction = $this->liveAuction(2, ['team_purse' => 10000, 'min_squad' => 1, 'max_squad' => 5]);
        $lot = $this->callAndSell($auction, $this->alpha, 1000);

        $this->postJson(route('admin.auctions.console.price', $this->edition), ['lot_id' => $lot->id, 'amount' => 2500])
            ->assertOk()->assertJsonPath('ok', true);
        $this->assertEquals(2500, $lot->fresh()->teamPlayer->sold_amount);
        $this->assertTrue($auction->events()->where('type', 'price_changed')->where('amount', 2500)->where('note', '1000')->exists());

        // Not a step of 500 from the minimum bid, or more than the team has left: refused, the price stays.
        $this->postJson(route('admin.auctions.console.price', $this->edition), ['lot_id' => $lot->id, 'amount' => 2600])->assertStatus(422);
        $this->postJson(route('admin.auctions.console.price', $this->edition), ['lot_id' => $lot->id, 'amount' => 10500])->assertStatus(422);
        $this->assertEquals(2500, $lot->fresh()->teamPlayer->sold_amount);
    }

    public function test_random_call_can_be_limited_to_a_role_and_says_so_when_nobody_of_it_waits(): void
    {
        $this->actingAs($this->admin);
        $this->registration('Bat One', ['primary_role' => 'batter']);
        $this->registration('Bowl One', ['primary_role' => 'bowler']);
        $auction = $this->service->start($this->service->create($this->edition));

        $this->postJson(route('admin.auctions.console.random', $this->edition), ['role' => 'bowler'])->assertOk();
        $this->assertSame('Bowl One', $auction->fresh()->currentLot->playerRegistration->player->name);

        $this->postJson(route('admin.auctions.console.random', $this->edition), ['role' => 'wicket_keeper'])
            ->assertOk()->assertJsonPath('message', 'Nobody of that role is waiting. Pick another role, or any player.');
        $this->postJson(route('admin.auctions.console.random', $this->edition), ['role' => 'umpire'])->assertStatus(422);
    }

    public function test_a_walk_in_player_can_come_with_a_village_and_a_role(): void
    {
        $this->actingAs($this->admin);
        $auction = $this->liveAuction(1);

        $this->postJson(route('admin.auctions.console.walk-in', $this->edition), [
            'name' => 'Walk In', 'phone' => '9876543210', 'village' => 'Daund', 'role' => 'wicket_keeper',
        ])->assertOk();

        $registration = PlayerRegistration::query()->whereHas('player', fn ($q) => $q->where('name', 'Walk In'))->firstOrFail();
        $this->assertSame('Daund', $registration->village);
        $this->assertSame('wicket_keeper', $registration->player->primary_role);
        $this->assertTrue($auction->events()->where('type', 'walk_in')->where('player_name', 'Walk In')->exists());
    }

    // ----- A fresh start ----------------------------------------------------------------------

    public function test_a_reset_empties_the_teams_drops_the_bids_and_returns_to_setup(): void
    {
        $this->actingAs($this->admin);
        $auction = $this->liveAuction(3);
        $this->callAndSell($auction, $this->alpha, 1500);
        $onBlock = $this->service->callRandom($auction);
        $this->service->placeBid($auction, $onBlock, $this->beta, $onBlock->fresh()->version, 500);
        $this->service->startNextRound($auction);

        $this->post(route('admin.auctions.reset', $this->edition), ['confirm' => 'nope'])->assertSessionHasErrors('confirm');
        $this->assertSame(1, TeamPlayer::count());

        $this->post(route('admin.auctions.reset', $this->edition), ['confirm' => 'RESET'])->assertRedirect(route('admin.auctions.show', $this->edition));

        $auction = $auction->fresh();
        $this->assertSame(Auction::STATUS_DRAFT, $auction->status);
        $this->assertSame(1, $auction->round);
        $this->assertNull($auction->current_lot_id);
        $this->assertSame(0, TeamPlayer::count());
        $this->assertSame(0, AuctionBid::count());
        $this->assertSame(3, $auction->lots()->where('status', AuctionLot::PENDING)->count());
        $this->assertTrue($auction->events()->where('type', 'reset')->exists());
        // The history of the rehearsal stays in the log.
        $this->assertTrue($auction->events()->where('type', 'sold')->exists());
    }

    public function test_a_reset_is_refused_when_a_sold_player_has_already_played_and_for_a_completed_auction(): void
    {
        $auction = $this->liveAuction(2);
        $lot = $this->callAndSell($auction, $this->alpha, 1000);
        MatchPlayer::factory()->create(['team_player_id' => $lot->teamPlayer->id]);

        $this->fails(fn () => $this->service->resetAll($auction), 'auction');
        $this->assertSame(1, TeamPlayer::count());

        MatchPlayer::query()->delete();
        $this->service->complete($auction->fresh());
        $this->fails(fn () => $this->service->resetAll($auction->fresh()), 'auction');
    }

    // ----- Ready for the day? -----------------------------------------------------------------

    public function test_the_check_flags_what_would_embarrass_the_hall_and_passes_a_clean_setup(): void
    {
        // Not enough players for two teams that each need 12: a red line; missing villages: amber.
        $this->registration('No Village');
        $auction = $this->service->create($this->edition);

        $result = app(AuctionReadinessService::class)->check($auction);
        $levels = collect($result['items'])->pluck('level', 'title');
        $this->assertGreaterThan(0, $result['errors']);
        $this->assertTrue(collect($result['items'])->contains(fn ($i) => $i['level'] === 'error' && str_starts_with($i['title'], 'Not enough players')));
        $this->assertTrue(collect($result['items'])->contains(fn ($i) => $i['level'] === 'warn' && str_contains($i['title'], 'have no village')));
        $this->assertNotEmpty($levels);

        // A clean setup: enough players, each with a village, role and photo, both teams with a logo.
        foreach ([$this->alpha, $this->beta] as $team) {
            $team->team->update(['logo_path' => 'teams/logo.png']);
        }
        PlayerRegistration::query()->update(['village' => 'Shirur']);
        Player::query()->update(['primary_role' => 'batter', 'photo_path' => 'players/a.jpg']);
        foreach (range(1, 24) as $i) {
            $this->registration('Clean '.$i, ['primary_role' => 'batter', 'photo_path' => 'players/a.jpg'], ['village' => 'Shirur']);
        }
        $this->service->refreshPool($auction);

        $clean = app(AuctionReadinessService::class)->check($auction->fresh());
        $this->assertSame(0, $clean['errors']);
        $this->assertSame(0, $clean['warnings']);

        $this->actingAs($this->admin)->get(route('admin.auctions.show', $this->edition))
            ->assertOk()->assertSee('Ready for the auction day?')->assertSee('All good');
    }

    // ----- Downloads --------------------------------------------------------------------------

    public function test_the_result_the_bids_and_the_log_download_and_the_pdf_opens(): void
    {
        $this->actingAs($this->admin);
        $auction = $this->liveAuction(2);
        $lot = $this->callAndSell($auction, $this->alpha, 1500);
        $name = $lot->playerRegistration->player->name;

        $result = $this->get(route('admin.auctions.export', [$this->edition, 'results']));
        $result->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $result->streamedContent();
        $this->assertStringContainsString('Price (points)', $csv);
        $this->assertStringContainsString($name, $csv);
        $this->assertStringContainsString('1500', $csv);

        $this->assertStringContainsString('Bid (points)', $this->get(route('admin.auctions.export', [$this->edition, 'bids']))->streamedContent());
        $this->assertStringContainsString('was sold to', $this->get(route('admin.auctions.export', [$this->edition, 'events']))->streamedContent());
        $this->get(route('admin.auctions.export', [$this->edition, 'secrets']))->assertNotFound();

        $this->get(route('admin.auctions.results-pdf', $this->edition))->assertOk()->assertHeader('content-type', 'application/pdf');

        // Only people who run the auction may download it.
        $scorer = User::factory()->create(['role_id' => Role::firstOrCreate(['slug' => 'scorer'], ['name' => 'Scorer'])->id]);
        $this->actingAs($scorer)->get(route('admin.auctions.export', [$this->edition, 'results']))->assertForbidden();
    }
}
