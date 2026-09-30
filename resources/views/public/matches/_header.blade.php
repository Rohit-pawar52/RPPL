{{--
    Shared match page header: back link, "Team A vs Team B" title,
    format · match no. · venue subtitle, status pill, then the tabs.
    Expects: $match (teamA.team, teamB.team, venue, edition loaded;
    innings_count loaded) and $active (tab key). $liveStatus = true on
    the Live page only, which wraps the pill in #live-status-badge so
    public-live-match.js can swap it while polling.
--}}
@php
    $liveStatus = $liveStatus ?? false;
    $statusValue = $liveStatus ? ($liveData['match_status'] ?? $match->match_status) : $match->match_status;
@endphp

<header class="pub-card mb-4 overflow-hidden">
    <div class="px-4 pt-3 sm:px-5 sm:pt-4">
        <a href="{{ route('public.matches.index') }}" class="inline-flex min-h-6 items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-900">
            <span aria-hidden="true">&larr;</span> {{ __('public.common.all_matches') }}
        </a>

        <div class="mt-1 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="pub-h1 break-words text-lg sm:text-xl">
                    {{ $match->teamA->team->name }} {{ __('matches.common.vs') }} {{ $match->teamB->team->name }}
                </h1>
                <p class="pub-meta mt-0.5">
                    {{ __('matches.common.overs_count', ['overs' => $match->overs_per_innings]) }}
                    @if($match->match_number)
                        &middot; {{ __('matches.info.match_number', ['number' => $match->match_number]) }}
                    @endif
                    @if($match->venue)
                        &middot; {{ $match->venue->name }}
                    @endif
                </p>
            </div>
            <div class="shrink-0 pt-0.5" @if($liveStatus) id="live-status-badge" @endif>
                <x-public.status-pill :status="$statusValue" />
            </div>
        </div>
    </div>

    <div class="mt-2">
        @include('public.matches._match-tabs', ['match' => $match, 'active' => $active])
    </div>
</header>
