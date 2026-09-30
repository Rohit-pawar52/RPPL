{{--
    System tab — the only System cleanup implemented this phase is
    failed_jobs (a real, growing table on this app's database queue
    connection). job_batches (never populated — this codebase never
    uses Bus::batch()) and application log files (unsafe to truncate
    against the configured `single` log channel) were both audited and
    rejected — see the Phase 3.49 report.
--}}
{{-- Read-only Failed Jobs list — visibility/debugging only. No retry
     and no per-row delete by design; the only destructive control on
     this tab is the existing delete-by-date form below. Rows come
     pre-summarized from FailedJobViewService (the raw payload never
     reaches this view). --}}
<div class="mb-6">
    <h3 class="mb-1 text-sm font-semibold text-slate-900">Failed Jobs</h3>
    <p class="mb-3 text-xs text-slate-500">Most recent first. Times shown in {{ $displayTimezone }}.</p>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-[13px]">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Failed At</th>
                    <th class="px-4 py-2 font-medium">Job</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Queue</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Connection</th>
                    <th class="px-4 py-2 font-medium">Error</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($failedJobList ?? [] as $failedJob)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-4 py-2 text-slate-500">{{ display_datetime($failedJob->failed_at, 'd M Y, h:i A') ?? '—' }}</td>
                        <td class="px-4 py-2 font-medium text-slate-800">{{ $failedJob->job_name }}</td>
                        <td class="hidden px-4 py-2 text-slate-600 md:table-cell">{{ $failedJob->queue }}</td>
                        <td class="hidden px-4 py-2 text-slate-600 md:table-cell">{{ $failedJob->connection }}</td>
                        <td class="break-words px-4 py-2 text-slate-600">{{ $failedJob->error_summary }}</td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.data-cleanup.failed-jobs.show', $failedJob->uuid) }}"
                                    title="Details"
                                    aria-label="View failed job details"
                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                            No failed jobs.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($failedJobList)
        <div class="mt-3">
            {{ $failedJobList->links() }}
        </div>
    @endif
</div>

<div class="border-t border-slate-200 pt-4">
    <h3 class="mb-1 text-sm font-semibold text-slate-900">Delete Old Failed Jobs</h3>
    <p class="mb-3 text-xs text-slate-500">{{ $failedJobCount }} total. Delete records before this date (selected date is not included) — times are interpreted in {{ $displayTimezone }}.</p>

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
        <p class="-mt-2 text-xs text-slate-500" data-cutoff-preview-result="failed-jobs">Select a date to see how many failed jobs this would affect.</p>
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
