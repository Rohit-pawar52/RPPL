<?php

namespace Tests\Feature\Admin;

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
use Tests\TestCase;

/**
 * Admin Analytics: the read side of the page_views capture. Everything is
 * aggregated from the stored local_date / local_hour, deleted subjects
 * stay in the numbers, and no visitor identifier is ever shown.
 */
class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $scorerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->scorerRole = Role::create(['name' => 'Scorer', 'slug' => 'scorer']);

        app(SettingsService::class)->flush();
        app(SettingsService::class)->set('system.display_timezone', 'Asia/Kolkata');

        // 12:00 UTC on 10 Mar 2026 is 17:30 IST on the same day, so the
        // default "last 7 days" is 2026-03-04 .. 2026-03-10.
        $this->travelTo(Carbon::parse('2026-03-10 12:00:00', 'UTC'));
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    private function scorer(): User
    {
        return User::factory()->create(['role_id' => $this->scorerRole->id]);
    }

    private function recordView(string $type, int $subjectId, string $visitor, string $localDate = '2026-03-10', int $localHour = 14, ?Carbon $viewedAt = null): PageView
    {
        return PageView::create([
            'event_type' => $type,
            'subject_id' => $subjectId,
            'visitor_hash' => hash('sha256', $visitor),
            'viewed_at' => $viewedAt ?? Carbon::parse($localDate.' 08:30:00', 'UTC'),
            'local_date' => $localDate,
            'local_hour' => $localHour,
        ]);
    }

    private function page(array $query = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->admin())->get(route('admin.analytics.index', $query));
    }

    // ----- Access -----

    public function test_an_admin_can_open_the_analytics_page_and_sees_it_in_the_navigation(): void
    {
        $this->page()
            ->assertOk()
            ->assertSee('Analytics')
            ->assertSee(route('admin.analytics.index'), false);
    }

    public function test_guests_and_scorers_cannot_open_analytics_and_scorers_do_not_see_the_link(): void
    {
        $this->get(route('admin.analytics.index'))->assertRedirect(route('admin.login'));

        $this->page(user: $this->scorer())->assertForbidden();

        $this->actingAs($this->scorer())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.analytics.index'), false);
    }

    // ----- Summary -----

    public function test_totals_unique_visitors_and_each_event_type_are_counted(): void
    {
        $this->recordView(PageView::MATCH_VIEW, 1, 'alice');
        $this->recordView(PageView::MATCH_VIEW, 2, 'alice');
        $this->recordView(PageView::MATCH_VIEW, 1, 'bob');
        $this->recordView(PageView::EDITION_VIEW, 1, 'alice');
        $this->recordView(PageView::PLAYER_VIEW, 5, 'carol');
        $this->recordView(PageView::PLAYER_VIEW, 5, 'alice');
        $this->recordView(PageView::PLAYER_VIEW, 6, 'carol');

        $response = $this->page()->assertOk();

        $this->assertSame(['total' => 7, 'visitors' => 3], $response->viewData('summary'));

        $breakdown = $response->viewData('breakdown');
        $this->assertSame(['views' => 3, 'visitors' => 2], $breakdown[PageView::MATCH_VIEW]);
        $this->assertSame(['views' => 1, 'visitors' => 1], $breakdown[PageView::EDITION_VIEW]);
        $this->assertSame(['views' => 3, 'visitors' => 2], $breakdown[PageView::PLAYER_VIEW]);
    }

    public function test_the_same_visitors_deduplicated_phase_one_rows_show_up_as_views_and_one_visitor(): void
    {
        $match = GameMatch::factory()->create();
        $visitor = (string) Str::uuid();
        $recorder = app(PageViewRecorder::class);

        // Real capture path: 10:00 counts, 10:10 is inside the 30-minute
        // window, 10:40 counts again — two views, one visitor.
        foreach (['10:00', '10:10', '10:40'] as $time) {
            $this->travelTo(Carbon::parse("2026-03-10 {$time}:00", 'UTC'));
            $recorder->record(PageView::MATCH_VIEW, $match->id, $visitor);
        }

        $this->assertSame(['total' => 2, 'visitors' => 1], $this->page()->viewData('summary'));
    }

    // ----- Filters -----

    public function test_the_date_filter_uses_local_date_and_the_presets_resolve_in_the_display_timezone(): void
    {
        $this->recordView(PageView::MATCH_VIEW, 1, 'a', '2026-03-10');
        $this->recordView(PageView::MATCH_VIEW, 1, 'b', '2026-03-09');
        $this->recordView(PageView::MATCH_VIEW, 1, 'c', '2026-03-04');
        $this->recordView(PageView::MATCH_VIEW, 1, 'd', '2026-03-03');
        $this->recordView(PageView::MATCH_VIEW, 1, 'e', '2026-02-01');

        $this->assertSame(1, $this->page(['range' => 'today'])->viewData('summary')['total']);
        $this->assertSame(1, $this->page(['range' => 'yesterday'])->viewData('summary')['total']);
        // Default and "last7" are the 7 days 04..10 Mar, inclusive.
        $this->assertSame(3, $this->page()->viewData('summary')['total']);
        $this->assertSame(3, $this->page(['range' => 'last7'])->viewData('summary')['total']);
        $this->assertSame(4, $this->page(['range' => 'last30'])->viewData('summary')['total']);

        $custom = $this->page(['range' => 'custom', 'from_date' => '2026-02-01', 'to_date' => '2026-03-03']);
        $this->assertSame(2, $custom->viewData('summary')['total']);
        $this->assertSame(['from' => '2026-02-01', 'to' => '2026-03-03'], $custom->viewData('dates'));
    }

    public function test_the_content_type_filter_narrows_totals_and_the_top_tables_but_not_the_type_cards(): void
    {
        $this->recordView(PageView::MATCH_VIEW, 1, 'a');
        $this->recordView(PageView::MATCH_VIEW, 1, 'b');
        $this->recordView(PageView::PLAYER_VIEW, 5, 'a');

        $response = $this->page(['type' => 'player']);

        $this->assertSame(['total' => 1, 'visitors' => 1], $response->viewData('summary'));
        $this->assertNull($response->viewData('topMatches'));
        $this->assertNull($response->viewData('topEditions'));
        $this->assertNotNull($response->viewData('topPlayers'));
        $this->assertSame(2, $response->viewData('breakdown')[PageView::MATCH_VIEW]['views']);

        $all = $this->page(['type' => 'all']);
        $this->assertSame(3, $all->viewData('summary')['total']);
        $this->assertNotNull($all->viewData('topMatches'));
    }

    public function test_bad_filters_show_errors_and_fall_back_to_the_defaults_without_redirecting(): void
    {
        $reversed = $this->page(['range' => 'custom', 'from_date' => '2026-03-10', 'to_date' => '2026-03-01'])->assertOk();
        $this->assertTrue($reversed->viewData('errors')->has('to_date'));
        // The report falls back to the default range...
        $this->assertSame('last7', $reversed->viewData('filters')['range']);
        // ...the form keeps what was typed so it can be corrected...
        $this->assertSame('custom', $reversed->viewData('form')['range']);
        $this->assertSame('2026-03-10', $reversed->viewData('form')['from_date']);
        // ...and the problem is stated in a banner that is visible whatever
        // the state of the date fields.
        $reversed->assertSee('Those filters could not be applied')
            ->assertSee('after or equal to');

        $missing = $this->page(['range' => 'custom'])->assertOk();
        $this->assertTrue($missing->viewData('errors')->has('from_date'));

        $tooLong = $this->page(['range' => 'custom', 'from_date' => '2024-01-01', 'to_date' => '2026-03-10'])->assertOk();
        $this->assertTrue($tooLong->viewData('errors')->has('to_date'));

        $malformed = $this->page(['range' => 'custom', 'from_date' => '10/03/2026', 'to_date' => '2026-03-10'])->assertOk();
        $this->assertTrue($malformed->viewData('errors')->has('from_date'));

        // Anything outside the allow-lists is rejected, never used in a query.
        $injected = $this->page(['range' => "today'; DROP TABLE page_views;--", 'type' => 'players; --'])->assertOk();
        $this->assertTrue($injected->viewData('errors')->has('range'));
        $this->assertTrue($injected->viewData('errors')->has('type'));
        $this->assertSame('all', $injected->viewData('filters')['type']);
        $this->assertSame(0, PageView::count());
    }

    public function test_array_style_query_values_cannot_break_the_page(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.analytics.index').'?range[]=custom&type[]=x&from_date[]=2026-01-01&to_date[a]=b&matches_page[]=1')
            ->assertOk()
            ->assertSee('Those filters could not be applied');

        $this->assertSame('last7', $response->viewData('filters')['range']);
        $this->assertNull($response->viewData('form')['from_date']);
    }

    // ----- Daily and hourly -----

    public function test_daily_views_group_by_local_date_not_the_utc_date_and_include_empty_days(): void
    {
        // 20:00 UTC on the 9th is 01:30 IST on the 10th — the stored local
        // date is what the report uses.
        $this->recordView(PageView::MATCH_VIEW, 1, 'a', '2026-03-10', 1, Carbon::parse('2026-03-09 20:00:00', 'UTC'));
        $this->recordView(PageView::MATCH_VIEW, 1, 'b', '2026-03-10', 14);
        $this->recordView(PageView::MATCH_VIEW, 1, 'a', '2026-03-08', 14);

        $daily = collect($this->page()->viewData('daily'))->keyBy('date');

        $this->assertCount(7, $daily);
        $this->assertSame(['date' => '2026-03-10', 'views' => 2, 'visitors' => 2], $daily['2026-03-10']);
        $this->assertSame(0, $daily['2026-03-09']['views']);
        $this->assertSame(1, $daily['2026-03-08']['views']);
        $this->assertSame(0, $daily['2026-03-04']['views']);
    }

    public function test_hourly_views_group_by_local_hour_not_the_utc_hour(): void
    {
        $this->recordView(PageView::MATCH_VIEW, 1, 'a', '2026-03-10', 1, Carbon::parse('2026-03-09 20:00:00', 'UTC'));
        $this->recordView(PageView::MATCH_VIEW, 1, 'b', '2026-03-09', 1, Carbon::parse('2026-03-09 20:10:00', 'UTC'));
        $this->recordView(PageView::PLAYER_VIEW, 2, 'c', '2026-03-10', 14);

        $hourly = collect($this->page()->viewData('hourly'))->keyBy('hour');

        $this->assertCount(24, $hourly);
        $this->assertSame(2, $hourly[1]['views']);
        $this->assertSame(1, $hourly[14]['views']);
        $this->assertSame(0, $hourly[20]['views']);
    }

    // ----- Top matches / editions / players -----

    public function test_top_matches_are_aggregated_and_ordered_with_readable_names(): void
    {
        $popular = GameMatch::factory()->create();
        $quiet = GameMatch::factory()->create();

        $this->recordView(PageView::MATCH_VIEW, $quiet->id, 'a');
        foreach (['a', 'b', 'c'] as $visitor) {
            $this->recordView(PageView::MATCH_VIEW, $popular->id, $visitor, '2026-03-10', 10);
        }
        $this->recordView(PageView::MATCH_VIEW, $popular->id, 'a', '2026-03-09', 10);
        // Outside the range: must not count.
        $this->recordView(PageView::MATCH_VIEW, $quiet->id, 'z', '2026-02-01');

        $rows = $this->page()->viewData('topMatches')->items();

        $this->assertSame([$popular->id, $quiet->id], array_column($rows, 'subject_id'));
        $this->assertSame(4, $rows[0]['views']);
        $this->assertSame(3, $rows[0]['visitors']);
        $this->assertSame(1, $rows[1]['views']);

        $popular->load('teamA.team', 'teamB.team');
        $expected = ($popular->teamA->team->short_name ?: $popular->teamA->team->name).' vs '.($popular->teamB->team->short_name ?: $popular->teamB->team->name);
        $this->assertSame($expected, $rows[0]['label']);
        $this->assertSame(route('admin.matches.show', $popular), $rows[0]['url']);
        $this->assertFalse($rows[0]['deleted']);
    }

    public function test_top_editions_and_players_are_aggregated(): void
    {
        $edition = Edition::factory()->create(['name' => 'RPPL Test Cup', 'year' => 2031]);
        $player = Player::factory()->create(['name' => 'Aarav Test']);

        $this->recordView(PageView::EDITION_VIEW, $edition->id, 'a');
        $this->recordView(PageView::EDITION_VIEW, $edition->id, 'b');
        $this->recordView(PageView::PLAYER_VIEW, $player->id, 'a');
        $this->recordView(PageView::PLAYER_VIEW, $player->id, 'a', '2026-03-09');

        $response = $this->page();

        $editionRow = $response->viewData('topEditions')->items()[0];
        $this->assertSame('RPPL Test Cup', $editionRow['label']);
        $this->assertSame([2, 2], [$editionRow['views'], $editionRow['visitors']]);

        // Two views by the same visitor on different days: 2 views, 1 visitor.
        $playerRow = $response->viewData('topPlayers')->items()[0];
        $this->assertSame('Aarav Test', $playerRow['label']);
        $this->assertSame([2, 1], [$playerRow['views'], $playerRow['visitors']]);

        $response->assertSee('RPPL Test Cup')->assertSee('Aarav Test');
    }

    public function test_deleted_subjects_stay_in_the_numbers_and_never_break_the_page(): void
    {
        $this->recordView(PageView::MATCH_VIEW, 9999, 'a');
        $this->recordView(PageView::EDITION_VIEW, 8888, 'a');
        $this->recordView(PageView::PLAYER_VIEW, 7777, 'b');

        $response = $this->page()->assertOk()
            ->assertSee('Deleted Match #9999')
            ->assertSee('Deleted Edition #8888')
            ->assertSee('Deleted Player #7777');

        $this->assertSame(3, $response->viewData('summary')['total']);
        $this->assertTrue($response->viewData('topMatches')->items()[0]['deleted']);
        $this->assertNull($response->viewData('topMatches')->items()[0]['url']);
    }

    public function test_the_top_tables_are_paginated_independently(): void
    {
        foreach (range(1, 12) as $i) {
            $this->recordView(PageView::MATCH_VIEW, 1000 + $i, 'v'.$i);
            $this->recordView(PageView::PLAYER_VIEW, 2000 + $i, 'v'.$i);
        }

        $first = $this->page();
        $this->assertCount(10, $first->viewData('topMatches')->items());
        $this->assertSame(12, $first->viewData('topMatches')->total());

        $second = $this->page(['matches_page' => 2]);
        $this->assertCount(2, $second->viewData('topMatches')->items());
        // The players table keeps its own page.
        $this->assertSame(1, $second->viewData('topPlayers')->currentPage());
        $this->assertCount(10, $second->viewData('topPlayers')->items());
    }

    // ----- Privacy and empty states -----

    public function test_no_visitor_identifier_is_ever_shown(): void
    {
        $hash = hash_hmac('sha256', 'a-visitor-cookie-uuid', 'secret');
        PageView::create([
            'event_type' => PageView::MATCH_VIEW,
            'subject_id' => 9999,
            'visitor_hash' => $hash,
            'viewed_at' => now(),
            'local_date' => '2026-03-10',
            'local_hour' => 14,
        ]);

        $response = $this->page()->assertOk();

        $response->assertDontSee($hash)
            ->assertDontSee(substr($hash, 0, 16))
            ->assertDontSee('visitor_hash')
            ->assertDontSee('rppl_vid');
        $this->assertStringNotContainsString($hash, json_encode($response->viewData('topMatches')->items()));
    }

    public function test_a_period_with_no_views_shows_an_empty_state_with_zeroed_numbers(): void
    {
        $response = $this->page()->assertOk()->assertSee('No page views recorded for this period.');

        $this->assertSame(['total' => 0, 'visitors' => 0], $response->viewData('summary'));
        $this->assertCount(7, $response->viewData('daily'));
        $this->assertCount(24, $response->viewData('hourly'));
    }
}
