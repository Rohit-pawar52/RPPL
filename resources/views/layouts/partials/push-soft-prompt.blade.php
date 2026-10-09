{{-- RPPL-controlled soft notification prompt (Phase 3.47) — hidden by
     default. resources/js/push-notifications.js decides WHETHER and
     WHEN to reveal it (Notification.permission === 'default', no
     active 7-day "Not Now" cooldown, not already shown this browser
     session) and reveals it after a short delay — never at first
     paint, never the browser-native permission popup. A non-modal
     card in the corner (a floating sheet on a phone), never a full-width
     bar, so it never competes with the announcement ticker or covers
     header navigation. The JS toggles the `hidden` class, so that class
     and the element ids below must stay. --}}
<div
    id="rppl-push-soft-prompt"
    class="hidden fixed inset-x-3 bottom-3 z-50 mx-auto max-w-sm rounded-2xl border border-line bg-white p-4 shadow-pop sm:inset-x-auto sm:bottom-5 sm:right-5 sm:mx-0 sm:w-[22rem]"
    role="dialog"
    aria-labelledby="rppl-push-soft-prompt-title"
    aria-describedby="rppl-push-soft-prompt-message"
>
    <div class="flex items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand">
            <x-icon name="bell" class="h-5 w-5" />
        </span>
        <div class="min-w-0 flex-1">
            <p id="rppl-push-soft-prompt-title" class="text-sm font-semibold tracking-tight text-slate-900">
                {{ __('ux_public_shell.push.title', ['name' => $branding->shortName]) }}
            </p>
            <p id="rppl-push-soft-prompt-message" class="mt-1 text-[13px] leading-snug text-slate-500">
                {{ __('ux_public_shell.push.message') }}
            </p>
            <div class="mt-3.5 flex items-center gap-2">
                <button
                    type="button"
                    id="rppl-push-soft-prompt-enable"
                    class="btn btn-primary btn-sm"
                >
                    {{ __('ux_public_shell.push.enable') }}
                </button>
                <button
                    type="button"
                    id="rppl-push-soft-prompt-dismiss"
                    class="btn btn-ghost btn-sm"
                >
                    {{ __('ux_public_shell.push.dismiss') }}
                </button>
            </div>
        </div>
    </div>
</div>
