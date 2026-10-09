{{--
    Compact match row for lists inside a card (season + venue pages).
    Expects: $match with teamA.team/teamB.team/venue loaded; scores are read
    only when firstInnings/secondInnings are eager loaded by the caller. For
    the richer card grid see _card.

    live -> green accent + current scores; finished -> result text;
    scheduled -> time and venue.
--}}
@php
    $isLive = $match->match_status === 'live';
    $isFinished = in_array($match->match_status, ['completed', 'abandoned', 'cancelled'], true);
    $scores = collect();
    if ($match->relationLoaded('firstInnings') && $match->relationLoaded('secondInnings')) {
        foreach ([$match->firstInnings, $match->secondInnings] as $inn) {
            if ($inn) {
                $scores[$inn->batting_team_id] = $inn->total_runs.'/'.$inn->total_wickets;
            }
        }
    }
    $nameA = $match->teamA->team->short_name ?: $match->teamA->team->name;
    $nameB = $match->teamB->team->short_name ?: $match->teamB->team->name;
@endphp

<a href="{{ $isLive ? route('public.matches.live', $match) : route('public.matches.show', $match) }}" @class(['mx-row', 'mx-row-live' => $isLive])>
    <span class="mx-row-date">
        <b>{{ display_datetime($match->scheduled_at, 'd') }}</b>
        <i>{{ display_datetime($match->scheduled_at, 'M') }}</i>
    </span>

    <div class="min-w-0 flex-1">
        <p class="mx-row-teams">
            <x-mx.team-logo :team="$match->teamA->team" size="xs" />
            <span class="truncate sm:hidden">{{ $nameA }}</span>
            <span class="hidden truncate sm:inline">{{ $match->teamA->team->name }}</span>
            @if($scores->has($match->teamA->id))
                <span class="shrink-0 tabular-nums">{{ $scores[$match->teamA->id] }}</span>
            @endif
            <span class="mx-vs-text">{{ __('matches.common.vs') }}</span>
            <x-mx.team-logo :team="$match->teamB->team" size="xs" />
            <span class="truncate sm:hidden">{{ $nameB }}</span>
            <span class="hidden truncate sm:inline">{{ $match->teamB->team->name }}</span>
            @if($scores->has($match->teamB->id))
                <span class="shrink-0 tabular-nums">{{ $scores[$match->teamB->id] }}</span>
            @endif
        </p>

        <span class="mx-row-sub">
            @if($isFinished && $match->match_result)
                <span class="is-result">{{ $match->match_result }}</span>
            @elseif($isLive)
                <span class="font-semibold text-green-700">{{ __('matches.list.follow_live') }} &rarr;</span>
            @else
                {{ display_datetime($match->scheduled_at, 'D · h:i A') }}
            @endif
            @if($match->venue)
                &middot; {{ $match->venue->name }}
            @endif
        </span>
    </div>

    <x-public.status-pill :status="$match->match_status" class="hidden sm:inline-flex" />
    <span class="shrink-0 text-slate-300 sm:hidden" aria-hidden="true">&rsaquo;</span>
</a>
