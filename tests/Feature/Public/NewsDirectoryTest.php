<?php

namespace Tests\Feature\Public;

use App\Models\News;
use App\Models\NewsImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Public /news listing and /news/{slug} detail, plus the News link in
 * the header's "More" menu and the mobile drawer.
 */
class NewsDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function image(News $news, string $path, int $sortOrder): NewsImage
    {
        // The picture is really on the disk, as it is for a post saved by the admin.
        Storage::disk('public')->put($path, 'picture');

        return NewsImage::create(['news_id' => $news->id, 'image_path' => $path, 'sort_order' => $sortOrder]);
    }

    // ----- Listing -----

    public function test_listing_is_public_and_shows_only_active_published_news(): void
    {
        News::factory()->create(['title' => 'Visible Headline', 'content' => 'Visible body text']);
        News::factory()->inactive()->create(['title' => 'Inactive Headline']);
        News::factory()->scheduled()->create(['title' => 'Future Headline']);

        $this->get(route('public.news.index'))
            ->assertOk()
            ->assertSee('Visible Headline')
            ->assertSee('Visible body text')
            ->assertDontSee('Inactive Headline')
            ->assertDontSee('Future Headline');
    }

    public function test_listing_orders_by_priority_then_newest_published(): void
    {
        News::factory()->create(['title' => 'Headline Alpha', 'priority' => 1, 'published_at' => now()->subDays(2)]);
        News::factory()->create(['title' => 'Headline Bravo', 'priority' => 1, 'published_at' => now()->subDay()]);
        News::factory()->create(['title' => 'Headline Charlie', 'priority' => 5, 'published_at' => now()->subMinute()]);

        $this->get(route('public.news.index'))
            ->assertSeeInOrder(['Headline Bravo', 'Headline Alpha', 'Headline Charlie']);
    }

    public function test_listing_shows_the_first_image_by_sort_order_as_the_cover(): void
    {
        $news = News::factory()->create();
        // Inserted out of order on purpose.
        $this->image($news, 'news/second.jpg', 1);
        $this->image($news, 'news/first.jpg', 0);

        $html = $this->get(route('public.news.index'))->assertOk()->getContent();

        $this->assertStringContainsString('news/first.jpg', $html);
        $this->assertStringNotContainsString('news/second.jpg', $html);
    }

    public function test_text_only_news_shows_the_default_picture_on_its_card(): void
    {
        News::factory()->create(['title' => 'Text Only Headline']);

        $html = $this->get(route('public.news.index'))->assertOk()->assertSee('Text Only Headline')->getContent();

        // No picture of its own: the card shows the default one, never a broken image or its alt text.
        $this->assertStringContainsString('images/default.png', $html);
    }

    public function test_listing_excerpt_is_shortened_and_the_full_text_is_not_shown(): void
    {
        News::factory()->create(['title' => 'Long One', 'content' => str_repeat('word ', 200).'TAILMARKER']);

        $this->get(route('public.news.index'))
            ->assertSee('Long One')
            ->assertDontSee('TAILMARKER');
    }

    public function test_listing_paginates_nine_per_page(): void
    {
        foreach (range(1, 10) as $i) {
            News::factory()->create(['title' => sprintf('Paged Headline %02d', $i), 'priority' => $i]);
        }

        $this->get(route('public.news.index'))
            ->assertSee('Paged Headline 09')
            ->assertDontSee('Paged Headline 10');
        $this->get(route('public.news.index', ['page' => 2]))->assertSee('Paged Headline 10');
    }

    public function test_empty_state_is_localized(): void
    {
        $this->get(route('public.news.index'))->assertSee('No news available yet.');

        $this->withCookie('rppl_locale', 'hi')
            ->get(route('public.news.index'))
            ->assertSee('अभी कोई समाचार उपलब्ध नहीं है।');
    }

    public function test_page_chrome_is_localized_but_news_content_is_not_translated(): void
    {
        News::factory()->create(['title' => 'Final Match Result', 'content' => 'English body stays as typed']);

        $this->get(route('public.news.index'))->assertSee('Read More');

        $this->withCookie('rppl_locale', 'hi')
            ->get(route('public.news.index'))
            ->assertSee('पूरा पढ़ें')
            ->assertSee('Final Match Result')
            ->assertSee('English body stays as typed');
    }

    // ----- Detail -----

    public function test_detail_shows_heading_date_full_content_and_keeps_line_breaks(): void
    {
        $news = News::factory()->create([
            'title' => 'Registration Opens',
            'slug' => 'registration-opens',
            'content' => "Line one.\n\nLine two with the full story.",
            'published_at' => now()->subDay(),
        ]);

        $html = $this->get(route('public.news.show', 'registration-opens'))
            ->assertOk()
            ->assertSee('Registration Opens')
            ->assertSee('Published')
            ->assertSee(display_datetime($news->published_at, 'd M Y, h:i A'))
            ->assertSee('<title>Registration Opens', false)
            ->getContent();

        $this->assertStringContainsString("Line one.\n\nLine two with the full story.", $html);
        $this->assertStringContainsString('whitespace-pre-line', $html);
    }

    public function test_detail_shows_all_images_in_sort_order(): void
    {
        $news = News::factory()->create(['slug' => 'with-images']);
        $this->image($news, 'news/img-b.jpg', 1);
        $this->image($news, 'news/img-c.jpg', 2);
        $this->image($news, 'news/img-a.jpg', 0);

        $this->get(route('public.news.show', 'with-images'))
            ->assertOk()
            ->assertSeeInOrder(['news/img-a.jpg', 'news/img-b.jpg', 'news/img-c.jpg'])
            ->assertSee('rppl-photo-link', false);
    }

    public function test_detail_of_a_single_image_and_a_text_only_post_both_render(): void
    {
        $single = News::factory()->create(['slug' => 'single']);
        $this->image($single, 'news/only.jpg', 0);
        News::factory()->create(['slug' => 'plain', 'title' => 'Plain Story']);

        $this->get(route('public.news.show', 'single'))->assertOk()->assertSee('news/only.jpg');

        $html = $this->get(route('public.news.show', 'plain'))->assertOk()->assertSee('Plain Story')->getContent();
        $this->assertStringNotContainsString('rppl-photo-link', $html);
    }

    public function test_inactive_future_and_unknown_slugs_are_not_found(): void
    {
        News::factory()->inactive()->create(['slug' => 'inactive-post']);
        News::factory()->scheduled()->create(['slug' => 'future-post']);

        $this->get(route('public.news.show', 'inactive-post'))->assertNotFound();
        $this->get(route('public.news.show', 'future-post'))->assertNotFound();
        $this->get(route('public.news.show', 'no-such-post'))->assertNotFound();
    }

    public function test_a_post_becomes_public_once_its_published_time_passes(): void
    {
        News::factory()->create(['slug' => 'soon', 'title' => 'Coming Soon', 'published_at' => now()->addHour()]);

        $this->get(route('public.news.show', 'soon'))->assertNotFound();

        $this->travel(2)->hours();

        $this->get(route('public.news.show', 'soon'))->assertOk()->assertSee('Coming Soon');
    }

    public function test_title_and_content_are_escaped_and_never_rendered_as_html(): void
    {
        News::factory()->create([
            'slug' => 'xss',
            'title' => '<script>alert("t")</script> Title',
            'content' => "<img src=x onerror=alert(1)>\n<b>bold</b>",
        ]);

        foreach ([route('public.news.show', 'xss'), route('public.news.index')] as $url) {
            $response = $this->get($url)->assertOk();
            $response->assertDontSee('<script>alert("t")</script>', false);
            $response->assertDontSee('<img src=x', false);
            $response->assertDontSee('<b>bold</b>', false);
        }

        $this->get(route('public.news.show', 'xss'))
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertSee('&lt;b&gt;bold&lt;/b&gt;', false);
    }

    public function test_detail_labels_follow_the_selected_language(): void
    {
        News::factory()->create(['slug' => 'hindi-chrome']);

        $this->withCookie('rppl_locale', 'hi')
            ->get(route('public.news.show', 'hindi-chrome'))
            ->assertSee('प्रकाशित')
            ->assertSee('समाचार पर वापस जाएँ');
    }

    // ----- Navigation -----

    public function test_desktop_more_menu_and_mobile_drawer_link_to_news_videos_and_photos(): void
    {
        $html = $this->get(route('public.home'))->assertOk()->getContent();

        // Within the header each appears twice: the desktop "More" dropdown and the mobile menu
        // (the footer carries its own links, so only the header is counted).
        $header = substr($html, 0, strpos($html, '</header>'));
        $this->assertSame(2, substr_count($header, 'href="'.route('public.news.index').'"'));
        $this->assertSame(2, substr_count($header, 'href="'.route('public.photos.index').'"'));
        $this->assertGreaterThanOrEqual(2, substr_count($header, 'href="'.route('public.videos.index').'"'));

        // Existing More items are preserved.
        foreach (['public.venues.index', 'public.editions.index', 'public.player-registration.create', 'public.rules.index', 'public.faqs'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $html);
        }

        // The first occurrence (desktop) sits inside the "More" menu.
        $moreMenu = substr($html, strpos($html, '<details class="group relative flex self-stretch" data-menu>'));
        $moreMenu = substr($moreMenu, 0, strpos($moreMenu, '</details>'));
        foreach (['public.news.index', 'public.videos.index', 'public.photos.index'] as $route) {
            $this->assertStringContainsString(route($route), $moreMenu);
        }
    }

    public function test_header_news_label_follows_the_selected_language(): void
    {
        $this->get(route('public.home'))->assertSee('>News</a>', false);

        $this->withCookie('rppl_locale', 'hi')
            ->get(route('public.home'))
            ->assertSee('>समाचार</a>', false);
    }
}
