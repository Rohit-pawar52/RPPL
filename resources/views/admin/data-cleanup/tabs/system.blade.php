{{--
    System tab — the only System cleanup implemented this phase is
    failed_jobs (a real, growing table on this app's database queue
    connection). job_batches (never populated — this codebase never
    uses Bus::batch()) and application log files (unsafe to truncate
    against the configured `single` log channel) were both audited and
    rejected — see the Phase 3.49 report.
--}}
<div>
    <h3 class="mb-1 text-sm font-semibold text-neutral-900">Failed Jobs</h3>
    <p class="mb-3 text-xs text-neutral-500">{{ $failedJobCount }} total. Delete records before this date (selected date is not included) — times are interpreted in {{ $displayTimezone }}.</p>

    <form
        method="POST"
        action="{{ route('admin.data-cleanup.failed-jobs.destroy') }}"
        data-confirm-action
        data-confirm-title="Delete old failed jobs?"
        data-confirm-text="This permanently deletes every failed-job record that failed before the selected date. Pending/running queue work is never affected. This cannot be undone."
        data-confirm-button-text="Yes, delete"
    >
        @csrf
        @method('DELETE')

        <div class="flex flex-wrap items-end gap-2">
            <x-form.input name="before_date" label="Delete failed jobs from before" type="date" required data-cutoff-preview="failed-jobs" />
            <button type="submit" class="mb-3.5 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-[13px] font-medium text-red-700 hover:bg-red-100">
                Delete failed jobs
            </button>
        </div>
        <p class="-mt-2 text-xs text-neutral-500" data-cutoff-preview-result="failed-jobs">Select a date to see how many failed jobs this would affect.</p>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-cutoff-preview]').forEach((input) => {
            const category = input.dataset.cutoffPreview;
            const result = document.querySelector(`[data-cutoff-preview-result="${category}"]`);
            if (! result) return;

            input.addEventListener('change', async () => {
                if (! input.value) return;

                try {
                    const response = await fetch(`{{ route('admin.data-cleanup.preview.cutoff') }}?category=${category}&before_date=${input.value}`, {
                        headers: { Accept: 'application/json' },
                    });

                    if (! response.ok) {
                        result.textContent = 'Could not calculate a preview for this date.';
                        return;
                    }

                    const data = await response.json();
                    result.textContent = `${data.count} record(s) would be deleted.`;
                } catch (error) {
                    result.textContent = 'Could not calculate a preview for this date.';
                }
            });
        });
    });
</script>
