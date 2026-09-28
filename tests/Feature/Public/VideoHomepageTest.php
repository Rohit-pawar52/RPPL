<?php

namespace Tests\Feature\Public;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoHomepageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The homepage only renders its sections (including Featured Videos)
     * once an edition exists, so every test starts from one with a
     * scheduled match to exercise the real match-first layout.
     */
    private function editionWithScheduledMatch(): array
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'match_status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
        ]);

        return [$edition, $teamA, $teamB];
    }

    public function test_active_video_is_featured_and_inactive_video_is_not(): void
    {
        $this->editionWithScheduledMatch();
        Video::factory()->create(['title' => 'Opening Ceremony Highlights']);
        Video::factory()->inactive()->create(['title' => 'Withdrawn Promo Clip']);

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('Featured Videos');
        $response->assertSee('Opening Ceremony Highlights');
        $response->assertDontSee('Withdrawn Promo Clip');
        $response->assertSee(route('public.videos.index'), false);
    }

    public function test_featured_videos_follow_priority_order_and_are_capped_at_three(): void
    {
        $this->editionWithScheduledMatch();
        // Created out of order so the assertion proves priority, not insertion order.
        Video::factory()->create(['title' => 'Priority Four Clip', 'priority' => 4]);
        Video::factory()->create(['title' => 'Priority Two Clip', 'priority' => 2]);
        Video::factory()->create(['title' => 'Priority Three Clip', 'priority' => 3]);
        Video::factory()->create(['title' => 'Priority One Clip', 'priority' => 1]);

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSeeInOrder(['Priority One Clip', 'Priority Two Clip', 'Priority Three Clip']);
        $response->assertDontSee('Priority Four Clip');
    }

    public function test_featured_videos_sit_between_match_blocks_and_points_table(): void
    {
        [, $teamA] = $this->editionWithScheduledMatch();
        Video::factory()->create(['title' => 'Placement Check Clip']);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSeeInOrder([
                $teamA->team->short_name ?: $teamA->team->name,
                'Featured Videos',
                'Placement Check Clip',
                'Points Table',
                'Upcoming Matches',
            ]);
    }

    public function test_homepage_with_no_active_videos_renders_existing_sections_and_no_video_section(): void
    {
        [, $teamA] = $this->editionWithScheduledMatch();
        Video::factory()->inactive()->create(['title' => 'Hidden Clip']);

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee($teamA->team->name);
        $response->assertSee('Points Table');
        $response->assertSee('Upcoming Matches');
        $response->assertSee('Top Performers');
        $response->assertDontSee('Featured Videos');
        $response->assertDontSee('Hidden Clip');
        $response->assertDontSee('<video', false);
    }

    public function test_video_with_thumbnail_renders_poster_and_no_thumbnail_renders_without_one(): void
    {
        $this->editionWithScheduledMatch();
        $withThumb = Video::factory()->create([
            'title' => 'Clip With Thumbnail',
            'video_path' => 'videos/with-thumb.mp4',
            'thumbnail_path' => 'videos/thumbnails/with-thumb.jpg',
            'priority' => 1,
        ]);

        $response = $this->get(route('public.home'));
        $response->assertOk();
        $response->assertSee('poster="'.Storage::url($withThumb->thumbnail_path).'"', false);
        $response->assertSee(Storage::url($withThumb->video_path), false);

        $withThumb->update(['thumbnail_path' => null]);

        $response = $this->get(route('public.home'));
        $response->assertOk();
        $response->assertSee('Clip With Thumbnail');
        $response->assertSee('<video', false);
        $response->assertDontSee('poster=', false);
    }

    public function test_featured_videos_never_autoplay_and_load_metadata_only(): void
    {
        $this->editionWithScheduledMatch();
        Video::factory()->count(3)->create();

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $this->assertStringNotContainsStringIgnoringCase('autoplay', $response->getContent());
        $this->assertSame(3, substr_count($response->getContent(), 'preload="metadata"'));
        $this->assertStringNotContainsString('.play()', $response->getContent());
    }
}
