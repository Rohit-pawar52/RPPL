{{--
    Expects: $match, with teamA.team/teamB.team/venue and
    firstInnings.battingTeam.team/secondInnings.battingTeam.team eager
    loaded. Reused by the homepage and the public matches index.
--}}
<div class="flex items-center justify-between gap-3 border-b border-neutral-100 py-2 text-[13px] last:border-b-0">
    <div class="min-w-0">
        <a href="{{ route('public.matches.show', $match) }}" class="font-medium text-neutral-800 hover:underline">
            {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
        </a>
        <p class="text-[11px] text-neutral-500">
            {{ display_datetime($match->scheduled_at, 'd M Y, h:i A') }}
            @if($match->venue)
                &middot; {{ $match->venue->name }}
            @endif
        </p>

        @if($match->match_status === 'live')
            <p class="mt-0.5 text-[11px] text-neutral-600">
                @if($match->firstInnings)
                    {{ $match->firstInnings->battingTeam->team->name }} {{ $match->firstInnings->total_runs }}/{{ $match->firstInnings->total_wickets }} ({{ $match->firstInnings->oversDisplay() }})
                @endif
                @if($match->secondInnings)
                    &middot; {{ $match->secondInnings->battingTeam->team->name }} {{ $match->secondInnings->total_runs }}/{{ $match->secondInnings->total_wickets }} ({{ $match->secondInnings->oversDisplay() }})
                @endif
            </p>
        @elseif(in_array($match->match_status, ['completed', 'abandoned', 'cancelled'], true) && $match->match_result)
            <p class="mt-0.5 text-[11px] text-neutral-600">{{ $match->match_result }}</p>
        @endif
    </div>

    <x-status-badge :status="$match->match_status" />
</div>
