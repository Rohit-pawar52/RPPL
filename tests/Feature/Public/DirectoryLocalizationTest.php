<?php

namespace Tests\Feature\Public;

use App\Models\Edition;
use App\Models\Player;
use App\Models\Rule;
use App\Models\RuleType;
use App\Models\Team;
use App\Models\Venue;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hindi/English coverage for the public directory pages (Teams,
 * Players, Venues, Editions, Videos, Rules) translated through
 * lang/{en,hi}/directory.php. Proves static UI chrome switches with the
 * rppl_locale cookie while Admin-entered content (names, rule titles)
 * renders byte-for-byte identically in both locales.
 */
class DirectoryLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private const HINDI_COMMITTEE_NOTICE = 'किसी भी गंभीर, विशेष, विवादित या इन नियमों में स्पष्ट रूप से शामिल न की गई परिस्थिति में RPPL समिति का निर्णय अंतिम होगा।';

    private const ENGLISH_COMMITTEE_NOTICE = 'the decision of the RPPL Committee shall be final';

    public function test_directory_index_pages_render_english_headings_by_default_and_hindi_with_cookie(): void
    {
        Team::factory()->create(['is_active' => true]);
        Player::factory()->create(['is_active' => true]);
        Venue::factory()->create(['is_active' => true]);
        Edition::factory()->create();
        Video::factory()->create();

        $pages = [
            // route => [english, hindi]
            'public.teams.index' => ['Search by name', 'नाम से खोजें'],
            'public.players.index' => ['Search by name', 'नाम से खोजें'],
            'public.venues.index' => ['Search by name or city', 'नाम या शहर से खोजें'],
            'public.editions.index' => ['Tournament Editions', 'टूर्नामेंट संस्करण'],
            'public.videos.index' => ['Your browser does not support embedded video.', 'आपका ब्राउज़र वीडियो चलाने का समर्थन नहीं करता।'],
        ];

        // withCookie() persists for every later request in the test, so
        // all default-locale (English) requests must run first.
        foreach ($pages as $route => [$english, $hindi]) {
            $this->get(route($route))
                ->assertOk()
                ->assertSee($english)
                ->assertDontSee($hindi);
        }

        foreach ($pages as $route => [$english, $hindi]) {
            $this->withCookie('rppl_locale', 'hi')->get(route($route))
                ->assertOk()
                ->assertSee($hindi)
                ->assertDontSee($english);
        }
    }

    public function test_edition_standings_leaderboard_and_records_labels_are_translated(): void
    {
        $edition = Edition::factory()->create();

        $this->get(route('public.editions.show', $edition))
            ->assertOk()
            ->assertSee('Top Run Scorers')
            ->assertSee('Highest individual score');

        $this->withCookie('rppl_locale', 'hi')->get(route('public.editions.show', $edition))
            ->assertOk()
            ->assertSee('भाग लेने वाली टीमें')
            ->assertSee('सबसे ज़्यादा रन')
            ->assertSee('सर्वोच्च व्यक्तिगत स्कोर')
            ->assertDontSee('Top Run Scorers')
            ->assertDontSee('Highest individual score');
    }

    public function test_rules_committee_notice_is_shown_in_hindi_and_rule_content_is_untouched(): void
    {
        $type = RuleType::factory()->create(['name' => 'Cricket Rules', 'sort_order' => 1]);
        Rule::factory()->create([
            'rule_type_id' => $type->id,
            'title' => 'Overs Per Innings',
            'content' => 'Each innings is limited to 8 overs.',
        ]);

        $english = $this->get(route('public.rules.index'));
        $english->assertOk()
            ->assertSee(self::ENGLISH_COMMITTEE_NOTICE)
            ->assertDontSee(self::HINDI_COMMITTEE_NOTICE);

        $hindi = $this->withCookie('rppl_locale', 'hi')->get(route('public.rules.index'));
        $hindi->assertOk()
            ->assertSee('नियम और विनियम')
            ->assertSee(self::HINDI_COMMITTEE_NOTICE)
            ->assertDontSee(self::ENGLISH_COMMITTEE_NOTICE);

        // Admin-entered category name, rule title and content render
        // identically in both locales.
        foreach (['Cricket Rules', 'Overs Per Innings', 'Each innings is limited to 8 overs.'] as $adminText) {
            $english->assertSee($adminText);
            $hindi->assertSee($adminText);
        }
    }

    public function test_team_player_and_venue_names_render_identically_in_both_locales(): void
    {
        $team = Team::factory()->create(['name' => 'Rajgarh Royals', 'is_active' => true]);
        $player = Player::factory()->create(['name' => 'Sandeep Pawar', 'is_active' => true]);
        $venue = Venue::factory()->create(['name' => 'Gandhi Maidan', 'is_active' => true]);

        $cases = [
            [route('public.teams.show', $team), $team->name, 'Back to teams', 'टीमों पर वापस जाएँ'],
            [route('public.players.show', $player), $player->name, 'Back to players', 'खिलाड़ियों पर वापस जाएँ'],
            [route('public.venues.show', $venue), $venue->name, 'Back to venues', 'मैदानों पर वापस जाएँ'],
        ];

        foreach ($cases as [$url, $name, $englishChrome]) {
            $this->get($url)->assertOk()->assertSee($name)->assertSee($englishChrome);
        }

        foreach ($cases as [$url, $name, $englishChrome, $hindiChrome]) {
            $this->withCookie('rppl_locale', 'hi')->get($url)
                ->assertOk()
                ->assertSee($name)
                ->assertSee($hindiChrome)
                ->assertDontSee($englishChrome);
        }
    }
}
