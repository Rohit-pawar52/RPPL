{{--
    One team of the match score header: crest, name and the innings score.
    Expects: $team (EditionTeam with team loaded), $score (array|null:
    runs, wickets, overs, crr, batting), $outcome ('won' | 'lost' | null) and
    $side ('a' | 'b' - team B is mirrored on wide screens).

    data-live-team / data-slot are the hooks resources/js/public-live-match.js
    fills while the match is live, so the numbers change without a reload.
--}}
@php
    $teamName = $team->team->name;
    $hasScore = $score !== null;
    $showCrr = $hasScore && $score['batting'] && ($score['crr'] ?? null) !== null;
@endphp

<div
    class="mx-team mx-team-{{ $side ?? 'a' }}"
    data-live-team="{{ $teamName }}"
    data-outcome="{{ $outcome }}"
    @if($hasScore && $score['batting']) data-batting="1" @endif
>
    <x-mx.team-logo :team="$team->team" size="lg" dark />

    <div class="min-w-0">
        <p class="mx-team-name">{{ $teamName }}</p>
        <p class="mx-team-meta">
            @if($team->team->short_name)
                <span>{{ $team->team->short_name }}</span>
            @endif
            <span class="mx-batting-tag {{ $hasScore && $score['batting'] ? '' : 'hidden' }}" data-slot="batting">{{ __('ux_public_matches.match.batting') }}</span>
        </p>
    </div>

    <div class="mx-team-score {{ $hasScore ? '' : 'hidden' }}">
        <p class="mx-score" data-slot="score">{{ $hasScore ? $score['runs'].'/'.$score['wickets'] : '' }}</p>
        <p class="mx-overs" data-slot="overs">
            @if($hasScore)
                {{ __('matches.common.overs_count', ['overs' => $score['overs']]) }}
            @endif
        </p>
        <p class="mx-crr {{ $showCrr ? '' : 'hidden' }}" data-slot="crr">
            @if($showCrr)
                {{ __('matches.chase.crr', ['rate' => number_format((float) $score['crr'], 2)]) }}
            @endif
        </p>
    </div>
</div>
