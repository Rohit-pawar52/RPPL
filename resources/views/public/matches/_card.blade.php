{{--
    Match card for the matches list.
    Expects: $match with teamA.team / teamB.team / venue loaded. Scores are
    read only when firstInnings/secondInnings are already eager loaded (never
    lazy-loaded here, so a list cannot N+1).

    LIVE       green accent edge, pulsing pill, current scores, "Follow live"
    COMPLETED  scores with the winner emphasised, result line
    SCHEDULED  date and time
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
    $winnerId = $status === 'completed' ? $match->winner_team_id : null;
    $href = $isLive ? route('public.matches.live', $match) : route('public.matches.show', $match);
@endphp

<a href="{{ $href }}" @class(['mx-card', 'mx-card-live' => $isLive])>
    <div class="flex items-center justify-between gap-2">
        <span class="mx-card-meta">
            @if($match->match_number)
                {{ __('matches.info.match_number', ['number' => $match->match_number]) }}
                @if($match->match_stage && $match->match_stage !== 'league')
                    &middot; {{ ucwords(str_replace('_', ' ', $match->match_stage)) }}
                @endif
            @else
                {{ display_datetime($match->scheduled_at, 'd M Y') }}
            @endif
        </span>
        <x-public.status-pill :status="$status" />
    </div>

    <div>
        @foreach([$match->teamA, $match->teamB] as $editionTeam)
            @php
                $inn = $inningsByTeam->get($editionTeam->id);
                $isLoser = $winnerId && (int) $winnerId !== (int) $editionTeam->id;
            @endphp
            <div @class(['mx-card-team', 'is-loser' => $isLoser])>
                <x-mx.team-logo :team="$editionTeam->team" size="sm" />
                <span class="mx-card-name">{{ $editionTeam->team->name }}</span>
                @if($inn)
                    <span class="mx-card-score">{{ $inn->total_runs }}/{{ $inn->total_wickets }}<small>({{ $inn->oversDisplay() }})</small></span>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mx-card-foot">
        @if($isFinished && $match->match_result)
            <p class="mx-card-result">{{ $match->match_result }}</p>
        @elseif($isLive)
            <p class="mx-card-when">{{ __('matches.centre.live') }}</p>
        @elseif(! $isFinished)
            <p class="mx-card-when">
                <x-icon name="calendar" class="size-4 shrink-0 text-slate-400" />
                {{ display_datetime($match->scheduled_at, 'D, d M · h:i A') }}
            </p>
        @endif

        <div class="mx-card-venue">
            <span class="min-w-0 truncate">{{ $match->venue->name ?? '' }}</span>
            <span class="mx-card-go">{{ $isLive ? __('matches.list.follow_live') : ($isFinished ? __('matches.centre.scorecard') : __('public.common.view_match')) }} &rarr;</span>
        </div>
    </div>
</a>
