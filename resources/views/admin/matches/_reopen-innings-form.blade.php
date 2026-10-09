{{--
    Reopen a completed innings (frozen S02 rule 16) — works for either an
    automatically or a manually completed innings, as long as the match
    itself is still live. Existing deliveries are never touched; a
    mandatory reason is required and audited via ScoringEvent.
--}}
<details class="mt-3 rounded-xl border border-line bg-slate-50 p-3 sm:p-4">
    <summary class="cursor-pointer text-xs font-medium text-slate-600">
        Innings {{ $innings->innings_number }} is completed
        @if($innings->completion_type === 'manual')
            (manually — {{ $innings->completion_reason }})
        @else
            (automatically)
        @endif
        . It can be reopened for a scoring correction if needed.
    </summary>

    <form method="POST" action="{{ route('admin.matches.innings.reopen', [$match, $innings]) }}" class="mt-3 grid gap-x-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
        @csrf
        <x-form.input name="reason" label="Reason for reopening this innings" placeholder="e.g. Wrong delivery recorded, needs correction" />
        <div class="mb-3.5">
            <button type="submit" class="btn btn-danger-soft min-h-10 max-sm:w-full">
                Reopen Innings
            </button>
        </div>
    </form>
</details>
