<?php

namespace Tests\Feature\Public;

use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public /photos gallery and the Videos + Photos links in the header's
 * "More" menu (desktop) and the mobile drawer.
 */
class PhotoDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_is_public_and_shows_only_active_photos(): void
    {
        Photo::factory()->create(['title' => 'Winning Moment', 'description' => 'The last ball']);
        Photo::factory()->inactive()->create(['title' => 'Unpublished Snap']);

        $this->get(route('public.photos.index'))
            ->assertOk()
            ->assertSee('Winning Moment')
            ->assertSee('The last ball')
            ->assertDontSee('Unpublished Snap');
    }

    public function test_gallery_paginates_twelve_per_page_in_priority_order(): void
    {
        foreach (range(13, 1) as $priority) {
            Photo::factory()->create([
                'title' => sprintf('Photo Snap %02d', $priority),
                'priority' => $priority,
            ]);
        }

        $first = $this->get(route('public.photos.index'));
        $first->assertOk();
        $first->assertSeeInOrder(array_map(fn ($i) => sprintf('Photo Snap %02d', $i), range(1, 12)));
        $first->assertDontSee('Photo Snap 13');

        $this->get(route('public.photos.index', ['page' => 2]))
            ->assertSee('Photo Snap 13')
            ->assertDontSee('Photo Snap 01');
    }

    public function test_empty_state_is_localized(): void
    {
        $this->get(route('public.photos.index'))->assertSee('No photos available yet.');

        $this->withCookie('rppl_locale', 'hi')
            ->get(route('public.photos.index'))
            ->assertSee('अभी कोई फोटो उपलब्ध नहीं है।');
    }

    public function test_photo_titles_are_escaped_and_never_translated(): void
    {
        Photo::factory()->create(['title' => '<script>alert(1)</script> Final']);

        $response = $this->withCookie('rppl_locale', 'hi')->get(route('public.photos.index'));

        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Final', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_header_more_menu_and_mobile_drawer_link_to_videos_and_photos(): void
    {
        $html = $this->get(route('public.home'))->assertOk()->getContent();

        // Desktop "More" dropdown and mobile drawer each carry both links.
        $this->assertSame(2, substr_count($html, 'href="'.route('public.videos.index').'"') - $this->videoLinksOutsideHeader($html));
        $this->assertSame(2, substr_count(substr($html, 0, strpos($html, '</header>')), 'href="'.route('public.photos.index').'"'));

        // Existing More items are preserved.
        foreach (['public.venues.index', 'public.editions.index', 'public.player-registration.create', 'public.rules.index', 'public.faqs'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $html);
        }

        $moreMenu = substr($html, strpos($html, '<details class="group relative flex self-stretch" data-menu>'));
        $moreMenu = substr($moreMenu, 0, strpos($moreMenu, '</details>'));
        $this->assertStringContainsString(route('public.videos.index'), $moreMenu);
        $this->assertStringContainsString(route('public.photos.index'), $moreMenu);
    }

    public function test_header_labels_follow_the_selected_language(): void
    {
        $this->get(route('public.home'))
            ->assertSee('>Videos</a>', false)
            ->assertSee('>Photos</a>', false);

        $this->withCookie('rppl_locale', 'hi')
            ->get(route('public.home'))
            ->assertSee('>वीडियो</a>', false)
            ->assertSee('>फोटो</a>', false);
    }

    /**
     * The homepage's Featured Videos "View All" link also points at
     * /videos, so it must not be counted as a header link.
     */
    private function videoLinksOutsideHeader(string $html): int
    {
        $headerEnd = strpos($html, '</header>');

        return substr_count(substr($html, $headerEnd), 'href="'.route('public.videos.index').'"');
    }
}
