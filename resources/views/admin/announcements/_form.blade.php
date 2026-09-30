{{-- Shared by create.blade.php and edit.blade.php. $announcement is null on create. --}}
@php
    $announcement = $announcement ?? null;
@endphp

<x-form.textarea name="message" label="Message" :value="$announcement->message ?? ''" rows="3" maxlength="500" required help="Plain text only — emoji are welcome (e.g. 🏏 📢 ⚠️ 🎉). HTML is not supported and will display as-is." />

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <x-form.input
        name="starts_at"
        label="Start Date & Time"
        type="datetime-local"
        :value="$announcement?->starts_at ? display_datetime($announcement->starts_at, 'Y-m-d\TH:i') : old('starts_at', '')"
    />
    <x-form.input
        name="ends_at"
        label="End Date & Time"
        type="datetime-local"
        :value="$announcement?->ends_at ? display_datetime($announcement->ends_at, 'Y-m-d\TH:i') : old('ends_at', '')"
    />
</div>
<p class="-mt-2.5 mb-3.5 text-[11px] text-slate-400">
    Both optional — leave blank for no boundary on that side. Times are entered and shown in the site's configured display timezone.
</p>

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <x-form.select
        name="is_active"
        label="Active"
        :options="['1' => 'Active', '0' => 'Inactive']"
        :value="$announcement ? ($announcement->is_active ? '1' : '0') : '1'"
    />
    <x-form.input
        name="sort_order"
        label="Display Order"
        type="number"
        min="0"
        max="9999"
        :value="$announcement->sort_order ?? 0"
    />
</div>
<p class="-mt-2.5 mb-3.5 text-[11px] text-slate-400">
    Lower display order numbers appear first when multiple announcements are active at once.
</p>

{{--
    Push notification — entirely separate from Active/Start/End above,
    which only ever control the public ticker's own visibility. See
    Announcement::notificationStatusLabel() / AnnouncementController.
--}}
@php
    $alreadyDispatched = $announcement?->notification_dispatched_at !== null;
    $defaultChoice = match (true) {
        ! $announcement?->notification_enabled => 'none',
        $announcement->notification_scheduled_at === null => 'now',
        default => 'later',
    };
    $choice = old('notification_choice', $defaultChoice);
@endphp

<div class="mb-1 border-t border-slate-100 pt-3.5">
    <p class="mb-2 text-xs font-medium text-slate-700">Push Notification</p>

    @if($alreadyDispatched)
        <p class="rounded-md bg-slate-50 px-3 py-2 text-[11px] text-slate-500">
            A push notification for this announcement has already been sent — editing the message/ticker window above will not send another one.
        </p>
    @else
        <div class="flex flex-col gap-1.5 text-[13px] text-slate-700">
            <label class="flex items-center gap-2">
                <input type="radio" name="notification_choice" value="none" {{ $choice === 'none' ? 'checked' : '' }} class="focus:border-green-500 focus:ring-green-100" />
                Do not send
            </label>
            <label class="flex items-center gap-2">
                <input type="radio" name="notification_choice" value="now" {{ $choice === 'now' ? 'checked' : '' }} class="focus:border-green-500 focus:ring-green-100" />
                Send now
            </label>
            <label class="flex items-center gap-2">
                <input
                    type="radio"
                    name="notification_choice"
                    value="later"
                    {{ $choice === 'later' ? 'checked' : '' }}
                    class="focus:border-green-500 focus:ring-green-100"
                />
                Schedule for later
            </label>
        </div>

        <div id="notification_scheduled_at_wrap" class="mt-2 max-w-xs {{ $choice === 'later' ? '' : 'hidden' }}">
            <x-form.input
                name="notification_scheduled_at"
                label="Send At"
                type="datetime-local"
                :value="$announcement?->notification_scheduled_at ? display_datetime($announcement->notification_scheduled_at, 'Y-m-d\TH:i') : old('notification_scheduled_at', '')"
            />
        </div>
        <p class="mt-1 text-[11px] text-slate-400">
            Times are entered and shown in the site's configured display timezone. "Send now" and "Schedule for later" both reuse the existing notification system — see Notifications for send history.
        </p>
        @error('notification_scheduled_at')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    @endif
</div>

<script>
    // Show/hide the "Send At" field based on the currently-checked radio —
    // a tiny page-local script, no build step or dependency needed. Runs
    // once on load too, since a validation-failure reload may already
    // have "later" selected via old().
    document.querySelectorAll('input[name="notification_choice"]').forEach((input) => {
        input.addEventListener('change', () => {
            const wrap = document.getElementById('notification_scheduled_at_wrap');
            if (wrap) {
                wrap.classList.toggle('hidden', input.value !== 'later' || !input.checked);
            }
        });
    });
</script>
