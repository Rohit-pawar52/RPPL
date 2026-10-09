{{--
    One match tile for the homepage's scrolling match row. Expects $match with
    teamA.team / teamB.team / venue and both innings loaded; optional $chase
    (the live payload's chase block, live match only). The whole tile is one
    link: live -> Live page, finished -> Scorecard, otherwise Match Info. A
    live match is the navy tile with its chase line; a finished one marks the
    winner and says "Scorecard".
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

    $cta = match (true) {
        $isLive => __('ux_public_shell.home.watch_live'),
        $isFinished => __('home.strip.scorecard'),
        default => __('home.strip.match_info'),
    };
@endphp

<a href="{{ $href }}" @class(['ps-match w-[18rem]', 'ps-match-live' => $isLive])>
    <div class="flex items-center justify-between gap-2">
        <span @class(['truncate text-[11px] font-semibold uppercase tracking-wide', 'text-white/60' => $isLive, 'text-slate-400' => ! $isLive])>
            @if($match->match_number)
                {{ __('matches.info.match_number', ['number' => $match->match_number]) }} &middot;
            @endif
            {{ display_datetime($match->scheduled_at, $isFinished ? 'd M' : 'D, d M') }}
        </span>
        <x-public.status-pill :status="$status" />
    </div>

    <div class="space-y-2.5">
        @foreach([$match->teamA, $match->teamB] as $editionTeam)
            @php
                $inn = $inningsByTeam->get($editionTeam->id);
                $won = $isFinished && $match->winner_team_id && $match->winner_team_id === $editionTeam->id;
                $dim = $isFinished && ! $won && ! $isLive;
            @endphp
            <div class="flex items-center gap-3">
                <x-public.team-logo :team="$editionTeam->team" class="size-9" />
                <span @class(['min-w-0 flex-1 truncate text-[15px]', 'font-semibold' => ! $dim, 'font-medium' => $dim, 'text-white' => $isLive, 'text-slate-500' => $dim, 'text-slate-900' => ! $isLive && ! $dim])>{{ $editionTeam->team->name }}</span>
                @if($inn)
                    <span class="shrink-0 whitespace-nowrap text-right">
                        <span @class(['text-xl font-bold tabular-nums', 'text-white' => $isLive, 'text-slate-500' => $dim, 'text-slate-900' => ! $isLive && ! $dim])>{{ $inn->total_runs }}/{{ $inn->total_wickets }}</span>
                        <span @class(['text-[11px]', 'text-white/60' => $isLive, 'text-slate-400' => ! $isLive])>({{ $inn->oversDisplay() }})</span>
                    </span>
                @endif
            </div>
        @endforeach
    </div>

    <div @class(['mt-auto flex items-center justify-between gap-3 border-t pt-3', 'border-white/10' => $isLive, 'border-line' => ! $isLive])>
        <p @class(['min-w-0 truncate text-[12px] font-medium', 'text-accent-dark' => $isLive, 'text-brand' => $isFinished, 'text-slate-600' => ! $isLive && ! $isFinished])>
            @if($isFinished)
                {{ $match->match_result ?: __('matches.common.result_unavailable') }}
            @elseif($isLive && $chase)
                {{ __('matches.chase.need_from_balls', ['runs' => $chase['runs_needed'], 'balls' => $chase['balls_remaining']]) }}
            @elseif($isLive)
                {{ __('matches.list.follow_live') }}
            @else
                {{ display_datetime($match->scheduled_at, 'h:i A') }}
                @if($match->venue)
                    &middot; {{ $match->venue->name }}
                @endif
            @endif
        </p>
        <span @class(['inline-flex shrink-0 items-center gap-1 text-xs font-semibold', 'text-white' => $isLive, 'text-link' => ! $isLive])>
            <span @class(['sr-only' => ! $isLive && ! $isFinished])>{{ $cta }}</span>
            <x-icon name="arrow-right" class="h-3.5 w-3.5" />
        </span>
    </div>
</a>
