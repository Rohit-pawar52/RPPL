{{--
    "This over": the balls of the over in progress as round badges, plus the
    last ball and the last wicket. Built from the same recent_deliveries the
    commentary feed uses (newest first), so it needs nothing extra.
    Expects: $deliveries (LiveMatchService 'recent_deliveries').
    KEEP IN SYNC with renderThisOver() in resources/js/public-live-match.js.
--}}
@php
    $latest = $deliveries[0] ?? null;
    $overKey = $latest ? explode('.', (string) $latest['ball_label'])[0] : null;
    $thisOver = collect($deliveries)
        ->takeWhile(fn ($delivery) => explode('.', (string) $delivery['ball_label'])[0] === $overKey)
        ->reverse()
        ->values();
    $lastWicket = collect($deliveries)->firstWhere('is_wicket', true);
@endphp

@if($latest)
    <div class="mx-over-row">
    <p class="mx-over-label">{{ __('matches.live.this_over') }} <span>{{ __('ux_public_matches.live.over_n', ['n' => (int) $overKey + 1]) }}</span></p>
    <div class="mx-over-balls">
        @foreach($thisOver as $delivery)
            @php
                $label = (string) $delivery['outcome_label'];
                $kind = match (true) {
                    (bool) $delivery['is_wicket'] => 'wicket',
                    $label === '6' => 'six',
                    $label === '4' => 'four',
                    ! ctype_digit($label) => 'extra',
                    default => 'run',
                };
            @endphp
            <span class="mx-ball mx-ball-lg mx-ball-{{ $kind }}">{{ $label }}</span>
        @endforeach
    </div>
    </div>
    <p class="mx-over-facts">
        <span class="mx-over-fact"><b>{{ __('ux_public_matches.live.last_ball') }}</b> {{ __('matches.centre.bowler_to_striker', ['bowler' => $latest['bowler'], 'striker' => $latest['striker']]) }}</span>
        @if($lastWicket)
            <span class="mx-over-fact"><b>{{ __('ux_public_matches.live.last_wicket') }}</b> {{ $lastWicket['dismissed_player'] ?? $lastWicket['striker'] }} ({{ $lastWicket['ball_label'] }})</span>
        @endif
    </p>
@else
    <p class="pub-empty">{{ __('ux_public_matches.live.no_deliveries') }}</p>
@endif
