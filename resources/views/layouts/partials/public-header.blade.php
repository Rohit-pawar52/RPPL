<header class="sticky top-0 z-40 border-b border-neutral-200 bg-white">
    <div class="mx-auto flex w-full max-w-5xl items-center justify-between gap-3 px-4 py-2.5 lg:px-6">
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

        {{-- Desktop nav: the 5 primary areas directly, everything else
             tucked into a native <details> "More" menu — no JS needed
             for either this or the mobile drawer below. --}}
        <nav class="hidden items-center gap-4 text-[13px] font-medium text-neutral-600 md:flex">
            <a href="{{ route('public.home') }}" class="hover:text-neutral-900">Home</a>
            <a href="{{ route('public.matches.index') }}" class="hover:text-neutral-900">Matches</a>
            @if($currentEditionForNav)
                <a href="{{ route('public.editions.show', $currentEditionForNav) }}" class="hover:text-neutral-900">Points Table</a>
            @endif
            <a href="{{ route('public.teams.index') }}" class="hover:text-neutral-900">Teams</a>
            <a href="{{ route('public.players.index') }}" class="hover:text-neutral-900">Players</a>

            <details class="group relative">
                <summary class="flex cursor-pointer list-none items-center gap-1 hover:text-neutral-900">
                    More
                    <span aria-hidden="true" class="text-[10px] text-neutral-400">&#9662;</span>
                </summary>
                <div class="absolute right-0 z-50 mt-2 w-44 rounded-md border border-neutral-200 bg-white py-1 text-[13px] shadow-lg">
                    <a href="{{ route('public.venues.index') }}" class="block px-3 py-1.5 hover:bg-neutral-50">Venues</a>
                    <a href="{{ route('public.editions.index') }}" class="block px-3 py-1.5 hover:bg-neutral-50">Editions</a>
                    <a href="{{ route('public.player-registration.create') }}" class="block px-3 py-1.5 hover:bg-neutral-50">Player Registration</a>
                    <a href="{{ route('public.rules.index') }}" class="block px-3 py-1.5 hover:bg-neutral-50">Rules</a>
                    <a href="{{ route('public.faqs') }}" class="block px-3 py-1.5 hover:bg-neutral-50">FAQs</a>
                </div>
            </details>

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

        {{-- Mobile: a single <details> drawer toggle, no JS. --}}
        <details class="group relative md:hidden">
            <summary class="flex h-8 w-8 cursor-pointer list-none items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-100">
                <x-icon name="menu" class="h-5 w-5 group-open:hidden" />
                <x-icon name="close" class="hidden h-5 w-5 group-open:block" />
            </summary>
            <nav class="absolute right-0 z-50 mt-2 w-56 rounded-md border border-neutral-200 bg-white py-1 text-[13px] font-medium text-neutral-700 shadow-lg">
                <a href="{{ route('public.home') }}" class="block px-3 py-2 hover:bg-neutral-50">Home</a>
                <a href="{{ route('public.matches.index') }}" class="block px-3 py-2 hover:bg-neutral-50">Matches</a>
                @if($currentEditionForNav)
                    <a href="{{ route('public.editions.show', $currentEditionForNav) }}" class="block px-3 py-2 hover:bg-neutral-50">Points Table</a>
                @endif
                <a href="{{ route('public.teams.index') }}" class="block px-3 py-2 hover:bg-neutral-50">Teams</a>
                <a href="{{ route('public.players.index') }}" class="block px-3 py-2 hover:bg-neutral-50">Players</a>
                <a href="{{ route('public.venues.index') }}" class="block px-3 py-2 hover:bg-neutral-50">Venues</a>
                <a href="{{ route('public.editions.index') }}" class="block px-3 py-2 hover:bg-neutral-50">Editions</a>
                <a href="{{ route('public.player-registration.create') }}" class="block px-3 py-2 hover:bg-neutral-50">Player Registration</a>
                <a href="{{ route('public.rules.index') }}" class="block px-3 py-2 hover:bg-neutral-50">Rules</a>
                <a href="{{ route('public.faqs') }}" class="block px-3 py-2 hover:bg-neutral-50">FAQs</a>
                <div class="mt-1 border-t border-neutral-100 pt-1">
                    <a href="{{ route('admin.login') }}" class="block px-3 py-2 text-neutral-400 hover:bg-neutral-50 hover:text-neutral-600">Admin</a>
                </div>
            </nav>
        </details>
    </div>
</header>
