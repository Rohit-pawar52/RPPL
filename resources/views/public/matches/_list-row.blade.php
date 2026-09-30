{{--
    Expects: $match, with teamA.team/teamB.team/venue eager loaded, plus
    firstInnings.battingTeam.team/secondInnings.battingTeam.team for any
    match that can be 'live' (only live rows read innings). Reused by the
    homepage, the public matches index, edition and venue pages — it only
    reads those relations, never anything a caller doesn't already load.

    Status-first layout so a list is scannable at a glance:
    live → pulsing indicator + current scores; completed/abandoned/
    cancelled → result text; scheduled/toss → date, time and venue.
--}}
@php
    $isLive = $match->match_status === 'live';
    $isFinished = in_array($match->match_status, ['completed', 'abandoned', 'cancelled'], true);
@endphp

<div class="flex items-start justify-between gap-3 border-b border-neutral-100 py-2.5 text-[13px] last:border-b-0">
    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-1.5">
            @if($isLive)
                <span class="relative flex h-2 w-2 shrink-0" aria-hidden="true">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                </span>
            @endif
            <a href="{{ route('public.matches.show', $match) }}" class="truncate font-semibold text-neutral-900 hover:underline">
                {{ $match->teamA->team->name }} {{ __('matches.common.vs') }} {{ $match->teamB->team->name }}
            </a>
        </div>

        @if($isLive)
            @if($match->firstInnings || $match->secondInnings)
                <div class="mt-1 space-y-0.5 text-[12px]">
                    @foreach([$match->firstInnings, $match->secondInnings] as $inn)
                        @if($inn)
                            <p class="flex items-baseline gap-1.5">
                                <span class="truncate text-neutral-600">{{ $inn->battingTeam->team->name }}</span>
                                <span class="font-semibold tabular-nums text-neutral-900">{{ $inn->total_runs }}/{{ $inn->total_wickets }}</span>
                                <span class="text-[11px] text-neutral-500">({{ $inn->oversDisplay() }})</span>
                            </p>
                        @endif
                    @endforeach
                </div>
            @endif
            <p class="mt-0.5 text-[11px] text-neutral-500">
                @if($match->venue)
                    {{ $match->venue->name }} &middot;
                @endif
                <a href="{{ route('public.matches.live', $match) }}" class="font-medium theme-link">{{ __('matches.list.follow_live') }} &rarr;</a>
            </p>
        @elseif($isFinished)
            @if($match->match_result)
                <p class="mt-0.5 text-[12px] font-medium theme-primary-text">{{ $match->match_result }}</p>
            @endif
            <p class="mt-0.5 text-[11px] text-neutral-500">
                {{ display_datetime($match->scheduled_at, 'd M Y') }}
                @if($match->venue)
                    &middot; {{ $match->venue->name }}
                @endif
            </p>
        @else
            <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[11px] text-neutral-500">
                <span class="inline-flex items-center gap-1">
                    <x-icon name="calendar" class="h-3 w-3 text-neutral-400" />
                    {{ display_datetime($match->scheduled_at, 'D, d M · h:i A') }}
                </span>
                @if($match->venue)
                    <span class="inline-flex min-w-0 items-center gap-1">
                        <x-icon name="map-pin" class="h-3 w-3 shrink-0 text-neutral-400" />
                        <span class="truncate">{{ $match->venue->name }}</span>
                    </span>
                @endif
            </p>
        @endif
    </div>

    <x-status-badge :status="$match->match_status" />
</div>
