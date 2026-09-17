<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\GameMatch;
use App\Services\LiveMatch\LiveMatchService;
use App\Services\Scoring\ScorecardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Public match listing/detail/scorecard/live pages. Read-only, no
 * authorization. The scorecard consumes the exact same ScorecardService
 * the admin scorecard uses, and the Live Match Center consumes
 * LiveMatchService — never a second batting/bowling/scoring engine.
 */
class MatchController extends Controller
{
    public function __construct(
        private readonly ScorecardService $scorecards,
        private readonly LiveMatchService $liveMatch,
    ) {}

    public function index(Request $request): View
    {
        $editionId = $request->integer('edition_id') ?: null;

        $upcoming = GameMatch::query()
            ->whereIn('match_status', ['scheduled', 'toss', 'live'])
            ->when($editionId, fn ($query, $id) => $query->where('edition_id', $id))
            ->with([
                'edition', 'teamA.team', 'teamB.team', 'venue',
                'firstInnings.battingTeam.team', 'secondInnings.battingTeam.team',
            ])
            ->orderBy('scheduled_at')
            ->get();

        $past = GameMatch::query()
            ->whereIn('match_status', ['completed', 'abandoned', 'cancelled'])
            ->when($editionId, fn ($query, $id) => $query->where('edition_id', $id))
            ->with(['edition', 'teamA.team', 'teamB.team', 'venue'])
            ->orderByDesc('scheduled_at')
            ->paginate(15)
            ->withQueryString();

        return view('public.matches.index', [
            'upcoming' => $upcoming,
            'past' => $past,
            'editions' => Edition::orderByDesc('year')->get(['id', 'name']),
            'selectedEditionId' => $editionId,
        ]);
    }

    public function show(GameMatch $match): View
    {
        $match->load([
            'edition', 'teamA.team', 'teamB.team', 'venue', 'tossWinner.team', 'winner.team',
            'firstInnings.battingTeam.team', 'firstInnings.bowlingTeam.team',
            'secondInnings.battingTeam.team', 'secondInnings.bowlingTeam.team',
        ]);
        $match->loadCount('innings');

        return view('public.matches.show', [
            'match' => $match,
        ]);
    }

    public function scorecard(GameMatch $match): View|RedirectResponse
    {
        if (! $match->innings()->exists()) {
            return redirect()
                ->route('public.matches.show', $match)
                ->with('info', 'Scorecard will be available once the match begins.');
        }

        $match->load(['edition', 'teamA.team', 'teamB.team']);

        return view('public.matches.scorecard', [
            'match' => $match,
            'inningsScorecards' => $this->scorecards->getMatchScorecard($match),
        ]);
    }

    /**
     * PDF snapshot of the same scorecard() consumes — same availability
     * rule (redirect, never a fake empty PDF, when no Innings exists yet)
     * and the same ScorecardService data, just rendered by a standalone
     * PDF-only Blade instead of the responsive web layout.
     */
    public function scorecardPdf(GameMatch $match): Response|RedirectResponse
    {
        if (! $match->innings()->exists()) {
            return redirect()
                ->route('public.matches.show', $match)
                ->with('info', 'Scorecard will be available once the match begins.');
        }

        $match->load(['edition', 'teamA.team', 'teamB.team', 'venue', 'tossWinner.team']);

        $pdf = Pdf::loadView('public.matches.scorecard-pdf', [
            'match' => $match,
            'inningsScorecards' => $this->scorecards->getMatchScorecard($match),
        ])->setPaper('a4');

        $filename = 'rppl-'.Str::slug($match->teamA->team->name).'-vs-'.Str::slug($match->teamB->team->name).'-scorecard.pdf';

        return $pdf->download($filename);
    }

    public function live(GameMatch $match): View|RedirectResponse
    {
        if (! $this->liveMatch->isAvailable($match)) {
            return redirect()
                ->route('public.matches.show', $match)
                ->with('info', 'Ball-by-ball coverage will be available once scoring begins.');
        }

        $match->load(['edition', 'teamA.team', 'teamB.team', 'venue', 'tossWinner.team']);

        return view('public.matches.live', [
            'match' => $match,
            'liveData' => $this->liveMatch->getLiveMatchData($match),
        ]);
    }

    /**
     * The one intentional JSON endpoint in this phase — polled by the
     * live page's JS. Returns only the explicit public-safe fields
     * LiveMatchService builds; never a serialized Eloquent model.
     */
    public function liveData(GameMatch $match): JsonResponse
    {
        return response()->json($this->liveMatch->getLiveMatchData($match));
    }
}
