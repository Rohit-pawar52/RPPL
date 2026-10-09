<?php

namespace Tests\Feature\Console;

use App\Models\Advertisement;
use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionContribution;
use App\Models\EditionTeam;
use App\Models\FcmToken;
use App\Models\News;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Setting;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * rppl:clear-demo-data empties the tournament data of a site that started from the demo dataset, but never the
 * accounts, settings, sponsors or push subscriptions, and a token makes sure it runs once.
 */
class ClearDemoDataCommandTest extends TestCase
{
    use RefreshDatabase;

    private function demoData(): void
    {
        $edition = Edition::factory()->create();
        EditionTeam::factory()->create(['edition_id' => $edition->id]);
        PlayerRegistration::factory()->create(['edition_id' => $edition->id]);
        EditionContribution::factory()->create(['edition_id' => $edition->id, 'contributor_id' => Contributor::factory()->create()->id]);
        News::factory()->create();
        Venue::factory()->create();
    }

    public function test_without_force_nothing_is_deleted(): void
    {
        $this->demoData();

        $this->artisan('rppl:clear-demo-data')->assertSuccessful()->expectsOutputToContain('Nothing was deleted');

        $this->assertGreaterThan(0, Edition::count());
        $this->assertGreaterThan(0, Player::count());
    }

    public function test_it_clears_the_tournament_data_and_keeps_accounts_settings_sponsors_and_subscriptions(): void
    {
        $this->demoData();
        $user = User::factory()->create();
        Setting::query()->create(['group' => 'general', 'key' => 'application_name', 'value' => 'My League', 'type' => Setting::TYPE_STRING]);
        $ad = Advertisement::factory()->create();
        $token = FcmToken::factory()->create(['player_id' => Player::query()->first()->id]);

        $this->artisan('rppl:clear-demo-data', ['--force' => true])->assertSuccessful();

        foreach (['editions', 'edition_teams', 'player_registrations', 'players', 'teams', 'contributors', 'edition_contributions', 'news', 'venues'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "$table should be empty");
        }

        $this->assertNotNull($user->fresh());
        $this->assertSame('My League', Setting::query()->where('key', 'application_name')->value('value'));
        $this->assertNotNull($ad->fresh());
        // The push subscription stays, only its link to the removed player is gone.
        $this->assertNull($token->fresh()->player_id);
        $this->assertNotNull($token->fresh());
    }

    public function test_a_token_makes_it_run_once_and_a_new_token_runs_it_again(): void
    {
        $this->demoData();

        $this->artisan('rppl:clear-demo-data', ['--force' => true, '--token' => 'go-1'])->assertSuccessful();
        $this->assertSame(0, Edition::count());

        // Real data entered afterwards survives a restart that still carries the same token.
        $edition = Edition::factory()->create(['name' => 'Real 2027']);
        $this->artisan('rppl:clear-demo-data', ['--force' => true, '--token' => 'go-1'])
            ->assertSuccessful()
            ->expectsOutputToContain('Skipped');
        $this->assertNotNull($edition->fresh());

        $this->artisan('rppl:clear-demo-data', ['--force' => true, '--token' => 'go-2'])->assertSuccessful();
        $this->assertSame(0, Edition::count());
    }
}
