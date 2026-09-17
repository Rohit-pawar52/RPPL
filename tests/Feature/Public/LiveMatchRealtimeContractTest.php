<?php

namespace Tests\Feature\Public;

use App\Models\GameMatch;
use App\Models\Innings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3.37C — the Blade contract the WebSocket-subscription JS depends
 * on. Not a JS test (this project has no frontend test runner — see
 * that phase's audit); these are the server-rendered attributes/markup
 * public-live-match.js reads to know which match channel to subscribe
 * to and where to fetch the canonical live state from. Real Echo/Reverb
 * connectivity itself was verified by a manual local smoke test.
 */
class LiveMatchRealtimeContractTest extends TestCase
{
    use RefreshDatabase;

    private function liveMatchWithInnings(): GameMatch
    {
        $match = GameMatch::factory()->create(['match_status' => 'live', 'started_at' => now()]);

        Innings::create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $match->edition_team_a_id,
            'bowling_team_id' => $match->edition_team_b_id,
            'status' => 'live',
        ]);

        return $match->fresh();
    }

    public function test_live_page_root_carries_the_match_id_as_a_plain_integer(): void
    {
        $match = $this->liveMatchWithInnings();

        $response = $this->get(route('public.matches.live', $match));

        $response->assertOk();
        $response->assertSee('data-match-id="'.$match->id.'"', false);
    }

    public function test_live_page_still_carries_the_canonical_live_data_url_and_poll_flag(): void
    {
        $match = $this->liveMatchWithInnings();

        $response = $this->get(route('public.matches.live', $match));

        $response->assertOk();
        $response->assertSee('data-live-data-url="'.route('public.matches.live-data', $match).'"', false);
        $response->assertSee('data-should-poll="1"', false);
    }

    public function test_live_page_includes_the_public_live_match_vite_entry(): void
    {
        $match = $this->liveMatchWithInnings();

        $response = $this->get(route('public.matches.live', $match));

        $response->assertOk();
        // Asserts the Vite tag references the source entry, not a
        // generated build filename (which is deliberately not asserted).
        $response->assertSee('public-live-match', false);
    }

    public function test_completed_match_still_carries_its_match_id_even_though_polling_is_off(): void
    {
        $match = $this->liveMatchWithInnings();
        $match->update(['match_status' => 'completed', 'match_result' => 'Team A won by 10 runs']);
        $match->firstInnings->update(['status' => 'completed']);

        $response = $this->get(route('public.matches.live', $match->fresh()));

        $response->assertOk();
        $response->assertSee('data-match-id="'.$match->id.'"', false);
        $response->assertSee('data-should-poll="0"', false);
    }
}
