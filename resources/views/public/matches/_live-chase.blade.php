{{--
    Expects: $chase (LiveMatchService 'chase' array — non-null only while a
    second innings is live) and $chasingTeam (string|null).
    KEEP IN SYNC with renderChase() in resources/js/public-live-match.js.
--}}
<p class="text-[13px] font-semibold text-neutral-900">
    @if($chase['runs_needed'] > 0)
        {{ $chasingTeam ?? 'Chasing side' }} need {{ $chase['runs_needed'] }} {{ \Illuminate\Support\Str::plural('run', $chase['runs_needed']) }}
        from {{ $chase['balls_remaining'] }} {{ \Illuminate\Support\Str::plural('ball', $chase['balls_remaining']) }}
    @else
        {{ $chasingTeam ?? 'Chasing side' }} have reached the target
    @endif
</p>
<dl class="mt-1.5 grid grid-cols-4 gap-2 text-center">
    <div>
        <dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Target</dt>
        <dd class="text-[13px] font-semibold tabular-nums text-neutral-900">{{ $chase['target'] }}</dd>
    </div>
    <div>
        <dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Need</dt>
        <dd class="text-[13px] font-semibold tabular-nums text-neutral-900">{{ $chase['runs_needed'] }}</dd>
    </div>
    <div>
        <dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">Balls</dt>
        <dd class="text-[13px] font-semibold tabular-nums text-neutral-900">{{ $chase['balls_remaining'] }}</dd>
    </div>
    <div>
        <dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">RRR</dt>
        <dd class="text-[13px] font-semibold tabular-nums text-neutral-900">{{ number_format((float) $chase['required_run_rate'], 2) }}</dd>
    </div>
</dl>
