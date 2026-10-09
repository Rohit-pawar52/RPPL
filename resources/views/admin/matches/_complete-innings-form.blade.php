{{--
    Manual innings completion (frozen S02 rule 15) — a mandatory reason
    is required and persisted as innings.completion_reason, distinct
    from an automatic completion (10 wickets/overs/target), which never
    goes through this form at all.
--}}
<form
    method="POST"
    action="{{ route('admin.matches.innings.complete', [$match, $innings]) }}"
    class="mt-4 grid gap-x-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end"
>
    @csrf
    <x-form.input name="reason" :label="__('Reason for completing this innings now')" :placeholder="__('e.g. Bad light, umpire\'s decision')" />
    <div class="mb-3.5">
        <button type="submit" class="btn btn-secondary min-h-10 max-sm:w-full">
            {{ __('Complete Innings') }}
        </button>
    </div>
</form>
