<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Services\Finance\ContributorRankingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The full "thank you" list behind the homepage contributors slider: everyone who contributed to a season,
 * with their village and picture. Recognition only - the order comes from ContributorRankingService
 * (amount-driven), but no amount is ever shown.
 */
class ContributorController extends Controller
{
    public function __construct(private readonly ContributorRankingService $contributorRanking) {}

    public function index(Request $request): View
    {
        $editions = Edition::query()->orderByDesc('year')->get(['id', 'name', 'year']);
        $edition = $editions->firstWhere('id', (int) $request->query('edition_id')) ?? Edition::current() ?? $editions->first();

        return view('public.contributors.index', [
            'edition' => $edition,
            'editions' => $editions,
            'contributors' => $edition ? $this->contributorRanking->getEditionRanking($edition) : [],
        ]);
    }
}
