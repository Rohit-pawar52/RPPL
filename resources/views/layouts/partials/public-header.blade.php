<header class="sticky top-0 z-40 border-b border-neutral-200 bg-white">
    <div class="mx-auto flex w-full max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3 lg:px-6">
        <a href="{{ route('public.home') }}" class="flex items-center gap-2 font-semibold text-neutral-900">
            @if($branding->logoUrl)
                <img src="{{ $branding->logoUrl }}" alt="{{ $branding->applicationName }}" class="h-7 w-7 rounded-md object-contain" />
            @else
                <span class="theme-primary-bg flex h-7 w-7 items-center justify-center rounded-md text-xs font-bold">
                    {{ Illuminate\Support\Str::substr($branding->shortName, 0, 1) }}
                </span>
            @endif
            <span class="text-sm">{{ $branding->shortName }}</span>
        </a>

        <nav class="flex flex-wrap items-center gap-4 text-[13px] font-medium text-neutral-600">
            <a href="{{ route('public.home') }}" class="hover:text-neutral-900">Home</a>
            <a href="{{ route('public.editions.index') }}" class="hover:text-neutral-900">Editions</a>
            <a href="{{ route('public.matches.index') }}" class="hover:text-neutral-900">Matches</a>
            <a href="{{ route('public.teams.index') }}" class="hover:text-neutral-900">Teams</a>
            <a href="{{ route('public.players.index') }}" class="hover:text-neutral-900">Players</a>
            <a href="{{ route('public.venues.index') }}" class="hover:text-neutral-900">Venues</a>
            <a href="{{ route('public.player-registration.create') }}" class="hover:text-neutral-900">Register</a>
            {{-- Compact bell badge (Phase 3.47) — hidden by default;
                 resources/js/push-notifications.js reveals it only once
                 the browser/Firebase config are confirmed usable.
                 Clicking it never itself guarantees a native permission
                 prompt: the JS decides what to do based on the CURRENT
                 Notification.permission (request it, show blocked help,
                 or do nothing if already granted) — see
                 push-notifications.js for the full state machine. --}}
            <span class="relative inline-flex">
                <button
                    type="button"
                    id="fcm-subscribe-button"
                    class="hidden rounded-md p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-60"
                    aria-label="Enable notifications"
                    title="Enable notifications"
                    data-state="default"
                >
                    <x-icon name="bell" class="h-4 w-4" />
                </button>
                <span
                    id="fcm-subscribe-indicator"
                    class="rppl-notify-indicator pointer-events-none absolute -right-0.5 -top-0.5 h-2 w-2 rounded-full ring-2 ring-white"
                    aria-hidden="true"
                ></span>
            </span>
            <a href="{{ route('admin.login') }}" class="text-neutral-400 hover:text-neutral-600">Admin</a>
        </nav>
    </div>
</header>
