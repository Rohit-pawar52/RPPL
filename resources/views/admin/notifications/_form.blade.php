{{-- Shared by create.blade.php and edit.blade.php. $notification is null on create. --}}
@php
    $notification = $notification ?? null;
    $appName = app(\App\Services\Settings\SettingsService::class)->get('general.short_name');
@endphp

<div class="crud-grid" data-push-compose>
    <div class="crud-main">
        <x-admin.card :title="__('Message')">
            <x-form.input name="title" :label="__('Title')" :value="$notification->title ?? ''" required autofocus maxlength="150" data-count="150" />

            <x-form.textarea name="message" :label="__('Message')" :value="$notification->message ?? ''" rows="4" maxlength="500" data-count="500" :help="__('Keep it short: most phones show only the first two lines.')" />

            <x-form.input name="action_url" :label="__('Action URL')" :value="$notification->action_url ?? ''" maxlength="255" placeholder="/matches/12" :help="__('Optional internal RPPL path, e.g. /matches/12 or /player-registration. External links are not allowed.')" />
        </x-admin.card>
    </div>

    <div class="crud-aside">
        <x-admin.card :title="__('How it will look')">
            {{-- A rough picture of the phone notification, updated as you type. --}}
            <div class="crud-push" aria-live="polite">
                <div class="flex items-center gap-2 text-[11px] text-slate-500">
                    <span class="flex h-5 w-5 items-center justify-center rounded-md bg-navy-900 text-[10px] font-bold text-white">{{ \Illuminate\Support\Str::substr($appName, 0, 1) }}</span>
                    <span class="font-medium uppercase tracking-wide">{{ $appName }}</span>
                    <span>&middot; {{ __('now') }}</span>
                </div>
                <p class="mt-2 break-words text-[13px] font-semibold text-slate-900" data-preview-title>{{ $notification->title ?? __('Notification title') }}</p>
                <p class="mt-0.5 line-clamp-3 break-words text-xs text-slate-600" data-preview-message>{{ $notification->message ?? __('Your message appears here.') }}</p>
            </div>
            <p class="crud-note mt-3">{{ __('Saving does not send it. Send it from the Notifications list or its page, to everyone subscribed at that moment.') }}</p>
        </x-admin.card>
    </div>
</div>

<script>
    // Live character counters + the preview card. Page-local, no dependency.
    (function () {
        var root = document.querySelector('[data-push-compose]');
        if (!root) { return; }

        var title = root.querySelector('#title');
        var message = root.querySelector('#message');
        var pTitle = root.querySelector('[data-preview-title]');
        var pMessage = root.querySelector('[data-preview-message]');

        root.querySelectorAll('[data-count]').forEach(function (field) {
            var max = parseInt(field.dataset.count, 10);
            var counter = document.createElement('span');
            counter.className = 'crud-counter mt-1 block text-right';
            field.insertAdjacentElement('afterend', counter);

            var update = function () {
                var n = field.value.length;
                counter.textContent = n + ' / ' + max;
                counter.dataset.near = n >= max * 0.9 ? 'true' : 'false';
                counter.dataset.over = n > max ? 'true' : 'false';
            };
            field.addEventListener('input', update);
            update();
        });

        var sync = function () {
            pTitle.textContent = title.value.trim() || @js(__('Notification title'));
            pMessage.textContent = message.value.trim() || @js(__('Your message appears here.'));
        };
        title.addEventListener('input', sync);
        message.addEventListener('input', sync);
        sync();
    })();
</script>
