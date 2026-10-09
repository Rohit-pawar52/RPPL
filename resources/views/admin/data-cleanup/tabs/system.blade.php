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
<section class="crud-card">
    <div class="crud-card-head">
        <div>
            <h3 class="crud-card-title">Failed Jobs</h3>
            <p class="crud-hint">Most recent first. Times shown in {{ $displayTimezone }}.</p>
        </div>
        <span class="crud-pill {{ $failedJobCount > 0 ? 'crud-pill-amber' : '' }}">{{ $failedJobCount }} total</span>
    </div>

    <div class="crud-table-scroll">
        <table class="crud-table crud-stack">
            <thead>
                <tr>
                    <th>Failed At</th>
                    <th>Job</th>
                    <th class="hidden md:table-cell">Queue</th>
                    <th class="hidden md:table-cell">Connection</th>
                    <th>Error</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($failedJobList ?? [] as $failedJob)
                    <tr class="crud-row">
                        <td class="whitespace-nowrap text-slate-500">{{ display_datetime($failedJob->failed_at, 'd M Y, h:i A') ?? '—' }}</td>
                        <td class="c-title">
                            <a href="{{ route('admin.data-cleanup.failed-jobs.show', $failedJob->uuid) }}" class="crud-row-link">{{ $failedJob->job_name }}</a>
                            <span class="crud-meta md:hidden">{{ display_datetime($failedJob->failed_at, 'd M Y, h:i A') ?? '—' }}</span>
                        </td>
                        <td class="hidden md:table-cell">{{ $failedJob->queue }}</td>
                        <td class="hidden md:table-cell">{{ $failedJob->connection }}</td>
                        <td class="break-words">{{ $failedJob->error_summary }}</td>
                        <td class="c-actions">
                            <div class="crud-actions">
                                <a
                                    href="{{ route('admin.data-cleanup.failed-jobs.show', $failedJob->uuid) }}"
                                    title="Details"
                                    aria-label="View failed job details"
                                    class="crud-icon-btn"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty table colspan="6" icon="shield">No failed jobs.</x-admin.empty>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($failedJobList)
        <div class="border-t border-line px-4 py-3">
            {{ $failedJobList->links() }}
        </div>
    @endif
</section>

<section class="crud-danger">
    <div class="crud-danger-head">
        <span class="crud-danger-icon"><x-icon name="trash" class="h-4 w-4" /></span>
        <div class="min-w-0">
            <h3 class="crud-card-title">Delete Old Failed Jobs</h3>
            <p class="crud-hint">{{ $failedJobCount }} total. Delete records before this date (selected date is not included) — times are interpreted in {{ $displayTimezone }}.</p>
        </div>
    </div>
    <div class="crud-card-body">
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

            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-48 flex-1 sm:max-w-xs">
                    <x-form.input name="before_date" label="Delete failed jobs from before" type="date" required data-cutoff-preview="failed-jobs" />
                </div>
                <button type="submit" class="btn btn-danger-soft mb-3.5">
                    <x-icon name="trash" class="h-4 w-4" /> Delete failed jobs
                </button>
            </div>
            <p class="-mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600" data-cutoff-preview-result="failed-jobs">Select a date to see how many failed jobs this would affect.</p>
        </form>
    </div>
</section>

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
