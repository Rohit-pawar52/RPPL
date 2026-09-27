{{--
    Reopen a completed innings (frozen S02 rule 16) — works for either an
    automatically or a manually completed innings, as long as the match
    itself is still live. Existing deliveries are never touched; a
    mandatory reason is required and audited via ScoringEvent.
--}}
<div class="mt-3 rounded-md border border-neutral-100 bg-neutral-50 p-3">
    <p class="mb-2 text-xs text-neutral-500">
        Innings {{ $innings->innings_number }} is completed
        @if($innings->completion_type === 'manual')
            (manually — {{ $innings->completion_reason }})
        @else
            (automatically)
        @endif
        . It can be reopened for a scoring correction if needed.
    </p>

    <form method="POST" action="{{ route('admin.matches.innings.reopen', [$match, $innings]) }}" class="flex flex-wrap items-end gap-2">
        @csrf
        <div class="min-w-[220px] flex-1">
            <x-form.input name="reason" label="Reason for reopening this innings" placeholder="e.g. Wrong delivery recorded, needs correction" />
        </div>
        <button type="submit" class="mb-3.5 rounded-md border border-amber-300 bg-amber-50 px-3 py-1.5 text-[13px] font-medium text-amber-700 hover:bg-amber-100">
            Reopen Innings
        </button>
    </form>
</div>
