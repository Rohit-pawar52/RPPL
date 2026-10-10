<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auction\StoreAuctionRequest;
use App\Http\Requests\Admin\Auction\UpdateAuctionRequest;
use App\Models\Auction;
use App\Models\Edition;
use App\Services\Auction\AuctionExportService;
use App\Services\Auction\AuctionNotificationService;
use App\Services\Auction\AuctionReadinessService;
use App\Services\Auction\AuctionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        private readonly AuctionReadinessService $readiness,
        private readonly AuctionExportService $exports,
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
            $data['readiness'] = $auction->isCompleted() ? null : $this->readiness->check($auction);
            $data['events'] = $auction->events()->with('user')->latest('id')->limit(40)->get();
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

    /**
     * Back to a fresh start (a rehearsal on the real data). Typing RESET is the confirmation: it removes every
     * sale, so a stray tap must not be enough.
     */
    public function reset(Request $request, Edition $edition): RedirectResponse
    {
        $auction = $this->auctionOf($edition);
        $this->authorize('update', $auction);

        $request->validate(['confirm' => ['required', 'in:RESET']], [
            'confirm.required' => __('Type RESET to confirm.'),
            'confirm.in' => __('Type RESET to confirm.'),
        ]);

        return $this->attempt(
            $edition,
            fn () => $this->auctions->resetAll($auction),
            __('The auction was reset: every player is waiting again and every team is empty.'),
        );
    }

    /**
     * A spreadsheet (CSV, opens in Excel) of the result, the bids, the activity log or the squads: the auction's
     * story outside the database, also the safety copy.
     */
    public function export(Edition $edition, string $what): StreamedResponse
    {
        $this->authorize('viewAny', Auction::class);
        $auction = $this->auctionOf($edition);

        $table = match ($what) {
            'results' => $this->exports->results($auction),
            'bids' => $this->exports->bids($auction),
            'events' => $this->exports->events($auction),
            default => abort(404),
        };

        $name = 'rppl-auction-'.Str::slug($edition->name).'-'.$what.'.csv';

        return response()->streamDownload(function () use ($table) {
            $out = fopen('php://output', 'w');
            // A byte-order mark makes Excel read the Hindi names correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $table['headers']);

            foreach ($table['rows'] as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The result as a printable PDF: every team's squad, what each player cost and what is left (English, like
     * the season report).
     */
    public function resultsPdf(Edition $edition): Response
    {
        $this->authorize('viewAny', Auction::class);
        $auction = $this->auctionOf($edition);

        $pdf = Pdf::loadView('admin.auctions.results-pdf', [
            'edition' => $edition,
            'auction' => $auction,
            'squads' => $this->exports->squads($auction, $this->auctions),
            'counts' => $this->auctions->counts($auction),
        ])->setPaper('a4');

        return $pdf->download('rppl-auction-'.Str::slug($edition->name).'-results.pdf');
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
