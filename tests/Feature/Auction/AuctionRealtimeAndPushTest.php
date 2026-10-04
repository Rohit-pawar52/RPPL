<?php

namespace Tests\Feature\Auction;

use App\Events\AuctionUpdated;
use App\Jobs\SendNotificationJob;
use App\Models\Auction;
use App\Models\AuctionLot;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Notification;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\User;
use App\Services\Auction\AuctionService;
use App\Services\Notification\NotificationSendService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

/**
 * Step 4: the "something changed" signal to the public page (sent once per
 * committed change, never for a rolled-back one, carrying no data) and the
 * optional push notifications (start / end, and sales of at least a minimum),
 * which must never get in the way of the auction.
 */
class AuctionRealtimeAndPushTest extends TestCase
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

        config(['auction.public_cache_seconds' => 0]);

        $this->service = app(AuctionService::class);
        $this->admin = User::factory()->create(['role_id' => Role::create(['name' => 'Admin', 'slug' => 'admin'])->id]);
        $this->edition = Edition::factory()->create(['status' => 'active', 'name' => 'RPPL Test Season']);
        $this->alpha = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
        $this->beta = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
    }

    private function paid(string $name): PlayerRegistration
    {
        return PlayerRegistration::factory()->create([
            'edition_id' => $this->edition->id,
            'player_id' => Player::factory()->create(['name' => $name])->id,
            'payment_status' => 'paid',
        ]);
    }

    private function auction(array $settings = [], int $players = 2): Auction
    {
        foreach (range(1, $players) as $i) {
            $this->paid('Player '.$i);
        }

        return $this->service->create($this->edition, null, $settings);
    }

    private function url(string $name): string
    {
        return route('admin.auctions.'.$name, $this->edition);
    }

    private function console(string $name): string
    {
        return route('admin.auctions.console.'.$name, $this->edition);
    }

    // ----- The signal ---------------------------------------------------------------

    public function test_the_signal_is_a_bare_id_on_the_public_channel(): void
    {
        $event = new AuctionUpdated(7);

        $this->assertEquals([new Channel('public-auction')], $event->broadcastOn());
        $this->assertSame('auction.updated', $event->broadcastAs());
        $this->assertSame(['auction_id' => 7], $event->broadcastWith());
        $this->assertTrue($event->afterCommit);
    }

    public function test_one_action_sends_one_signal_even_though_it_saves_several_rows(): void
    {
        $auction = $this->service->start($this->auction());
        $lot = $this->service->callRandom($auction);

        Event::fake([AuctionUpdated::class]);

        // A bid saves a bid row and the lot ...
        $this->service->placeBid($auction, $lot, $this->alpha, $lot->fresh()->version);
        Event::assertDispatchedTimes(AuctionUpdated::class, 1);

        // ... and a sale saves the lot, the squad row and the auction.
        $this->service->sell($auction, $lot, $lot->fresh()->version);
        Event::assertDispatchedTimes(AuctionUpdated::class, 2);
    }

    public function test_every_kind_of_change_is_announced(): void
    {
        $auction = $this->auction(players: 3);

        Event::fake([AuctionUpdated::class]);

        $this->service->start($auction);
        $lot = $this->service->callRandom($auction);
        $this->service->hold($auction, $lot, $lot->fresh()->version);
        $this->service->pause($auction);
        $this->service->resume($auction);
        $this->service->updateSettings($auction, ['show_live_bids' => false]);
        $this->service->complete($auction);

        // start, call, hold, pause, resume, settings, complete: one each.
        Event::assertDispatchedTimes(AuctionUpdated::class, 7);
    }

    public function test_nothing_is_announced_for_a_change_that_was_rolled_back(): void
    {
        $auction = $this->service->start($this->auction());

        Event::fake([AuctionUpdated::class]);

        try {
            DB::transaction(function () use ($auction) {
                $auction->update(['round' => 5]);

                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // expected
        }

        Event::assertNotDispatched(AuctionUpdated::class);
        $this->assertSame(1, $auction->fresh()->round);
    }

    public function test_the_kept_public_picture_is_dropped_the_moment_the_auction_changes(): void
    {
        config(['auction.public_cache_seconds' => 60]);
        $auction = $this->service->start($this->auction());

        $this->getJson(route('public.auction.data'))->assertJsonPath('state.lot', null);

        $this->service->callRandom($auction);

        // Not a minute later — right away.
        $this->assertNotNull($this->getJson(route('public.auction.data'))->json('state.lot'));
    }

    // ----- Push: start and end -----------------------------------------------------------

    public function test_starting_the_auction_pushes_to_subscribers_when_that_is_on(): void
    {
        Bus::fake([SendNotificationJob::class]);
        $this->auction(['notify_start' => true]);

        $this->actingAs($this->admin)->post($this->url('start'))->assertSessionHas('success');

        Bus::assertDispatchedTimes(SendNotificationJob::class, 1);
        $notification = Notification::first();
        $this->assertSame('RPPL Player Auction', $notification->title);
        $this->assertSame('The player auction has started — follow it live.', $notification->message);
        $this->assertSame('/auction', $notification->action_url);
        $this->assertSame($this->admin->id, $notification->created_by);
    }

    public function test_nothing_is_pushed_when_the_switch_is_off(): void
    {
        Bus::fake([SendNotificationJob::class]);
        $auction = $this->auction(['notify_start' => false]);

        $this->actingAs($this->admin)->post($this->url('start'));
        $this->actingAs($this->admin)->post($this->url('complete'));

        Bus::assertNotDispatched(SendNotificationJob::class);
        $this->assertSame(0, Notification::count());
        $this->assertSame(Auction::STATUS_COMPLETED, $auction->fresh()->status);
    }

    public function test_completing_pushes_that_the_auction_is_over(): void
    {
        $auction = $this->service->start($this->auction(['notify_start' => true]));
        Bus::fake([SendNotificationJob::class]);

        $this->actingAs($this->admin)->post($this->url('complete'))->assertSessionHas('success');

        Bus::assertDispatchedTimes(SendNotificationJob::class, 1);
        $this->assertSame('The player auction is over — see who bought whom.', Notification::first()->message);
        $this->assertSame(Auction::STATUS_COMPLETED, $auction->fresh()->status);
    }

    public function test_a_refused_start_pushes_nothing(): void
    {
        Bus::fake([SendNotificationJob::class]);
        $this->auction(['notify_start' => true]);

        // Started once, so starting again is refused and must not announce again.
        $this->actingAs($this->admin)->post($this->url('start'));
        $this->actingAs($this->admin)->post($this->url('start'))->assertSessionHas('error');

        Bus::assertDispatchedTimes(SendNotificationJob::class, 1);
    }

    // ----- Push: sales -----------------------------------------------------------------------

    /**
     * Sells the player on the block through the console to $team for $amount.
     */
    private function sellThroughConsole(Auction $auction, EditionTeam $team, int $amount): void
    {
        $lot = $this->service->callRandom($auction);
        $this->service->placeBid($auction, $lot, $team, $lot->fresh()->version, $amount);

        $this->actingAs($this->admin)->postJson($this->console('sell'), [
            'lot_id' => $lot->id,
            'version' => $lot->fresh()->version,
        ])->assertOk();
    }

    public function test_a_sale_of_at_least_the_minimum_is_pushed_and_a_smaller_one_is_not(): void
    {
        $auction = $this->service->start($this->auction(['notify_sale_min' => 10000], players: 3));
        Bus::fake([SendNotificationJob::class]);

        $this->sellThroughConsole($auction, $this->alpha, 9500);
        Bus::assertNotDispatched(SendNotificationJob::class);

        $this->sellThroughConsole($auction, $this->beta, 12000);
        Bus::assertDispatchedTimes(SendNotificationJob::class, 1);

        $message = Notification::first()->message;
        $this->assertStringContainsString($this->beta->team->name, $message);
        $this->assertStringContainsString('12,000 points', $message);
        $this->assertMatchesRegularExpression('/bought Player \d for/', $message);
    }

    public function test_with_no_minimum_no_sale_is_ever_pushed(): void
    {
        $auction = $this->service->start($this->auction(['notify_sale_min' => null, 'notify_start' => false]));
        Bus::fake([SendNotificationJob::class]);

        $this->sellThroughConsole($auction, $this->alpha, 250000);

        Bus::assertNotDispatched(SendNotificationJob::class);
    }

    public function test_a_failing_notification_never_breaks_the_auction(): void
    {
        $auction = $this->service->start($this->auction(['notify_sale_min' => 500, 'notify_start' => true]));

        $this->mock(NotificationSendService::class, function ($mock) {
            $mock->shouldReceive('send')->andThrow(new RuntimeException('Firebase is down'));
        });

        $this->sellThroughConsole($auction, $this->alpha, 1000);

        $this->assertSame(1, AuctionLot::where('status', 'sold')->count());

        // Completing still completes.
        $this->actingAs($this->admin)->post($this->url('complete'))->assertSessionHas('success');
        $this->assertSame(Auction::STATUS_COMPLETED, $auction->fresh()->status);
    }

    // ----- The settings ------------------------------------------------------------------------

    public function test_the_notification_settings_are_saved_from_the_set_up_page(): void
    {
        $this->auction();
        $payload = ['team_purse' => 600000, 'min_bid' => 500, 'bid_step' => 500, 'min_squad' => 12, 'max_squad' => 15];

        $this->actingAs($this->admin)
            ->put($this->url('update'), [...$payload, 'show_live_bids' => 1, 'notify_start' => 0, 'notify_sale_min' => '25000'])
            ->assertSessionHasNoErrors();

        $auction = Auction::first();
        $this->assertFalse($auction->notify_start);
        $this->assertSame(25000, $auction->notify_sale_min);

        // Blank means "no notification per sale".
        $this->actingAs($this->admin)
            ->put($this->url('update'), [...$payload, 'notify_start' => 1, 'notify_sale_min' => ''])
            ->assertSessionHasNoErrors();
        $this->assertTrue($auction->fresh()->notify_start);
        $this->assertNull($auction->fresh()->notify_sale_min);

        $this->actingAs($this->admin)
            ->put($this->url('update'), [...$payload, 'notify_sale_min' => 0])
            ->assertSessionHasErrors('notify_sale_min');

        $this->actingAs($this->admin)->get($this->url('show'))
            ->assertOk()
            ->assertSee('Send a push notification when the auction starts and when it ends')
            ->assertSee('Also notify when a player is sold for at least');
    }
}
