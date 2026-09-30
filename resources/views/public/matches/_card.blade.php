{{--
    Reusable match card (homepage + matches index).
    Expects: $match with teamA.team / teamB.team / venue loaded. Live
    scores are read only when firstInnings/secondInnings are already
    eager loaded (never lazy-loaded here, so a list cannot N+1).

    LIVE      green edge + tint, pulsing pill, current scores, "Follow live"
    COMPLETED result line
    SCHEDULED date and time
--}}
@php
    $status = $match->match_status;
    $isLive = $status === 'live';
    $isFinished = in_array($status, ['completed', 'abandoned', 'cancelled'], true);
    $inningsByTeam = collect();
    if ($match->relationLoaded('firstInnings') && $match->relationLoaded('secondInnings')) {
        foreach ([$match->firstInnings, $match->secondInnings] as $inn) {
            if ($inn) {
                $inningsByTeam[$inn->batting_team_id] = $inn;
            }
        }
    }
    $href = $isLive ? route('public.matches.live', $match) : route('public.matches.show', $match);
@endphp

<a href="{{ $href }}" class="match-card {{ $isLive ? 'match-card-live' : '' }} focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600">
    <div class="flex items-center justify-between gap-2">
        <span class="pub-meta truncate">
            @if($match->match_number)
                {{ __('matches.info.match_number', ['number' => $match->match_number]) }} &middot;
            @endif
            {{ display_datetime($match->scheduled_at, $isFinished ? 'd M Y' : 'D, d M') }}
        </span>
        <x-public.status-pill :status="$status" />
    </div>

    <div class="space-y-2">
        @foreach([$match->teamA, $match->teamB] as $editionTeam)
            @php $inn = $inningsByTeam->get($editionTeam->id); @endphp
            <div class="flex items-baseline justify-between gap-3">
                <span class="min-w-0 truncate text-[15px] font-semibold text-slate-900">{{ $editionTeam->team->name }}</span>
                @if($inn)
                    <span class="shrink-0 whitespace-nowrap">
                        <span class="text-base font-bold tabular-nums text-slate-900">{{ $inn->total_runs }}/{{ $inn->total_wickets }}</span>
                        <span class="text-[11px] text-slate-500">({{ $inn->oversDisplay() }})</span>
                    </span>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-auto space-y-1.5">
        @if($isFinished && $match->match_result)
            <p class="text-[13px] font-semibold text-green-700">{{ $match->match_result }}</p>
        @elseif(! $isFinished)
            <p class="text-[13px] font-medium {{ $isLive ? 'text-green-700' : 'text-slate-700' }}">
                {{ $isLive ? __('matches.centre.live') : display_datetime($match->scheduled_at, 'h:i A') }}
            </p>
        @endif

        <div class="flex items-center justify-between gap-2 pub-meta">
            <span class="min-w-0 truncate">{{ $match->venue->name ?? '' }}</span>
            <span class="shrink-0 text-xs font-semibold text-green-700">{{ $isLive ? __('matches.list.follow_live') : __('public.common.view_match') }} &rarr;</span>
        </div>
    </div>
</a>
