<?php

namespace Tests\Feature\Auction;

use App\Models\Auction;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The per-season auction set-up page: creating the auction with its rules,
 * changing them (and a team's own purse), updating the pool and moving the
 * auction between draft, live, paused and completed.
 */
class AuctionSetupPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Edition $edition;

    private EditionTeam $alpha;

    private EditionTeam $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role_id' => Role::create(['name' => 'Admin', 'slug' => 'admin'])->id]);
        $this->edition = Edition::factory()->create(['status' => 'active']);
        $this->alpha = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
        $this->beta = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
    }

    private function paidPlayers(int $count): void
    {
        PlayerRegistration::factory()->count($count)->create(['edition_id' => $this->edition->id, 'payment_status' => 'paid']);
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(array $overrides = []): array
    {
        return [
            'team_purse' => 600000,
            'min_bid' => 500,
            'bid_step' => 500,
            'min_squad' => 12,
            'max_squad' => 15,
            'show_live_bids' => 1,
            ...$overrides,
        ];
    }

    private function url(string $name): string
    {
        return route('admin.auctions.'.$name, $this->edition);
    }

    public function test_before_an_auction_exists_the_page_shows_the_defaults_and_how_many_paid_players_are_ready(): void
    {
        $this->paidPlayers(3);
        PlayerRegistration::factory()->create(['edition_id' => $this->edition->id, 'payment_status' => 'pending']);

        $this->actingAs($this->admin)->get($this->url('show'))
            ->assertOk()
            ->assertSee('Create auction')
            ->assertSee('value="600000"', false)
            ->assertSee('value="500"', false)
            ->assertSee('Paid players not in a team')
            ->assertSeeInOrder(['Teams in this season', '2', 'Paid players not in a team', '3']);
    }

    public function test_creating_the_auction_saves_the_rules_and_builds_the_pool(): void
    {
        $this->paidPlayers(4);

        $this->actingAs($this->admin)
            ->post($this->url('store'), $this->settings(['team_purse' => 800000, 'min_squad' => 10, 'max_squad' => 14, 'show_live_bids' => 0]))
            ->assertRedirect($this->url('show'))
            ->assertSessionHas('success');

        $auction = Auction::firstWhere('edition_id', $this->edition->id);
        $this->assertSame(Auction::STATUS_DRAFT, $auction->status);
        $this->assertSame([800000, 10, 14, false], [$auction->team_purse, $auction->min_squad, $auction->max_squad, $auction->show_live_bids]);
        $this->assertSame(4, $auction->lots()->count());
    }

    public function test_the_rules_are_validated(): void
    {
        $this->actingAs($this->admin)->post($this->url('store'), $this->settings(['min_squad' => 15, 'max_squad' => 12]))
            ->assertSessionHasErrors('max_squad');
        $this->actingAs($this->admin)->post($this->url('store'), $this->settings(['team_purse' => 0, 'bid_step' => 'many']))
            ->assertSessionHasErrors(['team_purse', 'bid_step']);

        $this->assertSame(0, Auction::count());
    }

    public function test_a_second_auction_for_a_season_is_refused_with_a_message(): void
    {
        $this->actingAs($this->admin)->post($this->url('store'), $this->settings())->assertSessionHas('success');

        $this->actingAs($this->admin)->post($this->url('store'), $this->settings())
            ->assertRedirect($this->url('show'))
            ->assertSessionHas('error', 'This season already has an auction.');
        $this->assertSame(1, Auction::count());
    }

    public function test_settings_and_a_teams_own_purse_can_be_changed_at_any_time_even_while_live(): void
    {
        $this->paidPlayers(2);
        $auction = Auction::factory()->live()->create(['edition_id' => $this->edition->id]);

        $this->actingAs($this->admin)
            ->put($this->url('update'), $this->settings([
                'team_purse' => 700000,
                'bid_step' => 1000,
                'min_bid' => 1000,
                'team_purses' => [$this->alpha->id => 900000, $this->beta->id => ''],
            ]))
            ->assertRedirect($this->url('show'))
            ->assertSessionHas('success');

        $auction->refresh();
        $this->assertSame([700000, 1000, 1000], [$auction->team_purse, $auction->min_bid, $auction->bid_step]);
        $this->assertSame(900000, $this->alpha->fresh()->auction_purse);
        $this->assertNull($this->beta->fresh()->auction_purse);

        $this->actingAs($this->admin)->get($this->url('show'))->assertSee('9,00,000', false)->assertSee('7,00,000', false);
    }

    public function test_a_completed_auction_can_no_longer_be_changed(): void
    {
        $auction = Auction::factory()->create(['edition_id' => $this->edition->id, 'status' => Auction::STATUS_COMPLETED]);

        $this->actingAs($this->admin)->put($this->url('update'), $this->settings(['team_purse' => 1]))
            ->assertSessionHas('error');
        $this->assertSame(600000, $auction->fresh()->team_purse);

        $this->actingAs($this->admin)->post($this->url('refresh-pool'))->assertSessionHas('error');
    }

    public function test_updating_the_pool_reports_what_changed(): void
    {
        $this->paidPlayers(1);
        $this->actingAs($this->admin)->post($this->url('store'), $this->settings());
        PlayerRegistration::withoutEvents(fn () => $this->paidPlayers(2));

        $this->actingAs($this->admin)->post($this->url('refresh-pool'))
            ->assertRedirect($this->url('show'))
            ->assertSessionHas('success', 'Pool updated: 2 added, 0 removed.');

        $this->assertSame(3, Auction::first()->lots()->count());
    }

    public function test_the_auction_moves_through_start_pause_resume_and_complete(): void
    {
        $this->paidPlayers(2);
        $this->actingAs($this->admin)->post($this->url('store'), $this->settings(['min_squad' => 1, 'max_squad' => 3]));
        $auction = Auction::first();

        $this->actingAs($this->admin)->post($this->url('start'))->assertSessionHas('success', 'The auction is live.');
        $this->assertSame(Auction::STATUS_LIVE, $auction->fresh()->status);

        $this->actingAs($this->admin)->post($this->url('pause'))->assertSessionHas('success');
        $this->assertSame(Auction::STATUS_PAUSED, $auction->fresh()->status);
        $this->actingAs($this->admin)->get($this->url('show'))->assertSee('Resume');

        $this->actingAs($this->admin)->post($this->url('resume'))->assertSessionHas('success');
        $this->assertSame(Auction::STATUS_LIVE, $auction->fresh()->status);

        $response = $this->actingAs($this->admin)->post($this->url('complete'));
        $response->assertSessionHas('success');
        $this->assertStringContainsString('2 players were left unsold', session('success'));
        $this->assertStringContainsString('Still short of the minimum squad', session('success'));
        $this->assertSame(Auction::STATUS_COMPLETED, $auction->fresh()->status);
    }

    public function test_starting_with_no_players_is_refused_with_a_message_and_a_started_auction_cannot_start_again(): void
    {
        $this->actingAs($this->admin)->post($this->url('store'), $this->settings());

        $this->actingAs($this->admin)->post($this->url('start'))->assertSessionHas('error');
        $this->assertSame(Auction::STATUS_DRAFT, Auction::first()->status);

        $this->paidPlayers(1);
        $this->actingAs($this->admin)->post($this->url('refresh-pool'));
        $this->actingAs($this->admin)->post($this->url('start'))->assertSessionHas('success');
        $this->actingAs($this->admin)->post($this->url('start'))->assertSessionHas('error');
    }

    public function test_actions_on_a_season_without_an_auction_are_not_found(): void
    {
        foreach (['update' => 'put', 'refresh-pool' => 'post', 'start' => 'post', 'pause' => 'post', 'resume' => 'post', 'complete' => 'post'] as $name => $method) {
            $this->actingAs($this->admin)->{$method}($this->url($name), $this->settings())->assertNotFound();
        }
    }

    public function test_the_seasons_list_shows_each_auction_state(): void
    {
        $other = Edition::factory()->create(['name' => 'Old Season', 'year' => 2020, 'status' => 'completed']);
        Auction::factory()->live()->create(['edition_id' => $this->edition->id]);

        $this->actingAs($this->admin)->get(route('admin.auctions.index'))
            ->assertOk()
            ->assertSee($this->edition->name)
            ->assertSee('live')
            ->assertSee($other->name)
            ->assertSee('Not set up');
    }
}
