<?php

namespace App\View\Components;

use App\Models\Advertisement;
use App\Services\Advertisement\AdvertisementDisplayService;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * <x-ad-slot tier="main|normal|mini" /> — a sponsor placement on a public
 * page. Renders nothing when no ad is live for that tier, so an empty slot
 * leaves no gap. Ads are display-only: no link, no overlay, never inside
 * the live-score area that is refreshed by polling.
 */
class AdSlot extends Component
{
    /**
     * @var Collection<int, Advertisement>
     */
    public Collection $ads;

    public function __construct(public string $tier, AdvertisementDisplayService $display)
    {
        $this->ads = match ($tier) {
            Advertisement::TIER_MAIN => collect([$display->main()])->filter(),
            Advertisement::TIER_NORMAL => collect([$display->banner()])->filter(),
            Advertisement::TIER_MINI => $display->minis(),
            default => collect(),
        };
    }

    public function shouldRender(): bool
    {
        return $this->ads->isNotEmpty();
    }

    public function render(): View
    {
        return view('components.ad-slot');
    }
}
