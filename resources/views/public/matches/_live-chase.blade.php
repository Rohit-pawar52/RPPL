{{--
    Expects: $chase (LiveMatchService 'chase' array — non-null only while a
    second innings is live) and $chasingTeam (string|null).
    KEEP IN SYNC with renderChase() in resources/js/public-live-match.js.
--}}
<p class="text-[13px] font-semibold text-green-800">
    @if($chase['runs_needed'] > 0)
        {{ $chasingTeam ?? 'Chasing side' }} need {{ $chase['runs_needed'] }} {{ \Illuminate\Support\Str::plural('run', $chase['runs_needed']) }}
        from {{ $chase['balls_remaining'] }} {{ \Illuminate\Support\Str::plural('ball', $chase['balls_remaining']) }}
    @else
        {{ $chasingTeam ?? 'Chasing side' }} have reached the target
    @endif
</p>
<dl class="mt-3 grid grid-cols-4 gap-2 text-center">
    @foreach([['Target', $chase['target']], ['Need', $chase['runs_needed']], ['Balls', $chase['balls_remaining']], ['RRR', number_format((float) $chase['required_run_rate'], 2)]] as [$statLabel, $statValue])
        <div class="rounded-lg bg-white px-1 py-2 ring-1 ring-inset ring-green-200">
            <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $statLabel }}</dt>
            <dd class="mt-0.5 text-sm font-bold tabular-nums text-slate-900">{{ $statValue }}</dd>
        </div>
    @endforeach
</dl>
