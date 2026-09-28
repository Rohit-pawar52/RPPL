{{--
    Expects: $delivery (array from LiveMatchService::formatDelivery()).
    KEEP IN SYNC with renderDeliveries()/outcomeClasses() in
    resources/js/public-live-match.js.
--}}
@php
    $outcomeClasses = match (true) {
        (bool) $delivery['is_wicket'] => 'bg-red-600 text-white',
        $delivery['outcome_label'] === '6' => 'bg-emerald-600 text-white',
        $delivery['outcome_label'] === '4' => 'bg-blue-600 text-white',
        default => 'bg-neutral-100 text-neutral-700',
    };
@endphp
<div class="flex items-start gap-2.5 border-b border-neutral-100 py-2 text-[13px] last:border-b-0">
    <span class="mt-0.5 w-9 shrink-0 text-[11px] font-medium tabular-nums text-neutral-500">{{ $delivery['ball_label'] }}</span>
    <span class="flex h-6 min-w-8 shrink-0 items-center justify-center rounded-full px-1.5 text-[11px] font-bold {{ $outcomeClasses }}">
        {{ $delivery['outcome_label'] }}
    </span>
    <span class="min-w-0 leading-snug text-neutral-700">{{ $delivery['commentary'] }}</span>
</div>
