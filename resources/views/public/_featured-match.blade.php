{{--
    Featured Current Match — the single most relevant match, larger and
    more detailed than the Match Centre strip above it. Same live/next/
    recent priority and the same LiveMatchService payload, just more of
    it (current batter/bowler comes straight from the already-fetched
    recent_deliveries feed — no second query). Expects $liveMatch/
    $liveMatchData, $nextMatch, $recentMatch (any may be null); renders
    nothing if all three are null.
--}}
@if($liveMatch || $nextMatch || $recentMatch)
    <div class="mt-3 rounded-lg border border-neutral-200 bg-white p-4 sm:mt-4">
        @if($liveMatch)
            @php $latestBall = $liveMatchData['recent_deliveries'][0] ?? null; @endphp
            <div class="flex items-center justify-between gap-2">
                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-green-700">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-500 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-green-600"></span>
                    </span>
                    Live &middot; {{ $liveMatch->teamA->team->name }} vs {{ $liveMatch->teamB->team->name }}
                </span>
                <x-status-badge :status="$liveMatch->match_status" />
            </div>

            <div class="mt-2 grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2">
                @foreach($liveMatchData['innings'] as $innings)
                    <p class="text-lg font-semibold text-neutral-900">
                        {{ $innings['batting_team'] }}
                        <span class="text-base font-normal text-neutral-600">{{ $innings['total_runs'] }}/{{ $innings['total_wickets'] }}</span>
                        <span class="text-xs font-normal text-neutral-400">({{ $innings['overs_display'] }} ov, CRR {{ number_format($innings['crr'], 2) }})</span>
                    </p>
                @endforeach
            </div>

            @if($liveMatchData['chase'])
                <p class="mt-1 text-[13px] text-neutral-700">
                    Target {{ $liveMatchData['chase']['target'] }}
                    &middot; Need {{ $liveMatchData['chase']['runs_needed'] }} from {{ $liveMatchData['chase']['balls_remaining'] }} balls
                    &middot; RRR {{ number_format($liveMatchData['chase']['required_run_rate'], 2) }}
                </p>
            @endif

            @if($latestBall)
                <p class="mt-1 text-[11px] text-neutral-500">
                    {{ $latestBall['bowler'] }} to {{ $latestBall['striker'] }} &middot; {{ $latestBall['commentary'] }}
                </p>
            @endif

            <a href="{{ route('public.matches.live', $liveMatch) }}" class="mt-3 inline-flex items-center justify-center rounded-md theme-button px-3 py-1.5 text-[13px] font-medium">
                Live Scorecard
            </a>
        @elseif($nextMatch)
            <div class="flex items-center justify-between gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-blue-700">Next Match</span>
                <x-status-badge :status="$nextMatch->match_status" />
            </div>
            <p class="mt-2 text-lg font-semibold text-neutral-900">
                {{ $nextMatch->teamA->team->name }} <span class="text-sm font-normal text-neutral-400">vs</span> {{ $nextMatch->teamB->team->name }}
            </p>
            <p class="mt-1 text-[13px] text-neutral-600">
                {{ display_datetime($nextMatch->scheduled_at, 'd M Y, h:i A') }}
                @if($nextMatch->venue)
                    &middot; {{ $nextMatch->venue->name }}
                @endif
                &middot; {{ $nextMatch->overs_per_innings }} overs
            </p>
            <a href="{{ route('public.matches.show', $nextMatch) }}" class="mt-3 inline-flex items-center justify-center rounded-md theme-button px-3 py-1.5 text-[13px] font-medium">
                Match Info
            </a>
        @elseif($recentMatch)
            <div class="flex items-center justify-between gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Recent Result</span>
                <x-status-badge :status="$recentMatch->match_status" />
            </div>

            <div class="mt-2 grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2">
                @if($recentMatch->firstInnings)
                    <p class="text-lg font-semibold text-neutral-900">
                        {{ $recentMatch->firstInnings->battingTeam->team->name }}
                        <span class="text-base font-normal text-neutral-600">{{ $recentMatch->firstInnings->total_runs }}/{{ $recentMatch->firstInnings->total_wickets }}</span>
                        <span class="text-xs font-normal text-neutral-400">({{ $recentMatch->firstInnings->oversDisplay() }} ov)</span>
                    </p>
                @endif
                @if($recentMatch->secondInnings)
                    <p class="text-lg font-semibold text-neutral-900">
                        {{ $recentMatch->secondInnings->battingTeam->team->name }}
                        <span class="text-base font-normal text-neutral-600">{{ $recentMatch->secondInnings->total_runs }}/{{ $recentMatch->secondInnings->total_wickets }}</span>
                        <span class="text-xs font-normal text-neutral-400">({{ $recentMatch->secondInnings->oversDisplay() }} ov)</span>
                    </p>
                @endif
            </div>

            <p class="mt-1 text-[13px] font-medium text-neutral-800">{{ $recentMatch->match_result ?: 'Result unavailable' }}</p>

            <a href="{{ route('public.matches.scorecard', $recentMatch) }}" class="mt-3 inline-flex items-center justify-center rounded-md theme-button px-3 py-1.5 text-[13px] font-medium">
                Full Scorecard
            </a>
        @endif
    </div>
@endif
