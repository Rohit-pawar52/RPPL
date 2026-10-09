<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auction\StoreAuctionRequest;
use App\Http\Requests\Admin\Auction\UpdateAuctionRequest;
use App\Models\Auction;
use App\Models\Edition;
use App\Services\Auction\AuctionNotificationService;
use App\Services\Auction\AuctionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The player auction's set-up side, for the admin and the auctioneer
 * (AuctionPolicy): one page per season to create the auction, change its
 * rules and each team's purse, bring the pool up to date and start, pause,
 * resume or complete it. All rules live in AuctionService; a rule failure
 * comes back as a message, never an error page.
 */
class AuctionController extends Controller
{
    public function __construct(
        private readonly AuctionService $auctions,
        private readonly AuctionNotificationService $notifications,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Auction::class);

        $editions = Edition::query()
            ->with(['auction' => fn ($query) => $query->withCount('lots')])
            ->withCount('editionTeams')
            ->orderByDesc('year')
            ->get();

        return view('admin.auctions.index', ['editions' => $editions]);
    }

    public function show(Edition $edition): View
    {
        $this->authorize('viewAny', Auction::class);

        $auction = $edition->auction;

        $data = [
            'edition' => $edition,
            'auction' => $auction,
            'teamCount' => $edition->editionTeams()->count(),
        ];

        if ($auction) {
            $data['counts'] = $this->auctions->counts($auction);
            $data['standings'] = $this->auctions->teamStandings($auction);
        } else {
            $data['readyPlayers'] = $edition->playerRegistrations()
                ->where('payment_status', 'paid')
                ->whereDoesntHave('teamPlayer')
                ->whereHas('player', fn ($query) => $query->where('is_active', true))
                ->count();
        }

        return view('admin.auctions.show', $data);
    }

    public function store(StoreAuctionRequest $request, Edition $edition): RedirectResponse
    {
        $this->authorize('create', Auction::class);

        return $this->attempt(
            $edition,
            fn () => $this->auctions->create($edition, $request->user(), $request->settings()),
            __('Auction created. Check the rules and the players, then start it.'),
        );
    }

    public function update(UpdateAuctionRequest $request, Edition $edition): RedirectResponse
    {
        $auction = $this->auctionOf($edition);
        $this->authorize('update', $auction);

        return $this->attempt(
            $edition,
            fn () => $this->auctions->updateSettings($auction, $request->settings(), $request->teamPurses()),
            __('Auction settings saved. They apply from the next bid.'),
        );
    }

    public function refreshPool(Edition $edition): RedirectResponse
    {
        $auction = $this->auctionOf($edition);
        $this->authorize('update', $auction);

        try {
            $result = $this->auctions->refreshPool($auction);
        } catch (ValidationException $e) {
            return $this->failed($edition, $e);
        }

        return redirect()
            ->route('admin.auctions.show', $edition)
            ->with('success', __('Pool updated: :added added, :removed removed.', ['added' => $result['added'], 'removed' => $result['removed']]));
    }

    public function start(Request $request, Edition $edition): RedirectResponse
    {
        $auction = $this->auctionOf($edition);
        $this->authorize('update', $auction);

        try {
            $this->auctions->start($auction);
        } catch (ValidationException $e) {
            return $this->failed($edition, $e);
        }

        $this->notifications->started($auction->fresh(), $request->user());

        return redirect()->route('admin.auctions.show', $edition)->with('success', __('The auction is live.'));
    }

    public function pause(Edition $edition): RedirectResponse
    {
        $auction = $this->auctionOf($edition);
        $this->authorize('update', $auction);

        return $this->attempt($edition, fn () => $this->auctions->pause($auction), __('The auction is paused.'));
    }

    public function resume(Edition $edition): RedirectResponse
    {
        $auction = $this->auctionOf($edition);
        $this->authorize('update', $auction);

        return $this->attempt($edition, fn () => $this->auctions->resume($auction), __('The auction is live again.'));
    }

    public function complete(Request $request, Edition $edition): RedirectResponse
    {
        $auction = $this->auctionOf($edition);
        $this->authorize('update', $auction);

        try {
            $result = $this->auctions->complete($auction);
        } catch (ValidationException $e) {
            return $this->failed($edition, $e);
        }

        $this->notifications->completed($auction->fresh(), $request->user());

        $message = __('The auction is completed. :count players were left unsold.', ['count' => $result['unsold']]);

        if ($result['short_teams']->isNotEmpty()) {
            $message .= ' '.__('Still short of the minimum squad: :teams.', ['teams' => $result['short_teams']
                ->map(fn (array $row) => __(':team (needs :count)', ['team' => $row['edition_team']->team->name, 'count' => $row['missing']]))
                ->implode(', ')]);
        }

        return redirect()->route('admin.auctions.show', $edition)->with('success', $message);
    }

    private function auctionOf(Edition $edition): Auction
    {
        return $edition->auction ?? abort(404);
    }

    /**
     * Runs a service action and sends the person back to the season's
     * auction page with its result — or with the rule it broke.
     */
    private function attempt(Edition $edition, callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return $this->failed($edition, $e);
        }

        return redirect()->route('admin.auctions.show', $edition)->with('success', $success);
    }

    private function failed(Edition $edition, ValidationException $e): RedirectResponse
    {
        return redirect()
            ->route('admin.auctions.show', $edition)
            ->withInput()
            ->with('error', collect($e->errors())->flatten()->first());
    }
}
