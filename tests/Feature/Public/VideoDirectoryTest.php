<?php

namespace Tests\Feature\Public;

use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_active_videos_and_hides_inactive_ones(): void
    {
        Video::factory()->create(['title' => 'Final Over Thriller']);
        Video::factory()->inactive()->create(['title' => 'Unpublished Draft Clip']);

        $response = $this->get(route('public.videos.index'));

        $response->assertOk();
        $response->assertSee('Final Over Thriller');
        $response->assertDontSee('Unpublished Draft Clip');
        $this->assertStringNotContainsStringIgnoringCase('autoplay', $response->getContent());
    }

    public function test_index_paginates_twelve_per_page_in_priority_order(): void
    {
        // Created in reverse so order on the page must come from priority.
        foreach (range(13, 1) as $priority) {
            Video::factory()->create([
                'title' => sprintf('Video Clip %02d', $priority),
                'priority' => $priority,
            ]);
        }

        $firstPage = $this->get(route('public.videos.index'));
        $firstPage->assertOk();
        $firstPage->assertSeeInOrder(array_map(fn ($i) => sprintf('Video Clip %02d', $i), range(1, 12)));
        $firstPage->assertDontSee('Video Clip 13');

        $secondPage = $this->get(route('public.videos.index', ['page' => 2]));
        $secondPage->assertOk();
        $secondPage->assertSee('Video Clip 13');
        $secondPage->assertDontSee('Video Clip 01');
    }

    public function test_index_with_no_videos_shows_empty_state(): void
    {
        $this->get(route('public.videos.index'))
            ->assertOk()
            ->assertSee('No videos available yet.');
    }
}
