{{--
    Shared match page header: the score header (two teams with crests and
    scores, status, result / chase line, venue, time and toss) followed by the
    sticky tabs.
    Expects: $match (teamA.team, teamB.team, edition, venue loaded; innings_count
    loaded) and $active (tab key). Whatever else it needs (innings, toss
    winner) is loaded here once - it is a single match, never a list.
    $liveStatus = true on the Live page only: the score slots, the status
    pill, the result line and the chase panel then carry the ids/data hooks
    public-live-match.js updates while polling, and the numbers come from the
    same $liveData payload that script fetches.
--}}
@php
    $liveStatus = $liveStatus ?? false;
    $liveData = $liveData ?? null;
    $liveStatus = $liveStatus && $liveData !== null;

    $match->loadMissing(['edition', 'venue', 'tossWinner.team']);
    if (! $liveStatus) {
        $match->loadMissing(['firstInnings', 'secondInnings']);
    }

    $statusValue = $liveStatus ? ($liveData['match_status'] ?? $match->match_status) : $match->match_status;
    $isLive = $statusValue === 'live';
    $isFinished = in_array($statusValue, ['completed', 'abandoned', 'cancelled'], true);

    // Score of each side, in one shape for the page render and the live payload.
    $scoreOf = function ($editionTeam) use ($liveStatus, $liveData, $match) {
        if ($liveStatus) {
            $row = collect($liveData['innings'])->firstWhere('batting_team', $editionTeam->team->name);

            return $row ? [
                'runs' => $row['total_runs'],
                'wickets' => $row['total_wickets'],
                'overs' => $row['overs_display'],
                'crr' => $row['crr'] ?? null,
                'batting' => $row['status'] === 'live',
            ] : null;
        }

        $innings = collect([$match->firstInnings, $match->secondInnings])->filter()->firstWhere('batting_team_id', $editionTeam->id);

        return $innings ? [
            'runs' => $innings->total_runs,
            'wickets' => $innings->total_wickets,
            'overs' => $innings->oversDisplay(),
            'crr' => null,
            'batting' => false,
        ] : null;
    };

    $outcomeOf = fn ($editionTeam) => $statusValue === 'completed' && $match->winner_team_id
        ? ((int) $match->winner_team_id === (int) $editionTeam->id ? 'won' : 'lost')
        : null;

    $chase = $liveStatus ? ($liveData['chase'] ?? null) : null;
    $chasingTeam = $liveStatus ? (collect($liveData['innings'])->firstWhere('innings_number', 2)['batting_team'] ?? null) : null;

    $resultText = $liveStatus ? ($liveData['match_result'] ?? null) : ($isFinished ? $match->match_result : null);
    $startsAt = ! $isLive && ! $isFinished && $match->scheduled_at && $match->scheduled_at->isFuture() ? $match->scheduled_at : null;
    $shareTitle = $match->teamA->team->name.' '.__('matches.common.vs').' '.$match->teamB->team->name;
@endphp

<header @class(['mx-hero', 'mx-hero-live' => $isLive])>
    <div class="mx-hero-glow" aria-hidden="true"></div>

    <div class="relative px-4 pt-2.5 sm:px-5 sm:pt-3">
        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('public.matches.index') }}" class="mx-hero-back">
                <span aria-hidden="true">&larr;</span> {{ __('public.common.all_matches') }}
            </a>

            <div class="flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    class="mx-hero-icon-btn"
                    data-mx-share
                    data-share-title="{{ $shareTitle }}"
                    data-copied="{{ __('ux_public_matches.match.link_copied') }}"
                    aria-label="{{ __('ux_public_matches.match.share') }}"
                    title="{{ __('ux_public_matches.match.share') }}"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><circle cx="18" cy="5" r="2.5" /><circle cx="6" cy="12" r="2.5" /><circle cx="18" cy="19" r="2.5" /><path d="m8.2 10.8 7.6-4.4M8.2 13.2l7.6 4.4" /></svg>
                </button>
                <span @if($liveStatus) id="live-status-badge" @endif>
                    <x-public.status-pill :status="$statusValue" />
                </span>
            </div>
        </div>

        <p class="mx-hero-context">
            <span>{{ $match->edition->name }}</span>
            @if($match->match_number)
                <span>{{ __('matches.info.match_number', ['number' => $match->match_number]) }}</span>
            @endif
            @if($match->match_stage)
                <span>{{ ucwords(str_replace('_', ' ', $match->match_stage)) }}</span>
            @endif
            <span>{{ __('matches.common.overs_count', ['overs' => $match->overs_per_innings]) }}</span>
        </p>
    </div>

    <h1 class="sr-only">{{ $shareTitle }}</h1>

    <div @if($liveStatus) id="live-innings" aria-live="polite" @endif class="relative grid gap-3 px-4 py-3 sm:grid-cols-[1fr_auto_1fr] sm:items-center sm:gap-5 sm:px-5 sm:py-3.5">
        @include('public.matches._score-team', ['team' => $match->teamA, 'score' => $scoreOf($match->teamA), 'outcome' => $outcomeOf($match->teamA), 'side' => 'a'])

        <div class="mx-vs" aria-hidden="true"><span>{{ __('matches.common.vs') }}</span></div>

        @include('public.matches._score-team', ['team' => $match->teamB, 'score' => $scoreOf($match->teamB), 'outcome' => $outcomeOf($match->teamB), 'side' => 'b'])
    </div>

    <div class="relative space-y-2 border-t border-white/10 px-4 py-2.5 sm:px-5">
        @if($liveStatus)
            <p id="live-match-result" class="mx-result {{ $resultText ? '' : 'hidden' }}">{{ $resultText }}</p>

            <div id="live-chase" class="{{ $chase ? '' : 'hidden' }}" aria-live="polite">
                @if($chase)
                    @include('public.matches._live-chase', ['chase' => $chase, 'chasingTeam' => $chasingTeam])
                @endif
            </div>
        @elseif($resultText)
            <p class="mx-result">{{ $resultText }}</p>
        @elseif($startsAt)
            <p class="mx-result">
                {{ __('ux_public_matches.match.starts_in') }}
                <span class="tabular-nums" data-mx-countdown="{{ $startsAt->toIso8601String() }}" data-day="{{ __('ux_public_matches.match.d') }}" data-hour="{{ __('ux_public_matches.match.h') }}" data-minute="{{ __('ux_public_matches.match.m') }}" data-soon="{{ __('ux_public_matches.match.starting_soon') }}">{{ display_datetime($startsAt, 'D, d M · h:i A') }}</span>
            </p>
        @endif

        <ul class="mx-hero-meta">
            @if($match->scheduled_at)
                <li>
                    <x-icon name="calendar" class="size-4 shrink-0" />
                    <span>{{ display_datetime($match->scheduled_at, 'D, d M Y · h:i A') }}</span>
                </li>
            @endif
            @if($match->venue)
                <li>
                    <x-icon name="map-pin" class="size-4 shrink-0" />
                    <a href="{{ route('public.venues.show', $match->venue) }}" class="hover:text-white hover:underline">{{ $match->venue->name }}</a>
                </li>
            @endif
            @if($match->tossWinner)
                <li>
                    <x-icon name="trophy" class="size-4 shrink-0" />
                    <span>{{ __('matches.info.toss_result', ['team' => $match->tossWinner->team->name, 'decision' => __('matches.info.toss_decision.'.$match->toss_decision)]) }}</span>
                </li>
            @endif
        </ul>
    </div>
</header>

@include('public.matches._match-tabs', ['match' => $match, 'active' => $active])

@include('public.matches._hero-scripts')
