{{--
    One ball of the commentary feed.
    Expects: $delivery (array from LiveMatchService::formatDelivery()).
    KEEP IN SYNC with renderDeliveries()/outcomeKind() in
    resources/js/public-live-match.js.
--}}
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
<div class="mx-feed-row" data-kind="{{ $kind }}">
    <span class="mx-ball mx-ball-{{ $kind }}">{{ $label }}</span>
    <p class="min-w-0 pt-0.5 leading-snug text-slate-700">
        <span class="mr-1.5 font-semibold tabular-nums text-slate-900">{{ $delivery['ball_label'] }}</span>{{ $delivery['commentary'] }}
    </p>
</div>
