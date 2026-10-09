{{--
    Notifications tab — old notifications, notification send-history,
    and FCM tokens. Each date-cutoff form shares the same live preview
    pattern: a hidden result line is filled in via
    admin.data-cleanup.preview.cutoff as soon as a date is chosen, so
    the admin sees an exact count before confirming, never just
    "this cannot be undone."
--}}
<section class="crud-danger">
    <div class="crud-danger-head">
        <span class="crud-danger-icon"><x-icon name="bell" class="h-4 w-4" /></span>
        <div class="min-w-0">
            <h3 class="crud-card-title">{{ __('Old Notifications') }}</h3>
            <p class="crud-hint">{{ __(':count total. Delete records before this date (selected date is not included) — times are interpreted in :timezone.', ['count' => $notificationCount, 'timezone' => $displayTimezone]) }}</p>
        </div>
    </div>
    <div class="crud-card-body">
        <form
            method="POST"
            action="{{ route('admin.data-cleanup.notifications.destroy') }}"
            data-confirm-action
            data-confirm-title="{{ __('Delete old notifications?') }}"
            data-confirm-text="{{ __('This permanently deletes every notification created before the selected date, along with its send history. This cannot be undone.') }}"
            data-confirm-button-text="{{ __('Yes, delete') }}"
        >
            @csrf
            @method('DELETE')

            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[12rem] flex-1 sm:max-w-xs">
                    <x-form.input name="before_date" :label="__('Delete notifications created before')" type="date" required data-cutoff-preview="notifications" />
                </div>
                <button type="submit" class="btn btn-danger-soft mb-3.5">
                    <x-icon name="trash" class="h-4 w-4" /> {{ __('Delete notifications') }}
                </button>
            </div>
            <p class="-mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600" data-cutoff-preview-result="notifications">{{ __('Select a date to see how many notifications this would affect.') }}</p>
        </form>
    </div>
</section>

<section class="crud-danger">
    <div class="crud-danger-head">
        <span class="crud-danger-icon"><x-crud.glyph name="send" class="h-4 w-4" /></span>
        <div class="min-w-0">
            <h3 class="crud-card-title">{{ __('Notification Send History') }}</h3>
            <p class="crud-hint">{{ __(':count total. Deleting this leaves the notifications themselves untouched.', ['count' => $notificationSendCount]) }}</p>
        </div>
    </div>
    <div class="crud-card-body">
        <form
            method="POST"
            action="{{ route('admin.data-cleanup.notification-sends.destroy') }}"
            data-confirm-action
            data-confirm-title="{{ __('Delete old send history?') }}"
            data-confirm-text="{{ __('This permanently deletes every send-history record created before the selected date. The notifications themselves are left untouched. This cannot be undone.') }}"
            data-confirm-button-text="{{ __('Yes, delete') }}"
        >
            @csrf
            @method('DELETE')

            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[12rem] flex-1 sm:max-w-xs">
                    <x-form.input name="before_date" :label="__('Delete sends created before')" type="date" required data-cutoff-preview="notification-sends" />
                </div>
                <button type="submit" class="btn btn-danger-soft mb-3.5">
                    <x-icon name="trash" class="h-4 w-4" /> {{ __('Delete send history') }}
                </button>
            </div>
            <p class="-mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600" data-cutoff-preview-result="notification-sends">{{ __('Select a date to see how many send records this would affect.') }}</p>
        </form>
    </div>
</section>

<section class="crud-danger">
    <div class="crud-danger-head">
        <span class="crud-danger-icon"><x-icon name="key" class="h-4 w-4" /></span>
        <div class="min-w-0">
            <h3 class="crud-card-title">{{ __('FCM Tokens') }}</h3>
            <p class="crud-hint">{{ __(':total total, :inactive inactive.', ['total' => $fcmTokenCount, 'inactive' => $inactiveFcmTokenCount]) }}</p>
        </div>
    </div>
    <div class="crud-card-body">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <form
                method="POST"
                action="{{ route('admin.data-cleanup.fcm-tokens.destroy-inactive') }}"
                class="flex flex-col rounded-lg border border-line p-4"
                data-confirm-action
                data-confirm-title="{{ __('Delete inactive FCM tokens?') }}"
                data-confirm-text="{{ __('This permanently deletes every FCM token Firebase has already reported as invalid/unregistered (:count token(s)). These can never receive a notification again. This cannot be undone.', ['count' => $inactiveFcmTokenCount]) }}"
                data-confirm-button-text="{{ __('Yes, delete') }}"
            >
                @csrf
                @method('DELETE')
                <p class="mb-1 text-[13px] font-semibold text-slate-900">{{ __('Delete all inactive tokens') }}</p>
                <p class="mb-4 text-xs text-slate-500">{{ __(':count token(s) already marked invalid by Firebase.', ['count' => $inactiveFcmTokenCount]) }}</p>
                <button type="submit" class="btn btn-danger-soft btn-block mt-auto">
                    <x-icon name="trash" class="h-4 w-4" /> {{ __('Delete inactive tokens') }}
                </button>
            </form>

            <form
                id="stale-fcm-tokens-form"
                method="POST"
                action="{{ route('admin.data-cleanup.fcm-tokens.destroy-stale') }}"
                class="flex flex-col rounded-lg border border-line p-4"
                data-confirm-action
                data-confirm-title="{{ __('Delete stale FCM tokens?') }}"
                data-confirm-text="{{ __('This permanently deletes every FCM token not seen in the selected number of days, regardless of its active/inactive status. Devices behind a deleted token will stop receiving notifications until they resubscribe. This cannot be undone.') }}"
                data-confirm-button-text="{{ __('Yes, delete') }}"
            >
                @csrf
                @method('DELETE')

                <x-form.select
                    name="days"
                    :label="__('Delete tokens not seen in the last')"
                    :options="collect($staleDaysOptions)->mapWithKeys(fn ($days) => [$days => __(':days days', ['days' => $days])])->all()"
                />
                <p class="-mt-2 mb-4 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600" id="stale-fcm-preview">{{ __('Choose a threshold to see how many tokens this would affect.') }}</p>

                <button type="submit" class="btn btn-danger-soft btn-block mt-auto">
                    <x-icon name="trash" class="h-4 w-4" /> {{ __('Delete stale tokens') }}
                </button>
            </form>
        </div>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // The texts this script shows, in the admin's language.
        const text = {
            noPreviewDate: @json(__('Could not calculate a preview for this date.')),
            recordsDeleted: @json(__(':count record(s) would be deleted.')),
            noPreviewThreshold: @json(__('Could not calculate a preview for this threshold.')),
            tokensDeleted: @json(__(':count token(s) would be deleted.')),
        };

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
                        result.textContent = text.noPreviewDate;
                        return;
                    }

                    const data = await response.json();
                    result.textContent = text.recordsDeleted.replace(':count', data.count);
                } catch (error) {
                    result.textContent = text.noPreviewDate;
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
                        staleFcmPreview.textContent = text.noPreviewThreshold;
                        return;
                    }

                    const data = await response.json();
                    staleFcmPreview.textContent = text.tokensDeleted.replace(':count', data.count);
                } catch (error) {
                    staleFcmPreview.textContent = text.noPreviewThreshold;
                }
            };

            staleDaysSelect.addEventListener('change', refreshStalePreview);
            refreshStalePreview();
        }
    });
</script>
