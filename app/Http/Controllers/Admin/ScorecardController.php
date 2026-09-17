<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Services\Scoring\ScorecardService;
use Illuminate\View\View;

/**
 * Read-only match scorecard. No writes happen through this controller
 * — all calculation lives in ScorecardService, which is deliberately
 * framework-agnostic so a future public-website controller can reuse
 * it without duplicating any scorecard logic.
 */
class ScorecardController extends Controller
{
    public function __construct(private readonly ScorecardService $scorecards) {}

    public function show(GameMatch $match): View
    {
        $this->authorize('view', $match);

        $match->load(['edition', 'teamA.team', 'teamB.team', 'venue']);

        return view('admin.matches.scorecard', [
            'match' => $match,
            'inningsScorecards' => $this->scorecards->getMatchScorecard($match),
        ]);
    }
}
