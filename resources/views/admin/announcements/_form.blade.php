{{-- Shared by create.blade.php and edit.blade.php. $announcement is null on create. --}}
@php
    $announcement = $announcement ?? null;
@endphp

<div class="crud-grid">
    <div class="crud-main">
        <x-admin.card title="Message">
            <x-form.textarea name="message" label="Message" :value="$announcement->message ?? ''" rows="3" maxlength="500" required autofocus help="Plain text only — emoji are welcome (e.g. 🏏 📢 ⚠️ 🎉). HTML is not supported and will display as-is." />
            <p class="crud-counter -mt-2 mb-3 block text-right" data-message-count>0 / 500</p>

            {{-- The ticker as visitors see it. --}}
            <div class="rppl-ticker rounded-lg text-xs font-medium">
                <p class="truncate px-3 py-2" data-ticker-preview>{{ $announcement->message ?? 'Your announcement appears in the ticker like this.' }}</p>
            </div>
        </x-admin.card>

        <x-admin.card title="When it shows">
            <div class="crud-cols">
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
            <p class="crud-note">
                Both optional — leave blank for no boundary on that side. Times are entered and shown in the site's configured display timezone.
            </p>
        </x-admin.card>
    </div>

    <div class="crud-aside">
        <x-admin.card title="Ticker">
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
                help="Lower numbers appear first when several announcements are active at once."
            />
        </x-admin.card>

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

        <x-admin.card title="Push notification">
            @if($alreadyDispatched)
                <p class="crud-note">
                    A push notification for this announcement has already been sent — editing the message/ticker window will not send another one.
                </p>
            @else
                <div class="space-y-2">
                    <label class="crud-choice">
                        <input type="radio" name="notification_choice" value="none" {{ $choice === 'none' ? 'checked' : '' }} />
                        <span><span class="crud-choice-title">Do not send</span><span class="crud-choice-text">Ticker only.</span></span>
                    </label>
                    <label class="crud-choice">
                        <input type="radio" name="notification_choice" value="now" {{ $choice === 'now' ? 'checked' : '' }} />
                        <span><span class="crud-choice-title">Send now</span><span class="crud-choice-text">Push it to subscribers when you save.</span></span>
                    </label>
                    <label class="crud-choice">
                        <input type="radio" name="notification_choice" value="later" {{ $choice === 'later' ? 'checked' : '' }} />
                        <span><span class="crud-choice-title">Schedule for later</span><span class="crud-choice-text">Pick the time below.</span></span>
                    </label>
                </div>

                <div id="notification_scheduled_at_wrap" class="mt-3 {{ $choice === 'later' ? '' : 'hidden' }}">
                    <x-form.input
                        name="notification_scheduled_at"
                        label="Send At"
                        type="datetime-local"
                        :value="$announcement?->notification_scheduled_at ? display_datetime($announcement->notification_scheduled_at, 'Y-m-d\TH:i') : old('notification_scheduled_at', '')"
                    />
                </div>
                <p class="mt-3 text-xs text-slate-500">
                    Times are entered and shown in the site's configured display timezone. "Send now" and "Schedule for later" both reuse the existing notification system — see Notifications for send history.
                </p>
                @error('notification_scheduled_at')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            @endif
        </x-admin.card>
    </div>
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

    // Live character count and ticker preview.
    (function () {
        const field = document.getElementById('message');
        const counter = document.querySelector('[data-message-count]');
        const preview = document.querySelector('[data-ticker-preview]');
        if (!field || !counter) { return; }
        const update = () => {
            const n = field.value.length;
            counter.textContent = n + ' / 500';
            counter.dataset.near = n >= 450 ? 'true' : 'false';
            counter.dataset.over = n > 500 ? 'true' : 'false';
            if (preview) { preview.textContent = field.value.trim() || 'Your announcement appears in the ticker like this.'; }
        };
        field.addEventListener('input', update);
        update();
    })();
</script>
