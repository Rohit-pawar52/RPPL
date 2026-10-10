{{--
    The Live tab's score card: status, the two teams with their score, the result or the chase. One small card in
    place of the big dark match header. The ids / data hooks are the ones resources/js/public-live-match.js updates
    while polling (data-live-team + data-slot, #live-status-badge, #live-match-result, #live-chase).
    Expects: $match (teamA.team, teamB.team, edition loaded) and $liveData (LiveMatchService payload).
--}}
@php
    $statusValue = $liveData['match_status'] ?? $match->match_status;
    $shareTitle = $match->teamA->team->name.' '.__('matches.common.vs').' '.$match->teamB->team->name;
    $chase = $liveData['chase'] ?? null;
    $chasingTeam = collect($liveData['innings'])->firstWhere('innings_number', 2)['batting_team'] ?? null;
    $resultText = $liveData['match_result'] ?? null;

    $scoreOf = function ($editionTeam) use ($liveData) {
        $row = collect($liveData['innings'])->firstWhere('batting_team', $editionTeam->team->name);

        return $row ? [
            'runs' => $row['total_runs'],
            'wickets' => $row['total_wickets'],
            'overs' => $row['overs_display'],
            'crr' => $row['crr'] ?? null,
            'batting' => $row['status'] === 'live',
        ] : null;
    };
@endphp

<section id="live-score-card" @class(['pub-card mx-lsc', 'mx-hero-live' => $statusValue === 'live']) aria-live="polite">
    <h1 class="sr-only">{{ $shareTitle }}</h1>

    <div class="mx-lsc-top">
        <span id="live-status-badge"><x-public.status-pill :status="$statusValue" /></span>
        <p class="mx-lsc-context">
            <span>{{ $match->edition->name }}</span>
            @if($match->match_number)
                <span>{{ __('matches.info.match_number', ['number' => $match->match_number]) }}</span>
            @endif
            <span>{{ __('matches.common.overs_count', ['overs' => $match->overs_per_innings]) }}</span>
        </p>
        <button
            type="button"
            class="mx-lsc-share"
            data-mx-share
            data-share-title="{{ $shareTitle }}"
            data-copied="{{ __('ux_public_matches.match.link_copied') }}"
            aria-label="{{ __('ux_public_matches.match.share') }}"
            title="{{ __('ux_public_matches.match.share') }}"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><circle cx="18" cy="5" r="2.5" /><circle cx="6" cy="12" r="2.5" /><circle cx="18" cy="19" r="2.5" /><path d="m8.2 10.8 7.6-4.4M8.2 13.2l7.6 4.4" /></svg>
        </button>
    </div>

    <div class="mx-lsc-teams">
    @foreach([$match->teamA, $match->teamB] as $editionTeam)
        @php
            $score = $scoreOf($editionTeam);
            $showCrr = $score && $score['batting'] && ($score['crr'] ?? null) !== null;
            $won = $statusValue === 'completed' && $match->winner_team_id && (int) $match->winner_team_id === (int) $editionTeam->id;
        @endphp
        <div class="mx-lsc-team" data-live-team="{{ $editionTeam->team->name }}" data-outcome="{{ $won ? 'won' : '' }}" @if($score && $score['batting']) data-batting="1" @endif>
            <x-mx.team-logo :team="$editionTeam->team" size="sm" />
            <p class="mx-lsc-name">
                <span class="line-clamp-2">{{ $editionTeam->team->name }}</span>
                <span class="mx-batting-tag {{ $score && $score['batting'] ? '' : 'hidden' }}" data-slot="batting">{{ __('ux_public_matches.match.batting') }}</span>
            </p>
            <div class="mx-team-score mx-lsc-score {{ $score ? '' : 'hidden' }}">
                <span class="mx-lsc-runs" data-slot="score">{{ $score ? $score['runs'].'/'.$score['wickets'] : '' }}</span>
                <span class="mx-lsc-overs" data-slot="overs">{{ $score ? __('matches.common.overs_count', ['overs' => $score['overs']]) : '' }}</span>
                <span class="mx-crr {{ $showCrr ? '' : 'hidden' }}" data-slot="crr">{{ $showCrr ? __('matches.chase.crr', ['rate' => number_format((float) $score['crr'], 2)]) : '' }}</span>
            </div>
            <p class="mx-lsc-yet {{ $score ? 'hidden' : '' }}" data-slot="yet">{{ __('ux_public_matches.match.yet_to_bat') }}</p>
        </div>
    @endforeach
    </div>

    <p id="live-match-result" class="mx-lsc-result {{ $resultText ? '' : 'hidden' }}">{{ $resultText }}</p>

    <div id="live-chase" class="{{ $chase ? '' : 'hidden' }}">
        @if($chase)
            @include('public.matches._live-chase', ['chase' => $chase, 'chasingTeam' => $chasingTeam])
        @endif
    </div>
</section>
