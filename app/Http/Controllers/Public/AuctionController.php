<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Services\Auction\AuctionStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * The public player-auction page. Read-only, no login. It shows the live
 * auction (or paused one) of the newest season and, once that is over, its
 * results; a draft is never shown. The live view draws itself from the same
 * public state the /auction/data endpoint serves every few seconds; the
 * results are plain server-rendered HTML. `?display=big` is the projector
 * layout for the room. Everything shown comes from AuctionStateService::
 * public(), which holds back anything private.
 */
class AuctionController extends Controller
{
    public function __construct(private readonly AuctionStateService $states) {}

    public function show(Request $request): View
    {
        $auction = $this->states->publicAuction();
        $big = $request->query('display') === 'big';

        if (! $auction) {
            return view('public.auction.none', ['big' => $big]);
        }

        $state = $this->state($auction);

        if ($auction->isCompleted()) {
            return view('public.auction.results', ['state' => $state, 'big' => $big]);
        }

        return view('public.auction.live', [
            'state' => $state,
            'big' => $big,
            'texts' => __('auction'),
            'dataUrl' => route('public.auction.data'),
            'pollSeconds' => max(1, (int) config('auction.public_poll_seconds')),
        ]);
    }

    public function data(): JsonResponse
    {
        $auction = $this->states->publicAuction();

        return response()
            ->json(['state' => $auction ? $this->state($auction) : null])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * @return array<string, mixed>
     */
    private function state(Auction $auction): array
    {
        $seconds = (int) config('auction.public_cache_seconds');

        if ($seconds <= 0) {
            return $this->states->public($auction);
        }

        return Cache::remember(Auction::publicStateCacheKey($auction->id), $seconds, fn () => $this->states->public($auction));
    }
}
