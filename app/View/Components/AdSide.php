<?php

namespace App\View\Components;

use App\Models\Advertisement;
use App\Services\Advertisement\AdvertisementDisplayService;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * <x-ad-side :offset="0" /> - a sponsor box on the live match page's side. All live "Side box" sponsors take turns in
 * it; with two boxes on the page the first one carries the ads 1, 3, 5... and the second one 2, 4, 6..., so the two
 * never show the same ad at once. Renders nothing when it has no ad, so an empty box leaves no gap. Display only: no
 * link, no click; a sponsor that cannot load removes itself and never breaks the page.
 */
class AdSide extends Component
{
    /**
     * @var Collection<int, Advertisement>
     */
    public Collection $ads;

    public int $rotateMs;

    public function __construct(AdvertisementDisplayService $display, public int $offset = 0)
    {
        $this->ads = $display->sideAds()
            ->values()
            ->filter(fn (Advertisement $ad, int $index) => $index % 2 === $this->offset % 2)
            ->values();

        $this->rotateMs = max(0, (int) config('ads.side_rotate_seconds')) * 1000;
    }

    public function shouldRender(): bool
    {
        return $this->ads->isNotEmpty();
    }

    public function render(): View
    {
        return view('components.ad-side');
    }
}
