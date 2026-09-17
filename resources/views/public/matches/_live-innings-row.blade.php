{{-- Expects: $innings (array from LiveMatchService::formatInnings()) --}}
<div class="rounded-lg border border-neutral-200 bg-white p-4">
    <div class="flex items-center justify-between gap-3">
        <p class="text-sm font-semibold text-neutral-900">
            Innings {{ $innings['innings_number'] }} &mdash; {{ $innings['batting_team'] }}
        </p>
        <x-status-badge :status="$innings['status']" />
    </div>
    <p class="mt-1 text-lg font-semibold text-neutral-900">
        {{ $innings['total_runs'] }}/{{ $innings['total_wickets'] }}
        <span class="text-xs font-normal text-neutral-500">({{ $innings['overs_display'] }} overs)</span>
    </p>
    <p class="text-xs text-neutral-500">{{ $innings['batting_team'] }} batting &middot; {{ $innings['bowling_team'] }} bowling</p>
</div>
