{{-- Expects: $innings, $match --}}
<div class="rounded-md border border-neutral-100 bg-neutral-50 p-3">
    <div class="flex items-center justify-between gap-2">
        <p class="text-xs font-semibold text-neutral-800">
            Innings {{ $innings->innings_number }} &mdash; {{ $innings->battingTeam->team->name }} batting
        </p>
        <x-status-badge :status="$innings->status" />
    </div>
    <div class="mt-1 flex items-center justify-between gap-2">
        <p class="text-sm font-medium text-neutral-800">
            {{ $innings->total_runs }}/{{ $innings->total_wickets }}
            <span class="text-xs font-normal text-neutral-500">({{ $innings->oversDisplay() }} overs)</span>
        </p>

        @can('score', $match)
            @if($innings->status === 'live')
                <a
                    href="{{ route('admin.matches.innings.score', [$match, $innings]) }}"
                    class="inline-flex items-center gap-1 rounded-md border border-neutral-200 bg-white px-2 py-1 text-[11px] font-medium text-neutral-600 hover:bg-neutral-50"
                >
                    <x-icon name="chart-bar" class="h-3 w-3" />
                    Score Innings
                </a>
            @endif
        @endcan
    </div>
</div>
