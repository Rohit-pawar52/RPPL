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

    /**
     * Ids of the Normal ads already handed to a slot on this page.
     *
     * @var list<int>
     */
    private array $shownNormalIds = [];

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * The one Main sponsor (if two ever overlap, the newest wins).
     */
    public function main(): ?Advertisement
    {
        return $this->liveIn(Advertisement::TIER_MAIN)->sortByDesc('id')->first();
    }

    /**
     * The one Auction sponsor (if two ever overlap, the newest wins).
     */
    public function auction(): ?Advertisement
    {
        return $this->liveIn(Advertisement::TIER_AUCTION)->sortByDesc('id')->first();
    }

    /**
     * The sponsor for the pop-up on the player auction page: the Auction
     * sponsor, or the Main sponsor while there is no Auction sponsor live.
     */
    public function auctionPopup(): ?Advertisement
    {
        return $this->auction() ?? $this->main();
    }

    /**
     * The Normal sponsor for the next Normal slot of the given format
     * ('banner' strip or 'card' tile) on the page, chosen by weight among
     * the ads made for that format that are not shown yet — so two slots of
     * one format never repeat an ad, and a later slot is simply empty once
     * every live ad of that format is already on the page. A different ad
     * can come up on the next page load.
     */
    public function nextNormal(string $format = Advertisement::FORMAT_BANNER): ?Advertisement
    {
        $candidates = $this->liveIn(Advertisement::TIER_NORMAL)
            ->filter(fn (Advertisement $ad) => $ad->effectiveFormat() === $format)
            ->reject(fn (Advertisement $ad) => in_array($ad->id, $this->shownNormalIds, true));
        $total = $candidates->sum(fn (Advertisement $ad) => max(1, $ad->weight));

        if ($total < 1) {
            return null;
        }

        $pick = self::pickWeighted($candidates, random_int(0, $total - 1));

        if ($pick) {
            $this->shownNormalIds[] = $pick->id;
        }

        return $pick;
    }

    /**
     * Every live Normal sponsor made for the live match page's side box, heavier ones first. The page shows them
     * one after another in its side boxes (see App\View\Components\AdSide), so a longer list means more of them get
     * seen during one match.
     *
     * @return Collection<int, Advertisement>
     */
    public function sideAds(): Collection
    {
        return $this->liveIn(Advertisement::TIER_NORMAL)
            ->filter(fn (Advertisement $ad) => $ad->effectiveFormat() === Advertisement::FORMAT_SIDE)
            ->sortBy([['weight', 'desc'], ['id', 'asc']])
            ->values();
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
