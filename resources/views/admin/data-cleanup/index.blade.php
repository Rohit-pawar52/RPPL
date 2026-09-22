@extends('layouts.admin')

@section('title', 'Data Cleanup')

@section('content')
    <div class="mb-4">
        <p class="text-[13px] text-neutral-500">
            Bulk retention cleanup for notification data. Nothing here runs automatically — every action below requires an explicit confirmation and cannot be undone.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Notifications --}}
        <div class="rounded-lg border border-neutral-200 bg-white p-4">
            <x-stat-card label="Notifications" :value="$notificationCount" icon="bell" />

            <form
                method="POST"
                action="{{ route('admin.data-cleanup.notifications.destroy') }}"
                class="mt-4"
                data-confirm-action
                data-confirm-title="Delete old notifications?"
                data-confirm-text="This permanently deletes every notification created before the selected date, along with its send history. This cannot be undone."
                data-confirm-button-text="Yes, delete"
            >
                @csrf
                @method('DELETE')

                <x-form.input name="before_date" label="Delete notifications created before" type="date" required />

                <button type="submit" class="w-full rounded-md border border-red-200 bg-red-50 px-3 py-2 text-[13px] font-medium text-red-700 hover:bg-red-100">
                    Delete notifications
                </button>
            </form>
        </div>

        {{-- Notification sends --}}
        <div class="rounded-lg border border-neutral-200 bg-white p-4">
            <x-stat-card label="Notification Sends" :value="$notificationSendCount" icon="bell" />

            <form
                method="POST"
                action="{{ route('admin.data-cleanup.notification-sends.destroy') }}"
                class="mt-4"
                data-confirm-action
                data-confirm-title="Delete old send history?"
                data-confirm-text="This permanently deletes every send-history record created before the selected date. The notifications themselves are left untouched. This cannot be undone."
                data-confirm-button-text="Yes, delete"
            >
                @csrf
                @method('DELETE')

                <x-form.input name="before_date" label="Delete sends created before" type="date" required />

                <button type="submit" class="w-full rounded-md border border-red-200 bg-red-50 px-3 py-2 text-[13px] font-medium text-red-700 hover:bg-red-100">
                    Delete send history
                </button>
            </form>
        </div>

        {{-- FCM tokens --}}
        <div class="rounded-lg border border-neutral-200 bg-white p-4">
            <x-stat-card label="FCM Tokens" :value="$fcmTokenCount" :subtext="$activeFcmTokenCount.' active'" icon="bell" />

            <form
                method="POST"
                action="{{ route('admin.data-cleanup.fcm-tokens.destroy') }}"
                class="mt-4"
                data-confirm-action
                data-confirm-title="Delete older FCM tokens?"
                data-confirm-text="This permanently deletes every FCM token beyond the number you keep, oldest-activity first. Devices behind a deleted token will stop receiving notifications until they resubscribe. This cannot be undone."
                data-confirm-button-text="Yes, delete"
            >
                @csrf
                @method('DELETE')

                <x-form.select
                    name="keep_count"
                    label="Keep the most recently active"
                    :options="collect($keepCountOptions)->mapWithKeys(fn ($count) => [$count => $count])->all()"
                />

                <button type="submit" class="mt-3.5 w-full rounded-md border border-red-200 bg-red-50 px-3 py-2 text-[13px] font-medium text-red-700 hover:bg-red-100">
                    Delete older tokens
                </button>
            </form>
        </div>
    </div>
@endsection
