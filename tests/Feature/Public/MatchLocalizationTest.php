<?php

namespace Tests\Feature\Public;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\TeamPlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bilingual localization — match-facing public pages (homepage, matches
 * list, per-match tabs, Match Info, Squads, Scorecard chrome) via
 * lang/{en,hi}/matches.php. A representative sample per page, not every
 * string: proves the labels switch with the rppl_locale cookie, stay
 * English by default, and that DB-sourced data (team names, scores,
 * result text) is rendered byte-for-byte identically in both locales.
 */
class MatchLocalizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: GameMatch, 1: EditionTeam, 2: EditionTeam}
     */
    private function completedMatch(): array
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        $match = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'match_status' => 'completed',
            'result_type' => 'won',
            'winner_team_id' => $teamA->id,
            'match_result' => "{$teamA->team->name} won by 10 runs",
            'toss_winner_team_id' => $teamA->id,
            'toss_decision' => 'bat',
        ]);

        Innings::create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $teamA->id,
            'bowling_team_id' => $teamB->id,
            'status' => 'completed',
            'total_runs' => 87,
            'total_wickets' => 4,
        ]);

        return [$match->fresh(), $teamA, $teamB];
    }

    private function hindi(): static
    {
        return $this->withCookie('rppl_locale', 'hi');
    }

    public function test_homepage_section_labels_are_english_by_default_and_hindi_with_the_cookie(): void
    {
        $this->completedMatch();

        $english = $this->get(route('public.home'));
        $english->assertOk();
        $english->assertSee('Most Runs');
        $english->assertSee('Recent Results');
        $english->assertSee('Top Performers');

        $hindi = $this->hindi()->get(route('public.home'));
        $hindi->assertOk();
        $hindi->assertSee('सबसे ज़्यादा रन');
        $hindi->assertSee('हाल के नतीजे');
        $hindi->assertSee('टॉप खिलाड़ी');
        $hindi->assertDontSee('Most Runs');
        $hindi->assertDontSee('Recent Results');
    }

    public function test_matches_list_labels_translate(): void
    {
        $this->completedMatch();

        $hindi = $this->hindi()->get(route('public.matches.index'));

        $hindi->assertOk();
        $hindi->assertSee('सभी संस्करण'); // "All editions"
        $hindi->assertSee('नतीजे'); // "Results"
        $hindi->assertDontSee('All editions');
    }

    public function test_match_tabs_and_match_info_labels_translate_including_the_toss_sentence(): void
    {
        [$match, $teamA] = $this->completedMatch();

        $english = $this->get(route('public.matches.show', $match));
        $english->assertSeeInOrder(['Live', 'Scorecard', 'Squads', 'Match Info']);
        $english->assertSee("{$teamA->team->name} won the toss and chose to bat");

        $hindi = $this->hindi()->get(route('public.matches.show', $match));
        $hindi->assertOk();
        $hindi->assertSeeInOrder(['लाइव', 'स्कोरकार्ड', 'स्क्वॉड', 'मैच जानकारी']);
        $hindi->assertSee("{$teamA->team->name} ने टॉस जीतकर पहले बल्लेबाज़ी चुनी");
        $hindi->assertDontSee('won the toss');
    }

    public function test_squads_role_and_captain_markers_translate(): void
    {
        [$match, $teamA] = $this->completedMatch();

        $teamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $teamA->id, 'jersey_number' => 7, 'role' => 'batter']);
        MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $teamPlayer->id, 'is_captain' => true]);

        $hindi = $this->hindi()->get(route('public.matches.squads', $match));

        $hindi->assertOk();
        $hindi->assertSee('प्लेइंग XI');
        $hindi->assertSee('(कप्तान)');
        $hindi->assertSee('बल्लेबाज़');
        $hindi->assertSee($teamPlayer->playerRegistration->player->name);
        $hindi->assertDontSee('Playing XI not announced yet.');
    }

    public function test_scorecard_page_chrome_translates_while_the_admin_shared_innings_table_is_untouched(): void
    {
        [$match] = $this->completedMatch();

        $hindi = $this->hindi()->get(route('public.matches.scorecard', $match));

        $hindi->assertOk();
        $hindi->assertSee('स्कोरकार्ड');
        $hindi->assertSee('सभी मैच'); // back link, public.common.all_matches
        // shared/scorecard/_innings.blade.php is also the ADMIN scorecard
        // partial and is deliberately not localized — its column headers
        // stay English on the public Hindi page too.
        $hindi->assertSee('Econ');
    }

    public function test_dynamic_match_data_renders_identically_in_both_locales(): void
    {
        [$match, $teamA, $teamB] = $this->completedMatch();

        foreach ([$this->get(route('public.matches.show', $match)), $this->hindi()->get(route('public.matches.show', $match))] as $response) {
            $response->assertOk();
            $response->assertSee($teamA->team->name);
            $response->assertSee($teamB->team->name);
            $response->assertSee('87/4');
            $response->assertSee("{$teamA->team->name} won by 10 runs"); // match_result is DB text, never translated
        }
    }
}
