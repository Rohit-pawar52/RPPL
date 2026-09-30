{{--
    Expects: $innings (array from LiveMatchService::formatInnings()).
    KEEP IN SYNC with renderInnings() in resources/js/public-live-match.js
    — polling/realtime re-renders this exact markup client-side, so the
    two must always show the same information.
--}}
@php $isLiveInnings = $innings['status'] === 'live'; @endphp
<div class="p-4 sm:p-5 {{ $isLiveInnings ? 'bg-green-50/50' : '' }}">
    <div class="flex items-center justify-between gap-3">
        <p class="pub-eyebrow">Innings {{ $innings['innings_number'] }}</p>
        <x-public.status-pill :status="$innings['status']" />
    </div>
    <p class="mt-2 truncate text-sm font-semibold text-slate-800">{{ $innings['batting_team'] }}</p>
    <p class="mt-1 flex items-baseline gap-2">
        <span class="score-figure">{{ $innings['total_runs']}}/{{ $innings['total_wickets'] }}</span>
        <span class="text-xs text-slate-500">({{ $innings['overs_display'] }} overs)</span>
    </p>
    <p class="mt-2 text-xs text-slate-500">
        vs {{ $innings['bowling_team'] }}
        @if(isset($innings['crr']))
            &middot; CRR <span class="font-semibold tabular-nums text-slate-700">{{ number_format((float) $innings['crr'], 2) }}</span>
        @endif
    </p>
</div>
