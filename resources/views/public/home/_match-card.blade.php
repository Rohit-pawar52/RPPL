{{--
    One compact match tile for the homepage's scrolling match row. Expects
    $match with teamA.team / teamB.team / venue and both innings loaded;
    optional $chase (the live payload's chase block, live match only). The
    whole tile is one link: live → Live page, finished → Scorecard,
    otherwise Match Info.
--}}
@php
    $status = $match->match_status;
    $isLive = $status === 'live';
    $isFinished = in_array($status, ['completed', 'abandoned', 'cancelled'], true);
    $chase = $chase ?? null;

    $inningsByTeam = collect([$match->firstInnings, $match->secondInnings])
        ->filter()
        ->keyBy('batting_team_id');

    $href = match (true) {
        $isLive => route('public.matches.live', $match),
        $isFinished => route('public.matches.scorecard', $match),
        default => route('public.matches.show', $match),
    };
@endphp

<a
    href="{{ $href }}"
    @class([
        'pub-card flex w-[17rem] shrink-0 snap-start flex-col gap-2 p-3 transition hover:border-green-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600',
        'border-l-4 border-l-green-500 bg-green-50/40' => $isLive,
    ])
>
    <div class="flex items-center justify-between gap-2">
        <span class="pub-meta truncate">
            @if($match->match_number)
                {{ __('matches.info.match_number', ['number' => $match->match_number]) }} &middot;
            @endif
            {{ display_datetime($match->scheduled_at, $isFinished ? 'd M' : 'D, d M') }}
        </span>
        <x-public.status-pill :status="$status" />
    </div>

    <div class="space-y-1">
        @foreach([$match->teamA, $match->teamB] as $editionTeam)
            @php $inn = $inningsByTeam->get($editionTeam->id); @endphp
            <div class="flex items-baseline justify-between gap-3">
                <span class="min-w-0 truncate text-[14px] font-semibold text-slate-900">{{ $editionTeam->team->name }}</span>
                @if($inn)
                    <span class="shrink-0 whitespace-nowrap">
                        <span class="text-[15px] font-bold tabular-nums text-slate-900">{{ $inn->total_runs }}/{{ $inn->total_wickets }}</span>
                        <span class="text-[11px] text-slate-500">({{ $inn->oversDisplay() }})</span>
                    </span>
                @endif
            </div>
        @endforeach
    </div>

    <p class="mt-auto truncate text-[12px] font-medium {{ $isFinished || $isLive ? 'text-green-700' : 'text-slate-600' }}">
        @if($isFinished)
            {{ $match->match_result ?: __('matches.common.result_unavailable') }}
        @elseif($isLive && $chase)
            {{ __('matches.chase.need_from_balls', ['runs' => $chase['runs_needed'], 'balls' => $chase['balls_remaining']]) }}
        @elseif($isLive)
            {{ __('matches.list.follow_live') }} &rarr;
        @else
            {{ display_datetime($match->scheduled_at, 'h:i A') }}
            @if($match->venue)
                &middot; {{ $match->venue->name }}
            @endif
        @endif
    </p>
</a>
