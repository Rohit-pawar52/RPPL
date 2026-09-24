{{--
    Notifications tab — old notifications, notification send-history,
    and FCM tokens. Each date-cutoff form shares the same live preview
    pattern: a hidden result line is filled in via
    admin.data-cleanup.preview.cutoff as soon as a date is chosen, so
    the admin sees an exact count before confirming, never just
    "this cannot be undone."
--}}
<div class="space-y-6">
    <div>
        <h3 class="mb-1 text-sm font-semibold text-neutral-900">Old Notifications</h3>
        <p class="mb-3 text-xs text-neutral-500">{{ $notificationCount }} total. Delete records before this date (selected date is not included) — times are interpreted in {{ $displayTimezone }}.</p>

        <form
            method="POST"
            action="{{ route('admin.data-cleanup.notifications.destroy') }}"
            data-confirm-action
            data-confirm-title="Delete old notifications?"
            data-confirm-text="This permanently deletes every notification created before the selected date, along with its send history. This cannot be undone."
            data-confirm-button-text="Yes, delete"
        >
            @csrf
            @method('DELETE')

            <div class="flex flex-wrap items-end gap-2">
                <x-form.input name="before_date" label="Delete notifications created before" type="date" required data-cutoff-preview="notifications" />
                <button type="submit" class="mb-3.5 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-[13px] font-medium text-red-700 hover:bg-red-100">
                    Delete notifications
                </button>
            </div>
            <p class="-mt-2 text-xs text-neutral-500" data-cutoff-preview-result="notifications">Select a date to see how many notifications this would affect.</p>
        </form>
    </div>

    <div class="border-t border-neutral-100 pt-6">
        <h3 class="mb-1 text-sm font-semibold text-neutral-900">Notification Send History</h3>
        <p class="mb-3 text-xs text-neutral-500">{{ $notificationSendCount }} total. Deleting this leaves the notifications themselves untouched.</p>

        <form
            method="POST"
            action="{{ route('admin.data-cleanup.notification-sends.destroy') }}"
            data-confirm-action
            data-confirm-title="Delete old send history?"
            data-confirm-text="This permanently deletes every send-history record created before the selected date. The notifications themselves are left untouched. This cannot be undone."
            data-confirm-button-text="Yes, delete"
        >
            @csrf
            @method('DELETE')

            <div class="flex flex-wrap items-end gap-2">
                <x-form.input name="before_date" label="Delete sends created before" type="date" required data-cutoff-preview="notification-sends" />
                <button type="submit" class="mb-3.5 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-[13px] font-medium text-red-700 hover:bg-red-100">
                    Delete send history
                </button>
            </div>
            <p class="-mt-2 text-xs text-neutral-500" data-cutoff-preview-result="notification-sends">Select a date to see how many send records this would affect.</p>
        </form>
    </div>

    <div class="border-t border-neutral-100 pt-6">
        <h3 class="mb-1 text-sm font-semibold text-neutral-900">FCM Tokens</h3>
        <p class="mb-3 text-xs text-neutral-500">{{ $fcmTokenCount }} total, {{ $inactiveFcmTokenCount }} inactive.</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <form
                method="POST"
                action="{{ route('admin.data-cleanup.fcm-tokens.destroy-inactive') }}"
                data-confirm-action
                data-confirm-title="Delete inactive FCM tokens?"
                data-confirm-text="This permanently deletes every FCM token Firebase has already reported as invalid/unregistered ({{ $inactiveFcmTokenCount }} token(s)). These can never receive a notification again. This cannot be undone."
                data-confirm-button-text="Yes, delete"
            >
                @csrf
                @method('DELETE')
                <p class="mb-2 text-xs font-medium text-neutral-700">Delete all inactive tokens</p>
                <p class="mb-3 text-xs text-neutral-500">{{ $inactiveFcmTokenCount }} token(s) already marked invalid by Firebase.</p>
                <button type="submit" class="w-full rounded-md border border-red-200 bg-red-50 px-3 py-2 text-[13px] font-medium text-red-700 hover:bg-red-100">
                    Delete inactive tokens
                </button>
            </form>

            <form
                id="stale-fcm-tokens-form"
                method="POST"
                action="{{ route('admin.data-cleanup.fcm-tokens.destroy-stale') }}"
                data-confirm-action
                data-confirm-title="Delete stale FCM tokens?"
                data-confirm-text="This permanently deletes every FCM token not seen in the selected number of days, regardless of its active/inactive status. Devices behind a deleted token will stop receiving notifications until they resubscribe. This cannot be undone."
                data-confirm-button-text="Yes, delete"
            >
                @csrf
                @method('DELETE')

                <x-form.select
                    name="days"
                    label="Delete tokens not seen in the last"
                    :options="collect($staleDaysOptions)->mapWithKeys(fn ($days) => [$days => $days.' days'])->all()"
                />
                <p class="-mt-2 mb-3 text-xs text-neutral-500" id="stale-fcm-preview">Choose a threshold to see how many tokens this would affect.</p>

                <button type="submit" class="w-full rounded-md border border-red-200 bg-red-50 px-3 py-2 text-[13px] font-medium text-red-700 hover:bg-red-100">
                    Delete stale tokens
                </button>
            </form>
        </div>
    </div>
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

        const staleDaysSelect = document.querySelector('#stale-fcm-tokens-form [name="days"]');
        const staleFcmPreview = document.getElementById('stale-fcm-preview');

        if (staleDaysSelect && staleFcmPreview) {
            const refreshStalePreview = async () => {
                try {
                    const response = await fetch(`{{ route('admin.data-cleanup.preview.stale-fcm-tokens') }}?days=${staleDaysSelect.value}`, {
                        headers: { Accept: 'application/json' },
                    });

                    if (! response.ok) {
                        staleFcmPreview.textContent = 'Could not calculate a preview for this threshold.';
                        return;
                    }

                    const data = await response.json();
                    staleFcmPreview.textContent = `${data.count} token(s) would be deleted.`;
                } catch (error) {
                    staleFcmPreview.textContent = 'Could not calculate a preview for this threshold.';
                }
            };

            staleDaysSelect.addEventListener('change', refreshStalePreview);
            refreshStalePreview();
        }
    });
</script>
