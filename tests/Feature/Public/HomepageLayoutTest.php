<?php

namespace Tests\Feature\Public;

use App\Models\Advertisement;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\News;
use App\Models\Photo;
use App\Models\Player;
use App\Models\Video;
use App\Services\Statistics\PlayerStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * The homepage: one scrolling match row (live/next, a sponsor card, latest
 * results), Videos / News / Photos cards, and the current season's summary.
 */
class HomepageLayoutTest extends TestCase
{
    use RefreshDatabase;

    private Edition $edition;

    private EditionTeam $teamA;

    private EditionTeam $teamB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = Edition::factory()->create(['status' => 'active', 'name' => 'RPPL Test Season']);
        $this->teamA = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
        $this->teamB = EditionTeam::factory()->create(['edition_id' => $this->edition->id]);
    }

    private function match(int $number, string $status, $scheduledAt, array $attributes = []): GameMatch
    {
        return GameMatch::factory()->create([
            'edition_id' => $this->edition->id,
            'edition_team_a_id' => $this->teamA->id,
            'edition_team_b_id' => $this->teamB->id,
            'match_number' => $number,
            'match_status' => $status,
            'scheduled_at' => $scheduledAt,
            ...$attributes,
        ]);
    }

    /**
     * Stands in for the real statistics so the summary can be checked
     * without scoring deliveries (the service has its own tests).
     *
     * @param  array<string, list<array<string, mixed>>>  $boards
     */
    private function fakeHighlights(array $boards): void
    {
        $empty = array_fill_keys(PlayerStatisticsService::HIGHLIGHT_BOARDS, []);

        $this->mock(PlayerStatisticsService::class, function (MockInterface $mock) use ($boards, $empty) {
            $mock->shouldReceive('getEditionHighlights')->andReturn([...$empty, ...$boards]);
        });
    }

    public function test_the_edition_strip_is_gone_and_the_edition_stays_reachable_from_the_navbar(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertDontSee('Edition details')
            ->assertSee(route('public.editions.show', $this->edition), false);
    }

    public function test_match_row_shows_live_and_next_matches_then_the_sponsor_card_then_latest_results(): void
    {
        $this->match(21, 'live', now()->subHour());
        $this->match(22, 'scheduled', now()->addDay());
        $this->match(23, 'scheduled', now()->addDays(2)); // beyond the two upcoming slots
        foreach ([31, 32, 33, 34] as $i => $number) {
            $this->match($number, 'completed', now()->subDays(10 - $i), ['match_result' => 'Result of '.$number]);
        }
        Advertisement::factory()->create(['title' => 'Sharma Tractors']);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSeeInOrder(['Match 21', 'Match 22', 'Sharma Tractors', 'Match 34', 'Match 33', 'Match 32', 'All matches'])
            ->assertDontSee('Match 23')
            ->assertDontSee('Match 31');
    }

    public function test_match_row_works_without_a_sponsor_or_any_recent_result(): void
    {
        $this->match(22, 'scheduled', now()->addDay());

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Match 22')
            ->assertSee('All matches')
            ->assertDontSee('data-ad=', false);
    }

    public function test_two_normal_slots_never_repeat_a_sponsor_and_the_main_banner_comes_first(): void
    {
        $this->match(22, 'scheduled', now()->addDay());
        Advertisement::factory()->main()->create(['title' => 'Main Title Sponsor']);
        Advertisement::factory()->create(['title' => 'Normal One']);
        Advertisement::factory()->create(['title' => 'Normal Two']);

        $html = $this->get(route('public.home'))->assertOk()->getContent();

        // Main banner, then the row (with one Normal card), then another Normal banner above the summary.
        $this->assertSame(1, substr_count($html, 'data-ad="main"'));
        $this->assertSame(2, substr_count($html, 'data-ad="normal"'));
        $this->assertSame(1, substr_count($html, 'alt="Normal One"'));
        $this->assertSame(1, substr_count($html, 'alt="Normal Two"'));
        $this->assertLessThan(strpos($html, 'Match 22'), strpos($html, 'Main Title Sponsor'));
        $this->assertLessThan(strpos($html, 'Season summary'), strrpos($html, 'data-ad="normal"'));
    }

    public function test_a_single_normal_sponsor_is_shown_once_not_twice(): void
    {
        $this->match(22, 'scheduled', now()->addDay());
        Advertisement::factory()->create(['title' => 'Only Sponsor']);

        $html = $this->get(route('public.home'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-ad="normal"'));
    }

    public function test_videos_news_and_photos_show_three_each_with_links_to_their_pages(): void
    {
        foreach ([4, 2, 3, 1] as $priority) {
            Video::factory()->create(['title' => 'Video P'.$priority, 'priority' => $priority]);
            Photo::factory()->create(['title' => 'Photo P'.$priority, 'priority' => $priority]);
            News::factory()->create(['title' => 'News P'.$priority, 'priority' => $priority]);
        }
        Video::factory()->inactive()->create(['title' => 'Hidden Video', 'priority' => 1]);
        News::factory()->scheduled()->create(['title' => 'Future News', 'priority' => 1]);

        $response = $this->get(route('public.home'))->assertOk();

        $response
            ->assertSeeInOrder(['Video P1', 'Video P2', 'Video P3'])
            ->assertSeeInOrder(['News P1', 'News P2', 'News P3'])
            ->assertSeeInOrder(['Photo P1', 'Photo P2', 'Photo P3'])
            ->assertDontSee('Video P4')
            ->assertDontSee('News P4')
            ->assertDontSee('Photo P4')
            ->assertDontSee('Hidden Video')
            ->assertDontSee('Future News')
            ->assertSee(route('public.videos.index'), false)
            ->assertSee(route('public.photos.index'), false)
            ->assertSee(route('public.news.index'), false);

        // A news item opens its own article; none of this embeds a player.
        $this->assertStringContainsString(route('public.news.show', News::firstWhere('title', 'News P1')->slug), $response->getContent());
        $this->assertStringNotContainsString('<video', $response->getContent());
    }

    public function test_a_section_with_nothing_published_is_left_out(): void
    {
        News::factory()->create(['title' => 'Lonely News']);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Lonely News')
            ->assertSee('data-home-section="news"', false)
            ->assertDontSee('data-home-section="videos"', false)
            ->assertDontSee('data-home-section="photos"', false)
            ->assertDontSee('md:grid-cols-3', false);
    }

    public function test_season_summary_lists_the_boards_that_have_data_with_view_more_links(): void
    {
        $this->fakeHighlights([
            'runs' => [['player' => Player::factory()->make(['name' => 'Top Run Getter']), 'value' => 210]],
            'wickets' => [['player' => Player::factory()->make(['name' => 'Top Wicket Taker']), 'value' => 9]],
            'highest-score' => [['player' => Player::factory()->make(['name' => 'Big Hitter']), 'value' => 88, 'not_out' => true]],
        ]);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSeeInOrder(['RPPL Test Season', 'Season summary', 'Points table', 'Most runs', 'Top Run Getter', 'Most wickets', 'Top Wicket Taker', 'Highest score', 'Big Hitter', '88*'])
            ->assertSee(route('public.editions.stats', [$this->edition, 'runs']), false)
            ->assertSee(route('public.editions.stats', [$this->edition, 'wickets']), false)
            ->assertSee(route('public.editions.stats', [$this->edition, 'highest-score']), false)
            // Boards nobody is on yet are left out.
            ->assertDontSee('Most 50s')
            ->assertDontSee('Most sixes')
            ->assertDontSee('Player stats appear here');
    }

    public function test_season_summary_says_so_when_there_are_no_stats_yet(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Season summary')
            ->assertSee('Player stats appear here once matches are scored.')
            ->assertDontSee('View more');
    }

    public function test_the_page_still_renders_without_any_edition(): void
    {
        Edition::query()->delete();

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('No tournament editions available yet.')
            ->assertDontSee('Season summary');
    }

    public function test_the_stats_page_lists_the_top_twenty_of_a_board(): void
    {
        $rows = collect(range(1, 20))->map(fn ($i) => [
            'player' => Player::factory()->make(['name' => 'Scorer '.$i]),
            'value' => 300 - $i, 'innings' => 5, 'balls' => 100, 'strike_rate' => 120.5, 'fours' => 3, 'sixes' => 1,
        ])->all();

        $this->mock(PlayerStatisticsService::class, function (MockInterface $mock) use ($rows) {
            $mock->shouldReceive('getEditionHighlights')
                ->once()
                ->withArgs(fn ($edition, $limit) => $edition->is($this->edition) && $limit === 20)
                ->andReturn([...array_fill_keys(PlayerStatisticsService::HIGHLIGHT_BOARDS, []), 'runs' => $rows]);
        });

        $this->get(route('public.editions.stats', [$this->edition, 'runs']))
            ->assertOk()
            ->assertSee('Most runs')
            ->assertSee('Top 20 players of RPPL Test Season')
            ->assertSeeInOrder(['Scorer 1', 'Scorer 2', 'Scorer 20'])
            ->assertSee('299')
            // The other boards are one tap away.
            ->assertSee(route('public.editions.stats', [$this->edition, 'wickets']), false);
    }

    public function test_the_stats_page_handles_an_empty_board_and_rejects_unknown_boards(): void
    {
        foreach (PlayerStatisticsService::HIGHLIGHT_BOARDS as $board) {
            $this->get(route('public.editions.stats', [$this->edition, $board]))
                ->assertOk()
                ->assertSee('Nothing to show yet');
        }

        $this->get('/editions/'.$this->edition->id.'/stats/not-a-board')->assertNotFound();
    }
}
