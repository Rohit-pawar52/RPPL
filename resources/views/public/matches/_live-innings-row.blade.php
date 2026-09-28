{{--
    Expects: $innings (array from LiveMatchService::formatInnings()).
    KEEP IN SYNC with renderInnings() in resources/js/public-live-match.js
    — polling/realtime re-renders this exact markup client-side, so the
    two must always show the same information.
--}}
@php $isLiveInnings = $innings['status'] === 'live'; @endphp
<div class="rounded-lg border bg-white p-3 {{ $isLiveInnings ? 'theme-primary-border' : 'border-neutral-200' }}">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Innings {{ $innings['innings_number'] }}</p>
            <p class="truncate text-[13px] font-semibold text-neutral-900">{{ $innings['batting_team'] }}</p>
        </div>
        <div class="shrink-0 text-right">
            <p class="text-lg font-bold leading-tight tabular-nums text-neutral-900">{{ $innings['total_runs'] }}/{{ $innings['total_wickets'] }}</p>
            <p class="text-[11px] text-neutral-500">({{ $innings['overs_display'] }} overs)</p>
        </div>
    </div>
    <div class="mt-1.5 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-[11px] text-neutral-500">
        <span class="min-w-0">
            vs {{ $innings['bowling_team'] }}
            @if(isset($innings['crr']))
                &middot; CRR <span class="font-semibold tabular-nums text-neutral-700">{{ number_format((float) $innings['crr'], 2) }}</span>
            @endif
        </span>
        <x-status-badge :status="$innings['status']" />
    </div>
</div>
