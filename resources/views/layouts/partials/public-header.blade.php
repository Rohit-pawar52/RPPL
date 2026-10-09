{{--
    The public site header. One sticky navy bar:
      - brand, then (large screens) the main areas with a clear active state and
        a "More" drop-down;
      - on the right: the language switch, the notification bell, a quiet admin
        link and the always-visible Register button;
      - below lg the links live in a full-width sheet opened from the menu button.
    The drop-downs and the sheet are native <details> elements, so they work with
    no JavaScript; the small script at the bottom only adds the niceties (close on
    outside click / Esc / link tap, stop the page scrolling behind the sheet).
    The bell button keeps its id and its initial `hidden` class: resources/js/
    push-notifications.js reveals it only when the browser can actually do push.
--}}
@php
    $is = fn (string ...$patterns) => request()->routeIs(...$patterns);

    // The language switch POSTs with a CSRF token, so it needs a started
    // session. A URL that matched no route (a typo -> 404) is answered before
    // the session middleware runs; the switch is simply left out there.
    $canSwitchLanguage = session()->isStarted();
    $locale = app()->getLocale();

    $registerUrl = route('public.player-registration.create');
    $statusUrl = route('public.player-registration.status');

    // Main areas: a bar link on large screens, a tile in the mobile sheet.
    $primary = [
        ['url' => route('public.home'), 'label' => __('public.nav.home'), 'icon' => 'home', 'active' => $is('public.home')],
        ['url' => route('public.matches.index'), 'label' => __('public.nav.matches'), 'icon' => 'calendar', 'active' => $is('public.matches.*')],
    ];
    if ($currentEditionForNav) {
        $primary[] = ['url' => route('public.editions.show', $currentEditionForNav), 'label' => __('public.nav.points_table'), 'icon' => 'table', 'active' => $is('public.editions.show', 'public.editions.stats')];
    }
    $primary[] = ['url' => route('public.teams.index'), 'label' => __('public.nav.teams'), 'icon' => 'users', 'active' => $is('public.teams.*')];
    $primary[] = ['url' => route('public.players.index'), 'label' => __('public.nav.players'), 'icon' => 'user', 'active' => $is('public.players.*')];

    $auctionLive = in_array($auctionForNav, ['live', 'paused'], true);
    $auctionItem = $auctionForNav
        ? ['url' => route('public.auction.show'), 'label' => __('auction.nav'), 'icon' => 'gavel', 'active' => $is('public.auction.*'), 'live' => $auctionForNav === 'live']
        : null;
    if ($auctionLive) {
        $primary[] = $auctionItem;
    }

    // Everything else: the "More" drop-down and the list in the mobile sheet.
    $more = [
        ['url' => route('public.news.index'), 'label' => __('directory.news.title'), 'icon' => 'newspaper', 'active' => $is('public.news.*')],
        ['url' => route('public.videos.index'), 'label' => __('directory.videos.title'), 'icon' => 'play', 'active' => $is('public.videos.*')],
        ['url' => route('public.photos.index'), 'label' => __('directory.photos.title'), 'icon' => 'camera', 'active' => $is('public.photos.*')],
        ['url' => route('public.venues.index'), 'label' => __('public.nav.venues'), 'icon' => 'map-pin', 'active' => $is('public.venues.*')],
        ['url' => route('public.editions.index'), 'label' => __('public.nav.editions'), 'icon' => 'trophy', 'active' => $is('public.editions.index')],
        ['url' => route('public.rules.index'), 'label' => __('public.nav.rules'), 'icon' => 'book', 'active' => $is('public.rules.*')],
        ['url' => route('public.faqs'), 'label' => __('public.nav.faqs'), 'icon' => 'help', 'active' => $is('public.faqs')],
    ];
    if ($auctionItem && ! $auctionLive) {
        array_unshift($more, $auctionItem);
    }
    $moreActive = collect($more)->contains('active', true) || $is('public.player-registration.status');
@endphp

<header class="sticky top-0 z-40 border-b border-white/5 bg-navy-900 text-white" data-public-header>
    <div class="mx-auto flex h-14 w-full max-w-6xl items-center gap-2 px-4 sm:gap-3 lg:h-16 lg:px-6">
        <a href="{{ route('public.home') }}" class="flex min-w-0 items-center gap-2.5 rounded-lg py-1 pr-1 font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-dark" aria-label="{{ $branding->applicationName }}">
            @if($branding->logoUrl)
                <img src="{{ $branding->logoUrl }}" alt="" class="h-9 w-9 shrink-0 rounded-lg bg-white/10 object-contain" />
            @else
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-accent-dark text-base font-bold text-navy-950">
                    {{ Illuminate\Support\Str::substr($branding->shortName, 0, 1) }}
                </span>
            @endif
            <span class="max-w-[7.5rem] truncate text-[17px] font-bold tracking-tight sm:max-w-[12rem]">{{ $branding->shortName }}</span>
        </a>

        {{-- Large screens: the main areas, then everything else under "More". --}}
        <nav class="ml-4 hidden items-stretch self-stretch lg:flex" aria-label="{{ __('ux_public_shell.nav.main') }}">
            @foreach($primary as $item)
                <a href="{{ $item['url'] }}" class="ps-nav-link {{ $item['active'] ? 'ps-nav-link-active' : '' }}" @if($item['active']) aria-current="page" @endif>
                    {{ $item['label'] }}
                    @if(! empty($item['live']))<span class="live-dot text-red-400" aria-hidden="true"></span>@endif
                </a>
            @endforeach

            <details class="group relative flex self-stretch" data-menu>
                <summary class="ps-nav-link cursor-pointer {{ $moreActive ? 'ps-nav-link-active' : '' }} group-open:text-white">
                    {{ __('public.nav.more') }}
                    <x-icon name="chevron-down" class="h-3.5 w-3.5 text-white/50 transition-transform group-open:rotate-180" />
                </summary>
                <div class="rppl-pop absolute left-0 top-full z-50 mt-1 w-64 rounded-xl bg-white p-1.5 shadow-pop ring-1 ring-black/5">
                    @foreach($more as $item)
                        <a href="{{ $item['url'] }}" class="ps-menu-item {{ $item['active'] ? 'ps-menu-item-active' : '' }}">
                            <span class="ps-menu-icon"><x-icon :name="$item['icon']" class="h-[18px] w-[18px]" /></span>
                            <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                            @if(! empty($item['live']))<span class="live-dot text-red-500" aria-hidden="true"></span>@endif
                        </a>
                    @endforeach
                    <div class="my-1 border-t border-line"></div>
                    <a href="{{ $statusUrl }}" class="ps-menu-item {{ $is('public.player-registration.status') ? 'ps-menu-item-active' : '' }}">
                        <span class="ps-menu-icon"><x-icon name="clipboard" class="h-[18px] w-[18px]" /></span>
                        <span class="min-w-0 flex-1 truncate">{{ __('ux_public_shell.nav.check_status') }}</span>
                    </a>
                </div>
            </details>
        </nav>

        <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
            @if($canSwitchLanguage)
                {{-- One tap to switch: two tiny POST forms (CSRF-protected) side by side. --}}
                <div class="hidden items-center rounded-full bg-white/10 p-0.5 text-xs font-semibold sm:inline-flex" role="group" aria-label="{{ __('public.language.switch_language') }}">
                    @foreach(['en' => 'EN', 'hi' => __('public.language.hindi')] as $code => $short)
                        <form method="POST" action="{{ route('public.language.switch', $code) }}">
                            @csrf
                            <button type="submit" class="rounded-full px-2.5 py-1 transition-colors {{ $locale === $code ? 'bg-white text-navy-900' : 'text-white/70 hover:text-white' }}" @if($locale === $code) aria-current="true" @endif>
                                {{ $short }}
                            </button>
                        </form>
                    @endforeach
                </div>
            @endif

            <span class="relative inline-flex">
                <button
                    type="button"
                    id="fcm-subscribe-button"
                    class="hidden rounded-md p-1.5 min-h-10 min-w-10 text-white/70 transition hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-accent-dark disabled:cursor-not-allowed disabled:opacity-60"
                    aria-label="Enable notifications"
                    title="Enable notifications"
                    data-state="default"
                >
                    <x-icon name="bell" class="mx-auto h-5 w-5" />
                </button>
                <span
                    id="fcm-subscribe-indicator"
                    class="rppl-notify-indicator pointer-events-none absolute right-1.5 top-1.5 h-2 w-2 rounded-full ring-2 ring-navy-900"
                    aria-hidden="true"
                ></span>
            </span>

            <a href="{{ route('admin.login') }}" class="hidden items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs text-white/40 transition hover:text-white/80 xl:inline-flex">
                <x-icon name="lock" class="h-3.5 w-3.5" />
                {{ __('public.nav.admin') }}
            </a>

            <a href="{{ $registerUrl }}" class="btn btn-primary shrink-0 px-3.5">
                <x-icon name="user-plus" class="hidden h-4 w-4 sm:block" />
                {{ __('ux_public_shell.nav.register') }}
            </a>

            {{-- Below lg: a full-width sheet with big tap targets. --}}
            <details class="group lg:hidden" data-menu data-menu-sheet>
                <summary
                    class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-lg text-white transition hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-accent-dark"
                    aria-label="{{ __('ux_public_shell.nav.menu') }}"
                >
                    <x-icon name="menu" class="h-6 w-6 group-open:hidden" />
                    <x-icon name="close" class="hidden h-6 w-6 group-open:block" />
                </summary>

                <nav class="ps-sheet" aria-label="{{ __('ux_public_shell.nav.main') }}">
                    <div class="mx-auto w-full max-w-xl space-y-5 p-4 pb-7">
                        <form method="GET" action="{{ route('public.players.index') }}" role="search" class="relative">
                            <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-slate-400" />
                            <input
                                type="search"
                                name="search"
                                placeholder="{{ __('ux_public_shell.nav.search_players') }}"
                                aria-label="{{ __('ux_public_shell.nav.search_players') }}"
                                enterkeyhint="search"
                                class="h-12 w-full rounded-xl border border-line bg-surface pl-10 pr-3 text-[15px] text-slate-900 placeholder:text-slate-400 focus:border-brand focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20"
                            />
                        </form>

                        <div class="grid grid-cols-2 gap-2.5">
                            @foreach($primary as $item)
                                <a href="{{ $item['url'] }}" class="ps-tile {{ $item['active'] ? 'ps-tile-active' : '' }}" @if($item['active']) aria-current="page" @endif>
                                    <span class="ps-tile-icon"><x-icon :name="$item['icon']" class="h-5 w-5" /></span>
                                    <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                    @if(! empty($item['live']))<span class="live-dot text-red-500" aria-hidden="true"></span>@endif
                                </a>
                            @endforeach
                        </div>

                        <div>
                            <p class="pub-eyebrow px-3 pb-1">{{ __('ux_public_shell.nav.explore') }}</p>
                            <div class="grid gap-0.5 sm:grid-cols-2">
                                @foreach($more as $item)
                                    <a href="{{ $item['url'] }}" class="ps-row {{ $item['active'] ? 'ps-row-active' : '' }}" @if($item['active']) aria-current="page" @endif>
                                        <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0 text-slate-400" />
                                        <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                        @if(! empty($item['live']))<span class="live-dot text-red-500" aria-hidden="true"></span>@endif
                                        <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-slate-300" />
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <div class="space-y-2">
                            <a href="{{ $registerUrl }}" class="btn btn-primary btn-lg btn-block">
                                <x-icon name="user-plus" class="h-5 w-5" />
                                {{ __('ux_public_shell.nav.register_long') }}
                            </a>
                            <a href="{{ $statusUrl }}" class="btn btn-secondary btn-block">
                                {{ __('ux_public_shell.nav.check_status') }}
                            </a>
                        </div>

                        @if($canSwitchLanguage)
                            <div role="group" aria-label="{{ __('public.language.switch_language') }}">
                                <p class="pub-eyebrow px-1 pb-1.5">{{ __('ux_public_shell.nav.language') }}</p>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach(['en' => __('public.language.english'), 'hi' => __('public.language.hindi')] as $code => $name)
                                        <form method="POST" action="{{ route('public.language.switch', $code) }}">
                                            @csrf
                                            <button type="submit" class="min-h-11 w-full rounded-xl border text-sm font-semibold transition-colors {{ $locale === $code ? 'border-brand bg-brand-soft text-brand' : 'border-line bg-white text-slate-700 hover:bg-hover' }}" @if($locale === $code) aria-current="true" @endif>
                                                {{ $name }}
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <a href="{{ route('admin.login') }}" class="flex items-center justify-center gap-1.5 py-1 text-xs text-slate-400 hover:text-slate-600">
                            <x-icon name="lock" class="h-3.5 w-3.5" />
                            {{ __('public.nav.admin') }}
                        </a>
                    </div>
                </nav>
            </details>
        </div>
    </div>
</header>

<script>
    (function () {
        var header = document.querySelector('[data-public-header]');
        if (!header) { return; }

        var menus = Array.prototype.slice.call(header.querySelectorAll('details[data-menu]'));
        var root = document.documentElement;

        var closeAll = function (except) {
            menus.forEach(function (menu) {
                if (menu !== except && menu.open) { menu.open = false; }
            });
        };

        menus.forEach(function (menu) {
            menu.addEventListener('toggle', function () {
                if (menu.open) { closeAll(menu); }

                if (menu.hasAttribute('data-menu-sheet')) {
                    // The sheet starts right under the header, wherever the page is scrolled to.
                    root.style.setProperty('--ps-sheet-top', Math.max(0, Math.round(header.getBoundingClientRect().bottom)) + 'px');
                    root.classList.toggle('ps-menu-open', menu.open);
                }
            });
        });

        document.addEventListener('click', function (event) {
            var target = event.target;
            menus.forEach(function (menu) {
                if (menu.open && !menu.contains(target)) { menu.open = false; }
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') { return; }
            var openMenu = menus.filter(function (menu) { return menu.open; })[0];
            if (!openMenu) { return; }
            openMenu.open = false;
            var summary = openMenu.querySelector('summary');
            if (summary) { summary.focus(); }
        });

        // A sheet left open while the window is widened must not keep the page locked.
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 1024) { closeAll(null); root.classList.remove('ps-menu-open'); }
        });
    })();
</script>
