<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public venue directory/profile. Read-only, no authorization.
 */
class VenueController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));

        $venues = Venue::query()
            ->active()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('city', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('public.venues.index', [
            'venues' => $venues,
            'search' => $search,
        ]);
    }

    /**
     * Deliberately does NOT reject an inactive venue — historical
     * matches must still be able to link to where they were played.
     */
    public function show(Venue $venue): View
    {
        $venue->loadCount('matches');

        // Eager-loads mirror public.matches.index so the same
        // public.matches._list-row partial can be reused without N+1.
        $upcomingMatches = $venue->matches()
            ->whereIn('match_status', ['scheduled', 'toss', 'live'])
            ->with(['edition', 'teamA.team', 'teamB.team', 'firstInnings.battingTeam.team', 'secondInnings.battingTeam.team'])
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get();

        $completedMatches = $venue->matches()
            ->whereIn('match_status', ['completed', 'abandoned', 'cancelled'])
            ->with(['edition', 'teamA.team', 'teamB.team'])
            ->orderByDesc('scheduled_at')
            ->limit(10)
            ->get();

        return view('public.venues.show', [
            'venue' => $venue,
            'upcomingMatches' => $upcomingMatches,
            'completedMatches' => $completedMatches,
        ]);
    }
}
