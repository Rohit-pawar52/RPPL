{{--
    Expects: $delivery (array from LiveMatchService::formatDelivery()).
    KEEP IN SYNC with renderDeliveries()/outcomeClasses() in
    resources/js/public-live-match.js.
--}}
@php
    $label = (string) $delivery['outcome_label'];
    $badgeClass = match (true) {
        (bool) $delivery['is_wicket'] => 'ball-badge-wicket',
        $label === '6' => 'ball-badge-six',
        $label === '4' => 'ball-badge-four',
        ! ctype_digit($label) => 'ball-badge-extra',
        default => '',
    };
@endphp
<div class="flex items-start gap-3 border-b border-line px-4 py-3 text-[13px] last:border-b-0">
    <span class="ball-badge {{ $badgeClass }}">{{ $label }}</span>
    <p class="min-w-0 pt-0.5 leading-snug text-slate-700">
        <span class="mr-1.5 font-semibold tabular-nums text-slate-900">{{ $delivery['ball_label'] }}</span>{{ $delivery['commentary'] }}
    </p>
</div>
