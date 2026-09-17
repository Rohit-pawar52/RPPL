{{-- Expects: $delivery (array from LiveMatchService::formatDelivery()) --}}
<div class="flex items-start gap-3 border-b border-neutral-100 py-2 text-[13px] last:border-b-0">
    <span class="mt-0.5 w-10 shrink-0 text-xs font-medium text-neutral-500">{{ $delivery['ball_label'] }}</span>
    <span class="flex h-6 w-9 shrink-0 items-center justify-center rounded-md text-xs font-semibold {{ $delivery['is_wicket'] ? 'bg-red-50 text-red-600' : 'bg-neutral-100 text-neutral-700' }}">
        {{ $delivery['outcome_label'] }}
    </span>
    <span class="min-w-0 text-neutral-700">{{ $delivery['commentary'] }}</span>
</div>
