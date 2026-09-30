@php
    // Desktop top-level links. The active one gets a green underline.
    $navLink = fn (bool $active) => 'flex h-full items-center border-b-2 px-3 text-[13px] font-medium transition '
        .($active ? 'border-green-400 text-white' : 'border-transparent text-white/70 hover:text-white');

    $dropdownLink = 'block px-3 py-2 text-[13px] text-slate-700 hover:bg-slate-50 hover:text-slate-900';
    $drawerLink = fn (bool $active) => 'block rounded-md px-3 py-2 text-[13px] font-medium '
        .($active ? 'bg-green-50 text-green-700' : 'text-slate-700 hover:bg-slate-50');
@endphp

<header class="sticky top-0 z-40 bg-navy-900 text-white shadow-md shadow-navy-950/20">
    <div class="mx-auto flex h-14 w-full max-w-6xl items-stretch justify-between gap-3 px-4 lg:px-6">
        <a href="{{ route('public.home') }}" class="flex items-center gap-2.5 font-semibold text-white" aria-label="{{ $branding->applicationName }}">
            @if($branding->logoUrl)
                <img src="{{ $branding->logoUrl }}" alt="" class="h-8 w-8 rounded-md bg-white/10 object-contain" />
            @else
                <span class="flex h-8 w-8 items-center justify-center rounded-md bg-green-500 text-sm font-bold text-navy-950">
                    {{ Illuminate\Support\Str::substr($branding->shortName, 0, 1) }}
                </span>
            @endif
            <span class="text-base font-bold tracking-tight">{{ $branding->shortName }}</span>
        </a>

        {{-- Desktop nav: the primary areas directly, everything else in a
             native <details> "More" menu — no JS needed for either this
             or the mobile drawer below. --}}
        <nav class="hidden items-stretch gap-0.5 lg:flex" aria-label="{{ $branding->shortName }}">
            <a href="{{ route('public.home') }}" class="{{ $navLink(request()->routeIs('public.home')) }}">{{ __('public.nav.home') }}</a>
            <a href="{{ route('public.matches.index') }}" class="{{ $navLink(request()->routeIs('public.matches.*')) }}">{{ __('public.nav.matches') }}</a>
            @if($currentEditionForNav)
                <a href="{{ route('public.editions.show', $currentEditionForNav) }}" class="{{ $navLink(request()->routeIs('public.editions.*')) }}">{{ __('public.nav.points_table') }}</a>
            @endif
            <a href="{{ route('public.teams.index') }}" class="{{ $navLink(request()->routeIs('public.teams.*')) }}">{{ __('public.nav.teams') }}</a>
            <a href="{{ route('public.players.index') }}" class="{{ $navLink(request()->routeIs('public.players.*')) }}">{{ __('public.nav.players') }}</a>

            <details class="group relative">
                <summary class="flex h-full cursor-pointer items-center gap-1 border-b-2 border-transparent px-3 text-[13px] font-medium text-white/70 transition hover:text-white group-open:text-white">
                    {{ __('public.nav.more') }}
                    <span aria-hidden="true" class="text-[10px] text-white/50">&#9662;</span>
                </summary>
                <div class="rppl-pop absolute right-0 top-full z-50 mt-1 w-52 overflow-hidden rounded-lg border border-line bg-white py-1 shadow-xl">
                    <a href="{{ route('public.venues.index') }}" class="{{ $dropdownLink }}">{{ __('public.nav.venues') }}</a>
                    <a href="{{ route('public.editions.index') }}" class="{{ $dropdownLink }}">{{ __('public.nav.editions') }}</a>
                    <a href="{{ route('public.player-registration.create') }}" class="{{ $dropdownLink }}">{{ __('public.nav.player_registration') }}</a>
                    <a href="{{ route('public.rules.index') }}" class="{{ $dropdownLink }}">{{ __('public.nav.rules') }}</a>
                    <a href="{{ route('public.faqs') }}" class="{{ $dropdownLink }}">{{ __('public.nav.faqs') }}</a>
                    <a href="{{ route('public.news.index') }}" class="{{ $dropdownLink }}">{{ __('directory.news.title') }}</a>
                    <a href="{{ route('public.videos.index') }}" class="{{ $dropdownLink }}">{{ __('directory.videos.title') }}</a>
                    <a href="{{ route('public.photos.index') }}" class="{{ $dropdownLink }}">{{ __('directory.photos.title') }}</a>
                </div>
            </details>
        </nav>

        <div class="flex items-center gap-1">
            {{-- Compact English/हिन्दी switcher — same zero-JS <details>
                 pattern as "More". Each option is its own tiny POST form
                 (CSRF-protected) to public.language.switch. --}}
            <details class="group relative hidden lg:block">
                <summary
                    class="flex h-8 cursor-pointer items-center gap-1 rounded-md px-2 text-[13px] font-medium text-white/80 transition hover:bg-white/10 hover:text-white"
                    aria-label="{{ __('public.language.switch_language') }}"
                >
                    {{ app()->getLocale() === 'hi' ? __('public.language.hindi') : __('public.language.english') }}
                    <span aria-hidden="true" class="text-[10px] text-white/50">&#9662;</span>
                </summary>
                <div class="rppl-pop absolute right-0 z-50 mt-2 w-32 overflow-hidden rounded-lg border border-line bg-white py-1 text-[13px] shadow-xl">
                    <form method="POST" action="{{ route('public.language.switch', 'en') }}">
                        @csrf
                        <button type="submit" class="block w-full px-3 py-2 text-left text-slate-700 hover:bg-slate-50 {{ app()->getLocale() === 'en' ? 'font-semibold text-green-700' : '' }}">
                            {{ __('public.language.english') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('public.language.switch', 'hi') }}">
                        @csrf
                        <button type="submit" class="block w-full px-3 py-2 text-left text-slate-700 hover:bg-slate-50 {{ app()->getLocale() === 'hi' ? 'font-semibold text-green-700' : '' }}">
                            {{ __('public.language.hindi') }}
                        </button>
                    </form>
                </div>
            </details>

            <span class="relative inline-flex">
                <button
                    type="button"
                    id="fcm-subscribe-button"
                    class="hidden rounded-md p-1.5 text-white/70 transition hover:bg-white/10 hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
                    aria-label="Enable notifications"
                    title="Enable notifications"
                    data-state="default"
                >
                    <x-icon name="bell" class="h-4 w-4" />
                </button>
                <span
                    id="fcm-subscribe-indicator"
                    class="rppl-notify-indicator pointer-events-none absolute right-0.5 top-0.5 h-2 w-2 rounded-full ring-2 ring-navy-900"
                    aria-hidden="true"
                ></span>
            </span>

            <a href="{{ route('admin.login') }}" class="hidden rounded-md px-2 py-1.5 text-xs text-white/40 transition hover:text-white/80 lg:block">{{ __('public.nav.admin') }}</a>

            {{-- Mobile / tablet: a single <details> drawer toggle, no JS. --}}
            <details class="group relative self-center lg:hidden">
                <summary
                    class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-md text-white transition hover:bg-white/10"
                    aria-label="Menu"
                >
                    <x-icon name="menu" class="h-5 w-5 group-open:hidden" />
                    <x-icon name="close" class="hidden h-5 w-5 group-open:block" />
                </summary>
                <nav class="rppl-pop absolute right-0 top-full z-50 mt-2 max-h-[80vh] w-64 overflow-y-auto rounded-lg border border-line bg-white p-1.5 shadow-xl">
                    <a href="{{ route('public.home') }}" class="{{ $drawerLink(request()->routeIs('public.home')) }}">{{ __('public.nav.home') }}</a>
                    <a href="{{ route('public.matches.index') }}" class="{{ $drawerLink(request()->routeIs('public.matches.*')) }}">{{ __('public.nav.matches') }}</a>
                    @if($currentEditionForNav)
                        <a href="{{ route('public.editions.show', $currentEditionForNav) }}" class="{{ $drawerLink(request()->routeIs('public.editions.*')) }}">{{ __('public.nav.points_table') }}</a>
                    @endif
                    <a href="{{ route('public.teams.index') }}" class="{{ $drawerLink(request()->routeIs('public.teams.*')) }}">{{ __('public.nav.teams') }}</a>
                    <a href="{{ route('public.players.index') }}" class="{{ $drawerLink(request()->routeIs('public.players.*')) }}">{{ __('public.nav.players') }}</a>
                    <a href="{{ route('public.venues.index') }}" class="{{ $drawerLink(request()->routeIs('public.venues.*')) }}">{{ __('public.nav.venues') }}</a>
                    <a href="{{ route('public.editions.index') }}" class="{{ $drawerLink(false) }}">{{ __('public.nav.editions') }}</a>
                    <a href="{{ route('public.player-registration.create') }}" class="{{ $drawerLink(request()->routeIs('public.player-registration.*')) }}">{{ __('public.nav.player_registration') }}</a>
                    <a href="{{ route('public.rules.index') }}" class="{{ $drawerLink(request()->routeIs('public.rules.*')) }}">{{ __('public.nav.rules') }}</a>
                    <a href="{{ route('public.faqs') }}" class="{{ $drawerLink(false) }}">{{ __('public.nav.faqs') }}</a>
                    <a href="{{ route('public.news.index') }}" class="{{ $drawerLink(request()->routeIs('public.news.*')) }}">{{ __('directory.news.title') }}</a>
                    <a href="{{ route('public.videos.index') }}" class="{{ $drawerLink(request()->routeIs('public.videos.*')) }}">{{ __('directory.videos.title') }}</a>
                    <a href="{{ route('public.photos.index') }}" class="{{ $drawerLink(request()->routeIs('public.photos.*')) }}">{{ __('directory.photos.title') }}</a>

                    {{-- Same two-form switcher as desktop, laid out inline. --}}
                    <div class="mt-1 flex items-center gap-1.5 border-t border-line px-1.5 pt-2" role="group" aria-label="{{ __('public.language.switch_language') }}">
                        <form method="POST" action="{{ route('public.language.switch', 'en') }}" class="flex-1">
                            @csrf
                            <button type="submit" class="w-full rounded-md px-2 py-1.5 text-center text-[13px] font-medium {{ app()->getLocale() === 'en' ? 'bg-navy-900 text-white' : 'text-slate-600 hover:bg-slate-50' }}">
                                {{ __('public.language.english') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('public.language.switch', 'hi') }}" class="flex-1">
                            @csrf
                            <button type="submit" class="w-full rounded-md px-2 py-1.5 text-center text-[13px] font-medium {{ app()->getLocale() === 'hi' ? 'bg-navy-900 text-white' : 'text-slate-600 hover:bg-slate-50' }}">
                                {{ __('public.language.hindi') }}
                            </button>
                        </form>
                    </div>

                    <a href="{{ route('admin.login') }}" class="mt-1 block rounded-md px-3 py-2 text-xs text-slate-400 hover:bg-slate-50 hover:text-slate-600">{{ __('public.nav.admin') }}</a>
                </nav>
            </details>
        </div>
    </div>
</header>
