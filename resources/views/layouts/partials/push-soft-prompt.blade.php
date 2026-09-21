{{-- RPPL-controlled soft notification prompt (Phase 3.47) — hidden by
     default. resources/js/push-notifications.js decides WHETHER and
     WHEN to reveal it (Notification.permission === 'default', no
     active 7-day "Not Now" cooldown, not already shown this browser
     session) and reveals it after a short delay — never at first
     paint, never the browser-native permission popup. A non-modal
     corner card, not a full-width bar, so it never competes with the
     announcement ticker or covers header navigation. --}}
<div
    id="rppl-push-soft-prompt"
    class="hidden fixed inset-x-4 bottom-4 z-50 mx-auto max-w-sm rounded-lg border border-neutral-200 bg-white p-4 shadow-lg sm:inset-x-auto sm:right-4"
    role="dialog"
    aria-labelledby="rppl-push-soft-prompt-title"
    aria-describedby="rppl-push-soft-prompt-message"
>
    <div class="flex items-start gap-3">
        <span class="theme-primary-soft-bg theme-primary-text flex h-9 w-9 shrink-0 items-center justify-center rounded-full">
            <x-icon name="bell" class="h-4 w-4" />
        </span>
        <div class="min-w-0 flex-1">
            <p id="rppl-push-soft-prompt-title" class="text-[13px] font-semibold text-neutral-900">
                Never miss an RPPL update
            </p>
            <p id="rppl-push-soft-prompt-message" class="mt-0.5 text-xs text-neutral-500">
                Get important match timings, postponements and tournament updates.
            </p>
            <div class="mt-3 flex items-center gap-2">
                <button
                    type="button"
                    id="rppl-push-soft-prompt-enable"
                    class="rounded-md theme-button px-3 py-1.5 text-xs font-medium"
                >
                    Enable Notifications
                </button>
                <button
                    type="button"
                    id="rppl-push-soft-prompt-dismiss"
                    class="rounded-md border border-neutral-200 px-3 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                >
                    Not Now
                </button>
            </div>
        </div>
    </div>
</div>
