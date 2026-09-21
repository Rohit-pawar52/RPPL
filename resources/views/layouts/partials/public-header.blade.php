<header class="sticky top-0 z-40 border-b border-neutral-200 bg-white">
    <div class="mx-auto flex w-full max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3 lg:px-6">
        <a href="{{ route('public.home') }}" class="flex items-center gap-2 font-semibold text-neutral-900">
            @if($branding->logoUrl)
                <img src="{{ $branding->logoUrl }}" alt="{{ $branding->applicationName }}" class="h-7 w-7 rounded-md object-contain" />
            @else
                <span class="flex h-7 w-7 items-center justify-center rounded-md bg-blue-600 text-xs font-bold text-white">
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
            {{-- Hidden by default; resources/js/push-notifications.js reveals it
                 only once the browser/Firebase config are confirmed usable, and
                 never requests permission until this button is explicitly clicked. --}}
            <button
                type="button"
                id="fcm-subscribe-button"
                class="hidden rounded-md border border-neutral-200 px-2.5 py-1 text-xs font-medium text-neutral-600 hover:border-neutral-300 hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-60"
            >
                🔔 Enable Notifications
            </button>
            <a href="{{ route('admin.login') }}" class="text-neutral-400 hover:text-neutral-600">Admin</a>
        </nav>
    </div>
</header>
