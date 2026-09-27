{{--
    Manual innings completion (frozen S02 rule 15) — a mandatory reason
    is required and persisted as innings.completion_reason, distinct
    from an automatic completion (10 wickets/overs/target), which never
    goes through this form at all.
--}}
<form
    method="POST"
    action="{{ route('admin.matches.innings.complete', [$match, $innings]) }}"
    class="mt-3 flex flex-wrap items-end gap-2"
>
    @csrf
    <div class="min-w-[220px] flex-1">
        <x-form.input name="reason" label="Reason for completing this innings now" placeholder="e.g. Bad light, umpire's decision" />
    </div>
    <button type="submit" class="mb-3.5 rounded-md theme-button px-3 py-1.5 text-[13px] font-medium">
        Complete Innings
    </button>
</form>
