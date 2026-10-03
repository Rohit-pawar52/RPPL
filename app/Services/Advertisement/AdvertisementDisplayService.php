<?php

namespace App\Services\Advertisement;

use App\Models\Advertisement;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Picks the sponsor ads a public page shows. One instance per request, so
 * the ads are read once and the Normal banner is the same wherever it is
 * placed on that page. The whole thing is fail-safe: if ads cannot be
 * loaded, the page simply has none — a sponsor must never break a page.
 */
class AdvertisementDisplayService
{
    /**
     * @var Collection<int, Advertisement>|null
     */
    private ?Collection $live = null;

    private bool $bannerPicked = false;

    private ?Advertisement $banner = null;

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * The one Main sponsor (if two ever overlap, the newest wins).
     */
    public function main(): ?Advertisement
    {
        return $this->liveIn(Advertisement::TIER_MAIN)->sortByDesc('id')->first();
    }

    /**
     * One Normal sponsor, chosen by weight; a different one can come up on
     * the next page load. Fixed for the rest of this request.
     */
    public function banner(): ?Advertisement
    {
        if (! $this->bannerPicked) {
            $candidates = $this->liveIn(Advertisement::TIER_NORMAL);
            $total = $candidates->sum('weight');

            $this->banner = $total > 0 ? self::pickWeighted($candidates, random_int(0, $total - 1)) : null;
            $this->bannerPicked = true;
        }

        return $this->banner;
    }

    /**
     * Every live Mini sponsor, for the logo strip (heavier ones first).
     *
     * @return Collection<int, Advertisement>
     */
    public function minis(): Collection
    {
        return $this->liveIn(Advertisement::TIER_MINI)->sortBy([['weight', 'desc'], ['id', 'asc']])->values();
    }

    /**
     * $roll is a number from 0 up to (total weight - 1): the ad whose share
     * of the total it lands in is the pick. Public so it can be tested
     * without randomness.
     *
     * @param  Collection<int, Advertisement>  $ads
     */
    public static function pickWeighted(Collection $ads, int $roll): ?Advertisement
    {
        foreach ($ads->sortBy('id') as $ad) {
            $roll -= max(1, $ad->weight);

            if ($roll < 0) {
                return $ad;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, Advertisement>
     */
    private function liveIn(string $tier): Collection
    {
        return $this->liveAds()->where('tier', $tier)->values();
    }

    /**
     * @return Collection<int, Advertisement>
     */
    private function liveAds(): Collection
    {
        if ($this->live === null) {
            try {
                // "Today" is the calendar day in the display timezone, the
                // same one the admin picked the dates in.
                $today = now($this->settings->get('system.display_timezone'))->toDateString();

                $this->live = Advertisement::query()->liveOn($today)->get();
            } catch (Throwable $e) {
                report($e);

                $this->live = new Collection;
            }
        }

        return $this->live;
    }
}
