<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionLot;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Services\Auction\AuctionNotificationService;
use App\Services\Auction\AuctionService;
use App\Services\Auction\AuctionStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The live auction console, for the admin and the auctioneer (AuctionPolicy).
 * A page that draws itself from one state array, plus small JSON endpoints —
 * one per action — that each return the fresh state. The rules all live in
 * AuctionService; a rule failure comes back as a 422 with a message and the
 * current state, so the screen can show it and correct itself. The "reserve"
 * failure is the one the console can offer to override.
 */
class AuctionConsoleController extends Controller
{
    public function __construct(
        private readonly AuctionService $auctions,
        private readonly AuctionStateService $states,
        private readonly AuctionNotificationService $notifications,
    ) {}

    public function show(Edition $edition): View|RedirectResponse
    {
        $auction = $this->auctionOf($edition);

        if ($auction->isDraft()) {
            return redirect()
                ->route('admin.auctions.show', $edition)
                ->with('error', 'Start the auction before opening the console.');
        }

        return view('admin.auctions.console', [
            'edition' => $edition,
            'auction' => $auction,
            'state' => $this->states->console($auction),
        ]);
    }

    public function state(Edition $edition): JsonResponse
    {
        $auction = $this->auctionOf($edition);

        return response()->json(['ok' => true, 'state' => $this->states->console($auction)]);
    }

    /**
     * Calls the next random waiting player to the block.
     */
    public function random(Edition $edition): JsonResponse
    {
        $auction = $this->auctionOf($edition);

        return $this->respond($auction, function () use ($auction) {
            if ($this->auctions->callRandom($auction) === null) {
                return 'Nobody is waiting. Start the next round to bring the hold players back.';
            }

            return null;
        });
    }

    public function call(Request $request, Edition $edition): JsonResponse
    {
        $auction = $this->auctionOf($edition);
        $data = $request->validate(['lot_id' => ['required', 'integer']]);

        return $this->respond($auction, function () use ($auction, $data) {
            $this->auctions->callLot($auction, $this->lotOf($auction, $data['lot_id']));
        });
    }

    public function bid(Request $request, Edition $edition): JsonResponse
    {
        $auction = $this->auctionOf($edition);
        $data = $request->validate([
            'lot_id' => ['required', 'integer'],
            'version' => ['required', 'integer'],
            'team_id' => ['required', 'integer'],
            'amount' => ['nullable', 'integer', 'min:1'],
            'key' => ['nullable', 'string', 'max:64'],
            'override' => ['nullable', 'boolean'],
        ]);

        return $this->respond($auction, function () use ($auction, $data, $request) {
            $team = EditionTeam::query()->where('edition_id', $auction->edition_id)->find($data['team_id']);

            if (! $team) {
                throw ValidationException::withMessages(['bid' => 'That team is not in this season.']);
            }

            $this->auctions->placeBid(
                $auction,
                $this->lotOf($auction, $data['lot_id']),
                $team,
                (int) $data['version'],
                isset($data['amount']) ? (int) $data['amount'] : null,
                $data['key'] ?? null,
                (bool) ($data['override'] ?? false),
                $request->user(),
            );
        });
    }

    public function undo(Request $request, Edition $edition): JsonResponse
    {
        return $this->onLot($request, $edition, fn (Auction $auction, AuctionLot $lot, int $version) => $this->auctions->undoBid($auction, $lot, $version));
    }

    public function sell(Request $request, Edition $edition): JsonResponse
    {
        return $this->onLot($request, $edition, function (Auction $auction, AuctionLot $lot, int $version) use ($request) {
            $this->auctions->sell($auction, $lot, $version);

            // A sale of at least the auction's minimum is pushed (if it has one).
            $this->notifications->sold($auction->fresh(), $lot->fresh(), $request->user());
        });
    }

    public function hold(Request $request, Edition $edition): JsonResponse
    {
        return $this->onLot($request, $edition, fn (Auction $auction, AuctionLot $lot, int $version) => $this->auctions->hold($auction, $lot, $version));
    }

    public function release(Request $request, Edition $edition): JsonResponse
    {
        return $this->onLot($request, $edition, fn (Auction $auction, AuctionLot $lot, int $version) => $this->auctions->release($auction, $lot, $version));
    }

    public function reopen(Request $request, Edition $edition): JsonResponse
    {
        $auction = $this->auctionOf($edition);
        $data = $request->validate(['lot_id' => ['required', 'integer']]);

        return $this->respond($auction, function () use ($auction, $data) {
            $this->auctions->reopenSold($auction, $this->lotOf($auction, $data['lot_id']));
        });
    }

    public function nextRound(Edition $edition): JsonResponse
    {
        $auction = $this->auctionOf($edition);

        return $this->respond($auction, function () use ($auction) {
            $back = $this->auctions->startNextRound($auction);

            return $back === 1 ? 'Round '.($auction->fresh()->round).' started — 1 player is back.' : 'Round '.($auction->fresh()->round)." started — {$back} players are back.";
        });
    }

    public function liveBids(Request $request, Edition $edition): JsonResponse
    {
        $auction = $this->auctionOf($edition);
        $data = $request->validate(['show' => ['required', 'boolean']]);

        return $this->respond($auction, function () use ($auction, $data) {
            $this->auctions->updateSettings($auction, ['show_live_bids' => (bool) $data['show']]);
        });
    }

    public function pause(Edition $edition): JsonResponse
    {
        $auction = $this->auctionOf($edition);

        return $this->respond($auction, function () use ($auction) {
            $this->auctions->pause($auction);
        });
    }

    public function resume(Edition $edition): JsonResponse
    {
        $auction = $this->auctionOf($edition);

        return $this->respond($auction, function () use ($auction) {
            $this->auctions->resume($auction);
        });
    }

    public function walkIn(Request $request, Edition $edition): JsonResponse
    {
        $auction = $this->auctionOf($edition);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
        ]);

        return $this->respond($auction, function () use ($auction, $data) {
            $lot = $this->auctions->addWalkInPlayer($auction, $data['name'], $data['phone']);

            return $lot->playerRegistration->player->name.' is added to the waiting players.';
        });
    }

    // ----- Helpers ----------------------------------------------------------

    /**
     * A change to the player on the block: needs which player and the version
     * of them the screen was showing.
     *
     * @param  callable(Auction, AuctionLot, int): mixed  $action
     */
    private function onLot(Request $request, Edition $edition, callable $action): JsonResponse
    {
        $auction = $this->auctionOf($edition);
        $data = $request->validate([
            'lot_id' => ['required', 'integer'],
            'version' => ['required', 'integer'],
        ]);

        return $this->respond($auction, function () use ($action, $auction, $data) {
            $action($auction, $this->lotOf($auction, $data['lot_id']), (int) $data['version']);
        });
    }

    /**
     * Runs an action and answers with the fresh state — or, when a rule is
     * broken, with the message, which rule it was and the state as it is now.
     * An action may return a short message to show.
     */
    private function respond(Auction $auction, callable $action): JsonResponse
    {
        try {
            $message = $action();
        } catch (ValidationException $e) {
            $errors = $e->errors();

            return response()->json([
                'ok' => false,
                'key' => array_key_first($errors),
                'message' => collect($errors)->flatten()->first(),
                'state' => $this->states->console($auction),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => is_string($message) ? $message : null,
            'state' => $this->states->console($auction),
        ]);
    }

    private function auctionOf(Edition $edition): Auction
    {
        $auction = $edition->auction ?? abort(404);
        $this->authorize('update', $auction);

        return $auction;
    }

    private function lotOf(Auction $auction, int $lotId): AuctionLot
    {
        return $auction->lots()->find($lotId)
            ?? throw ValidationException::withMessages(['stale' => 'That player is no longer part of this auction. The screen has been refreshed.']);
    }
}
