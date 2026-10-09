{{--
    The chase panel of the score header: what the batting side still needs.
    Expects: $chase (LiveMatchService 'chase' array - non-null only while a
    second innings is live) and $chasingTeam (string|null).
    KEEP IN SYNC with renderChase() in resources/js/public-live-match.js.
--}}
@php
    $teamLabel = $chasingTeam ?? __('ux_public_matches.chase.chasing_side');
    $scored = max(0, (int) $chase['target'] - (int) $chase['runs_needed']);
    $percent = $chase['target'] > 0 ? min(100, (int) round($scored / $chase['target'] * 100)) : 0;
@endphp
<div class="mx-chase">
    <p class="mx-chase-headline">
        @if($chase['runs_needed'] > 0)
            {{ __('ux_public_matches.chase.need', [
                'team' => $teamLabel,
                'runs' => trans_choice('ux_public_matches.chase.runs', $chase['runs_needed'], ['count' => $chase['runs_needed']]),
                'balls' => trans_choice('ux_public_matches.chase.balls', $chase['balls_remaining'], ['count' => $chase['balls_remaining']]),
            ]) }}
        @else
            {{ __('ux_public_matches.chase.reached', ['team' => $teamLabel]) }}
        @endif
    </p>
    <div class="mx-chase-bar" aria-hidden="true"><span style="width: {{ $percent }}%"></span></div>
    <dl class="mx-chase-grid">
        @foreach([
            [__('ux_public_matches.chase.target'), $chase['target']],
            [__('ux_public_matches.chase.need_label'), $chase['runs_needed']],
            [__('ux_public_matches.chase.balls_label'), $chase['balls_remaining']],
            [__('ux_public_matches.chase.rrr'), number_format((float) $chase['required_run_rate'], 2)],
        ] as [$statLabel, $statValue])
            <div>
                <dt>{{ $statLabel }}</dt>
                <dd>{{ $statValue }}</dd>
            </div>
        @endforeach
    </dl>
</div>
