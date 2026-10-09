<?php

namespace Tests\Feature\Admin\I18n;

use App\Models\Auction;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\User;
use App\Services\Auction\AuctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The match-day screens in Hindi: the registrations queue, the match page, the scoring console and the auction
 * console draw their labels in Devanagari, the English pages are untouched, and a flash message, a refused auction
 * action and a validation message (with the translated field name) come out in Hindi too.
 */
class OpsHindiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $locale): User
    {
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id, 'locale' => $locale]);
    }

    public function test_the_registrations_queue_and_the_registration_page_are_hindi(): void
    {
        $admin = $this->admin('hi');
        $registration = PlayerRegistration::factory()->create(['payment_status' => 'pending'])->assignRegistrationNumber();

        $this->actingAs($admin)->get(route('admin.player-registrations.index'))
            ->assertOk()
            ->assertSee('फ़ैसले का इंतज़ार')
            ->assertSee('भुगतान हुआ')
            ->assertSee('भुगतान नहीं हुआ')
            ->assertDontSee('Mark paid')
            ->assertDontSee('Waiting for a decision');

        $this->actingAs($admin)->get(route('admin.player-registrations.show', $registration))
            ->assertOk()
            ->assertSee('भुगतान का सबूत')
            ->assertSee('आपका फ़ैसला');
    }

    public function test_marking_paid_flashes_in_hindi_and_english_stays_english(): void
    {
        $registration = PlayerRegistration::factory()->create(['payment_status' => 'pending'])->assignRegistrationNumber();

        $this->actingAs($this->admin('hi'))
            ->post(route('admin.player-registrations.mark-paid', $registration))
            ->assertSessionHas('info', fn (string $message) => str_contains($message, $registration->registration_number.' भुगतान हुआ दर्ज किया गया।')
                && str_contains($message, 'अब कोई बाकी पंजीकरण नहीं है'));

        $other = PlayerRegistration::factory()->create(['payment_status' => 'pending'])->assignRegistrationNumber();
        $this->actingAs($this->admin('en'))->get(route('admin.player-registrations.index'))
            ->assertOk()->assertSee('Waiting for a decision')->assertDontSee('फ़ैसले का इंतज़ार');
        $this->actingAs($this->admin('en'))
            ->post(route('admin.player-registrations.mark-paid', $other))
            ->assertSessionHas('info', fn (string $message) => str_contains($message, 'marked paid. No more pending registrations'));
    }

    public function test_the_match_page_and_the_scoring_console_are_hindi(): void
    {
        $admin = $this->admin('hi');
        $match = GameMatch::factory()->create(['match_status' => 'live', 'started_at' => now()]);
        $innings = Innings::create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $match->edition_team_a_id,
            'bowling_team_id' => $match->edition_team_b_id,
            'status' => 'live',
        ]);

        $this->actingAs($admin)->get(route('admin.matches.show', $match))
            ->assertOk()->assertSee('मैच का विवरण')->assertSee('अगला कदम');

        $this->actingAs($admin)->get(route('admin.matches.innings.score', [$match, $innings]))
            ->assertOk()
            ->assertSee('स्कोरबोर्ड')
            ->assertSee('पारी शुरू करें')
            // the words the keypad and the live panels use are handed to the script in Hindi
            ->assertSee(trim(json_encode('वाइड'), '"'), false)
            ->assertSee(trim(json_encode('कौन आउट?'), '"'), false);
    }

    public function test_the_auction_console_and_its_refusals_are_hindi(): void
    {
        $admin = $this->admin('hi');
        $edition = Edition::factory()->create(['status' => 'active']);
        EditionTeam::factory()->count(2)->create(['edition_id' => $edition->id]);
        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'paid']);
        $service = app(AuctionService::class);
        $service->start($service->create($edition));

        $this->actingAs($admin)->get(route('admin.auctions.console', $edition))
            ->assertOk()
            ->assertSee('नीलामी कंसोल')
            ->assertSee('यह रकम लगाएँ');

        $lot = $this->actingAs($admin)->postJson(route('admin.auctions.console.random', $edition))->assertOk()->json('state.lot');

        // Undo with no bid on the player is refused, and the refusal reads in Hindi.
        $this->actingAs($admin)
            ->postJson(route('admin.auctions.console.undo', $edition), ['lot_id' => $lot['id'], 'version' => $lot['version']])
            ->assertStatus(422)
            ->assertJsonPath('message', 'वापस करने के लिए कोई बोली नहीं है।');

        $this->actingAs($admin)->get(route('admin.auctions.show', $edition))
            ->assertOk()->assertSee('नियम और टीम पर्स');
    }

    public function test_a_validation_message_names_the_field_in_hindi(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $this->assertNull(Auction::where('edition_id', $edition->id)->first());

        $response = $this->actingAs($this->admin('hi'))->from(route('admin.auctions.show', $edition))
            ->post(route('admin.auctions.store', $edition), ['team_purse' => '', 'min_bid' => 500, 'bid_step' => 500, 'min_squad' => 12, 'max_squad' => 15]);

        $response->assertSessionHasErrors('team_purse');
        $this->assertStringContainsString('हर टीम का पर्स', session('errors')->first('team_purse'));
        $this->assertDoesNotMatchRegularExpression('/team purse/i', session('errors')->first('team_purse'));
    }
}
