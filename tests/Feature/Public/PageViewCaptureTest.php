<?php

namespace Tests\Feature\Public;

use App\Models\Edition;
use App\Models\GameMatch;
use App\Models\PageView;
use App\Models\Player;
use App\Models\Role;
use App\Models\User;
use App\Services\Analytics\PageViewRecorder;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Analytics V1 capture: only a successful public GET of the match, edition
 * or player detail page is recorded (one visitor + subject per 30 minutes),
 * never polling, lists, redirects, staff or bots.
 */
class PageViewCaptureTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->flush();
    }

    /**
     * A GET as a given visitor (their rppl_vid cookie) with a normal browser UA.
     */
    private function visit(string $url, ?string $visitorId = null, string $userAgent = self::BROWSER)
    {
        $request = $this->withHeader('User-Agent', $userAgent);

        if ($visitorId !== null) {
            $request = $request->withCookie(PageViewRecorder::VISITOR_COOKIE, $visitorId);
        }

        return $request->get($url);
    }

    private function newVisitor(): string
    {
        return (string) Str::uuid();
    }

    // ----- Basic capture -----

    public function test_a_match_detail_view_is_recorded_as_match_view(): void
    {
        $match = GameMatch::factory()->create();

        $this->visit(route('public.matches.show', $match))->assertOk();

        $view = PageView::sole();
        $this->assertSame(PageView::MATCH_VIEW, $view->event_type);
        $this->assertSame($match->id, $view->subject_id);
    }

    public function test_an_edition_detail_view_is_recorded_as_edition_view(): void
    {
        $edition = Edition::factory()->create();

        $this->visit(route('public.editions.show', $edition))->assertOk();

        $view = PageView::sole();
        $this->assertSame(PageView::EDITION_VIEW, $view->event_type);
        $this->assertSame($edition->id, $view->subject_id);
    }

    public function test_a_player_profile_view_is_recorded_as_player_view(): void
    {
        $player = Player::factory()->create();

        $this->visit(route('public.players.show', $player))->assertOk();

        $view = PageView::sole();
        $this->assertSame(PageView::PLAYER_VIEW, $view->event_type);
        $this->assertSame($player->id, $view->subject_id);
    }

    // ----- Visitor identity -----

    public function test_a_new_visitor_receives_the_visitor_cookie_and_only_its_hash_is_stored(): void
    {
        $match = GameMatch::factory()->create();

        $response = $this->visit(route('public.matches.show', $match));

        $cookie = $response->getCookie(PageViewRecorder::VISITOR_COOKIE);
        $this->assertNotNull($cookie);
        $this->assertTrue(Str::isUuid($cookie->getValue()));
        // Roughly two years, first-party and not readable from JavaScript.
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertEqualsWithDelta(time() + 60 * 60 * 24 * 365 * 2, $cookie->getExpiresTime(), 120);

        $stored = PageView::sole()->visitor_hash;
        $this->assertNotSame($cookie->getValue(), $stored);
        $this->assertSame(64, strlen($stored));
        $this->assertSame(hash_hmac('sha256', $cookie->getValue(), (string) config('app.key')), $stored);
        $this->assertDatabaseMissing('page_views', ['visitor_hash' => $cookie->getValue()]);
    }

    public function test_a_returning_visitor_keeps_their_cookie_and_is_not_issued_a_new_one(): void
    {
        $match = GameMatch::factory()->create();

        $response = $this->visit(route('public.matches.show', $match), $this->newVisitor());

        $this->assertNull($response->getCookie(PageViewRecorder::VISITOR_COOKIE));
    }

    public function test_the_language_cookie_behaviour_is_unchanged(): void
    {
        $match = GameMatch::factory()->create();

        $this->withCookie('rppl_locale', 'hi')
            ->withHeader('User-Agent', self::BROWSER)
            ->get(route('public.matches.show', $match))
            ->assertOk()
            ->assertSee('मैच');
    }

    // ----- Deduplication (30 minutes) -----

    public function test_the_same_visitor_and_subject_within_thirty_minutes_is_counted_once(): void
    {
        $match = GameMatch::factory()->create();
        $visitor = $this->newVisitor();

        $this->travelTo(Carbon::parse('2026-03-10 10:00:00', 'UTC'));
        $this->visit(route('public.matches.show', $match), $visitor);
        $this->travelTo(Carbon::parse('2026-03-10 10:10:00', 'UTC'));
        $this->visit(route('public.matches.show', $match), $visitor);
        $this->travelTo(Carbon::parse('2026-03-10 10:25:00', 'UTC'));
        $this->visit(route('public.matches.show', $match), $visitor);

        $this->assertSame(1, PageView::count());
    }

    public function test_the_same_visitor_and_subject_after_thirty_minutes_is_counted_again(): void
    {
        $match = GameMatch::factory()->create();
        $visitor = $this->newVisitor();

        $this->travelTo(Carbon::parse('2026-03-10 10:00:00', 'UTC'));
        $this->visit(route('public.matches.show', $match), $visitor);
        $this->travelTo(Carbon::parse('2026-03-10 10:10:00', 'UTC'));
        $this->visit(route('public.matches.show', $match), $visitor);
        $this->travelTo(Carbon::parse('2026-03-10 10:35:00', 'UTC'));
        $this->visit(route('public.matches.show', $match), $visitor);

        $this->assertSame(2, PageView::count());
    }

    public function test_one_visitor_viewing_two_different_matches_creates_two_views(): void
    {
        $visitor = $this->newVisitor();
        $first = GameMatch::factory()->create();
        $second = GameMatch::factory()->create();

        $this->visit(route('public.matches.show', $first), $visitor);
        $this->visit(route('public.matches.show', $second), $visitor);

        $this->assertEqualsCanonicalizing([$first->id, $second->id], PageView::pluck('subject_id')->all());
    }

    public function test_two_different_visitors_viewing_the_same_match_create_two_rows(): void
    {
        $match = GameMatch::factory()->create();

        $this->visit(route('public.matches.show', $match), $this->newVisitor());
        $this->visit(route('public.matches.show', $match), $this->newVisitor());

        $this->assertSame(2, PageView::count());
        $this->assertSame(2, PageView::distinct()->count('visitor_hash'));
    }

    public function test_the_player_query_string_and_pagination_do_not_create_extra_views(): void
    {
        $player = Player::factory()->create();
        $visitor = $this->newVisitor();
        $url = route('public.players.show', $player);

        $this->visit($url, $visitor)->assertOk();
        $this->visit($url.'?edition_id=2026', $visitor)->assertOk();
        $this->visit($url.'?edition_id=2026&page=2', $visitor)->assertOk();

        $this->assertSame(1, PageView::count());
        $this->assertSame($player->id, PageView::sole()->subject_id);
    }

    // ----- Things that must never be counted -----

    public function test_lists_and_the_homepage_are_not_counted(): void
    {
        GameMatch::factory()->create();

        foreach ([route('public.home'), route('public.matches.index'), route('public.editions.index'), route('public.players.index')] as $url) {
            $this->visit($url, $this->newVisitor())->assertOk();
        }

        $this->assertSame(0, PageView::count());
    }

    public function test_the_other_match_tabs_and_the_live_data_endpoint_are_not_counted(): void
    {
        $match = GameMatch::factory()->create();
        $visitor = $this->newVisitor();

        $this->visit(route('public.matches.squads', $match), $visitor)->assertOk();
        $this->visit(route('public.matches.live-data', $match), $visitor)->assertOk();
        // Polling the way the live page's JavaScript does.
        foreach (range(1, 5) as $ignored) {
            $this->withHeaders(['User-Agent' => self::BROWSER, 'Accept' => 'application/json'])
                ->withCookie(PageViewRecorder::VISITOR_COOKIE, $visitor)
                ->get(route('public.matches.live-data', $match))
                ->assertOk();
        }

        $this->assertSame(0, PageView::count());
    }

    public function test_a_missing_subject_is_a_404_and_is_not_counted(): void
    {
        $this->visit(route('public.matches.show', 9999), $this->newVisitor())->assertNotFound();
        $this->visit(route('public.editions.show', 9999), $this->newVisitor())->assertNotFound();
        $this->visit(route('public.players.show', 9999), $this->newVisitor())->assertNotFound();

        $this->assertSame(0, PageView::count());
    }

    public function test_a_redirect_is_not_counted(): void
    {
        // The live page redirects to Match Info while coverage isn't
        // available — that redirect must not count as a view of anything.
        $match = GameMatch::factory()->create(['match_status' => 'scheduled']);

        $this->visit(route('public.matches.live', $match), $this->newVisitor())
            ->assertRedirect(route('public.matches.show', $match));

        $this->assertSame(0, PageView::count());
    }

    public function test_head_and_post_requests_are_not_counted(): void
    {
        $match = GameMatch::factory()->create();

        $this->withHeader('User-Agent', self::BROWSER)->call('HEAD', route('public.matches.show', $match));
        $this->withHeader('User-Agent', self::BROWSER)->post(route('public.matches.show', $match));

        $this->assertSame(0, PageView::count());
    }

    public function test_ajax_and_prefetch_requests_are_not_counted(): void
    {
        $match = GameMatch::factory()->create();

        $this->withHeaders(['User-Agent' => self::BROWSER, 'X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('public.matches.show', $match));
        $this->withHeaders(['User-Agent' => self::BROWSER, 'Sec-Purpose' => 'prefetch'])
            ->get(route('public.matches.show', $match));

        $this->assertSame(0, PageView::count());
    }

    public function test_logged_in_admin_and_scorer_browsing_the_public_site_are_not_counted(): void
    {
        $match = GameMatch::factory()->create();
        $admin = User::factory()->create(['role_id' => Role::create(['name' => 'Admin', 'slug' => 'admin'])->id]);
        $scorer = User::factory()->create(['role_id' => Role::create(['name' => 'Scorer', 'slug' => 'scorer'])->id]);

        foreach ([$admin, $scorer] as $staff) {
            $response = $this->actingAs($staff)->withHeader('User-Agent', self::BROWSER)->get(route('public.matches.show', $match));
            $response->assertOk();
            $this->assertNull($response->getCookie(PageViewRecorder::VISITOR_COOKIE));
        }

        $this->assertSame(0, PageView::count());
    }

    public function test_bots_and_clients_without_a_user_agent_are_not_counted(): void
    {
        $match = GameMatch::factory()->create();

        foreach (['Googlebot/2.1 (+http://www.google.com/bot.html)', 'curl/8.4.0', 'facebookexternalhit/1.1', 'Mozilla/5.0 (compatible; bingbot/2.0)', 'python-requests/2.31', ''] as $userAgent) {
            $response = $this->visit(route('public.matches.show', $match), $this->newVisitor(), $userAgent);
            $response->assertOk();
            $this->assertNull($response->getCookie(PageViewRecorder::VISITOR_COOKIE));
        }

        $this->assertSame(0, PageView::count());
    }

    // ----- Failure safety -----

    public function test_a_recording_failure_never_breaks_the_public_page(): void
    {
        $match = GameMatch::factory()->create();

        $this->partialMock(PageViewRecorder::class, function ($mock) {
            $mock->shouldReceive('record')->andThrow(new RuntimeException('analytics store unavailable'));
        });

        $this->visit(route('public.matches.show', $match), $this->newVisitor())->assertOk();

        $this->assertSame(0, PageView::count());
    }

    // ----- Timezone -----

    public function test_local_date_and_hour_use_the_display_timezone_not_utc(): void
    {
        $match = GameMatch::factory()->create();
        app(SettingsService::class)->set('system.display_timezone', 'Asia/Kolkata');

        // 20:00 UTC on the 10th is 01:30 IST on the 11th.
        $this->travelTo(Carbon::parse('2026-03-10 20:00:00', 'UTC'));
        $this->visit(route('public.matches.show', $match), $this->newVisitor());

        $view = PageView::sole();
        $this->assertSame('2026-03-10 20:00:00', $view->viewed_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-11', $view->local_date);
        $this->assertSame(1, $view->local_hour);
    }

    public function test_changing_the_display_timezone_changes_new_events(): void
    {
        $match = GameMatch::factory()->create();
        app(SettingsService::class)->set('system.display_timezone', 'America/New_York');

        // 03:30 UTC on the 10th is 23:30 on the 9th in New York (EST, UTC-5, before DST).
        $this->travelTo(Carbon::parse('2026-01-10 03:30:00', 'UTC'));
        $this->visit(route('public.matches.show', $match), $this->newVisitor());

        $view = PageView::sole();
        $this->assertSame('2026-01-09', $view->local_date);
        $this->assertSame(22, $view->local_hour);
    }
}
