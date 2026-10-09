{{-- Expects: $innings, $match --}}
<div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line bg-slate-50 p-3 sm:p-4">
    <div class="min-w-0">
        <p class="text-xs font-semibold text-slate-700">
            {{ __('Innings :number — :team batting', ['number' => $innings->innings_number, 'team' => $innings->battingTeam->team->name]) }}
        </p>
        <p class="mt-0.5 text-xl font-bold tabular-nums tracking-tight text-slate-900">
            {{ $innings->total_runs }}/{{ $innings->total_wickets }}
            <span class="text-xs font-medium text-slate-500">({{ __(':overs overs', ['overs' => $innings->oversDisplay()]) }})</span>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <x-status-badge :status="$innings->status" />

        @can('score', $match)
            @if($innings->status === 'live')
                <a
                    href="{{ route('admin.matches.innings.score', [$match, $innings]) }}"
                    class="btn btn-primary"
                >
                    <x-icon name="chart-bar" class="h-4 w-4" />
                    {{ __('Score Innings') }}
                </a>
            @endif
        @endcan
    </div>
</div>
