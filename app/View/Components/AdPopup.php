<?php

namespace App\View\Components;

use App\Models\Advertisement;
use App\Services\Advertisement\AdvertisementDisplayService;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * <x-ad-popup [big] /> — the sponsor pop-up of the player auction page: it
 * appears a little while after the page opens, stays a few seconds, goes
 * away, and comes back after a pause (config/ads.php), all by itself. It is
 * display only, like every ad, and can be closed. Renders nothing when there
 * is no ad to show or the timings are switched off, so it never leaves a gap
 * or breaks the page.
 */
class AdPopup extends Component
{
    public ?Advertisement $ad;

    public int $firstSeconds;

    public int $visibleSeconds;

    public int $intervalSeconds;

    /**
     * @param  bool  $big  the projector layout: a larger pop-up for the room
     */
    public function __construct(AdvertisementDisplayService $display, public bool $big = false)
    {
        $this->ad = $display->auctionPopup();
        $this->firstSeconds = max(1, (int) config('ads.popup_first_seconds'));
        $this->visibleSeconds = (int) config('ads.popup_visible_seconds');
        $this->intervalSeconds = (int) config('ads.popup_interval_seconds');
    }

    public function shouldRender(): bool
    {
        return $this->ad !== null && $this->visibleSeconds > 0 && $this->intervalSeconds > 0;
    }

    public function render(): View
    {
        return view('components.ad-popup');
    }
}
