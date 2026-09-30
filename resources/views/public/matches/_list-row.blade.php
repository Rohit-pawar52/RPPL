{{--
    Compact match row for lists inside a card (edition + venue pages).
    Expects: $match with teamA.team/teamB.team/venue loaded; innings are
    read only for a live match when firstInnings/secondInnings are eager
    loaded by the caller. For the richer card grid see _card.

    live -> green accent + current scores; finished -> result text;
    scheduled -> date, time and venue.
--}}
@php
    $isLive = $match->match_status === 'live';
    $isFinished = in_array($match->match_status, ['completed', 'abandoned', 'cancelled'], true);
@endphp

<a href="{{ $isLive ? route('public.matches.live', $match) : route('public.matches.show', $match) }}" class="match-row {{ $isLive ? 'border-l-4 border-l-green-500 bg-green-50/50' : '' }}">
    <div class="min-w-0 flex-1">
        <p class="truncate font-semibold text-slate-900">
            {{ $match->teamA->team->name }} {{ __('matches.common.vs') }} {{ $match->teamB->team->name }}
        </p>

        @if($isLive && $match->relationLoaded('firstInnings') && ($match->firstInnings || $match->secondInnings))
            <div class="mt-1 space-y-0.5 text-xs">
                @foreach([$match->firstInnings, $match->secondInnings] as $inn)
                    @if($inn)
                        <p class="flex items-baseline gap-1.5">
                            <span class="truncate text-slate-600">{{ $inn->battingTeam->team->name }}</span>
                            <span class="font-semibold tabular-nums text-slate-900">{{ $inn->total_runs }}/{{ $inn->total_wickets }}</span>
                            <span class="text-[11px] text-slate-500">({{ $inn->oversDisplay() }})</span>
                        </p>
                    @endif
                @endforeach
            </div>
        @endif

        @if($isFinished && $match->match_result)
            <p class="mt-0.5 text-xs font-medium text-green-700">{{ $match->match_result }}</p>
        @endif

        <p class="pub-meta mt-0.5 truncate">
            {{ display_datetime($match->scheduled_at, $isFinished ? 'd M Y' : 'D, d M · h:i A') }}
            @if($match->venue)
                &middot; {{ $match->venue->name }}
            @endif
            @if($isLive)
                &middot; <span class="font-semibold text-green-700">{{ __('matches.list.follow_live') }} &rarr;</span>
            @endif
        </p>
    </div>

    <x-public.status-pill :status="$match->match_status" />
</a>
