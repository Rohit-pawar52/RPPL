@php
    $nav = app(\App\Support\AdminNavigation::class);
    $navigation = $navigation ?? $nav->forUser(auth()->user());
    $breadcrumb = $nav->current($navigation);

    // Every page the user may open, for the Ctrl+K "jump to" box.
    $palettePages = collect($navigation['top'])
        ->map(fn ($item) => ['label' => $item['label'], 'group' => null, 'url' => $item['url']])
        ->concat(collect($navigation['groups'])->flatMap(
            fn ($group) => collect($group['items'])->map(fn ($item) => ['label' => $item['label'], 'group' => $group['label'], 'url' => $item['url']])
        ))
        ->values()
        ->all();
@endphp

<header class="adm-topbar no-print">
    <div class="flex h-14 items-center justify-between gap-2 px-3 sm:gap-3 sm:px-6 lg:h-16 lg:px-8">
        <div class="flex min-w-0 items-center gap-1.5 sm:gap-3">
            <button
                id="admin-sidebar-toggle"
                type="button"
                class="adm-iconbtn -ml-1 focus-visible:outline-brand lg:hidden"
                aria-label="{{ __('Toggle navigation') }}"
                aria-controls="admin-sidebar"
                aria-expanded="false"
            >
                <x-icon name="menu" class="h-5 w-5" />
            </button>

            {{-- Where am I: "Group / Page", derived from the navigation config --}}
            <nav aria-label="{{ __('Breadcrumb') }}" class="min-w-0 truncate text-[13px] text-slate-500">
                @if($breadcrumb)
                    @if($breadcrumb['group'])
                        <span class="hidden sm:inline">{{ $breadcrumb['group'] }}<span class="mx-1.5 text-slate-300" aria-hidden="true">/</span></span>
                    @endif
                    {{-- The current page follows the configured primary colour (Settings -> General). --}}
                    <span class="theme-primary-soft-bg theme-primary-text rounded-md px-2 py-1 font-semibold">{{ $breadcrumb['item'] }}</span>
                @else
                    <span class="font-semibold text-slate-800">{{ $branding->shortName }} {{ __('Admin') }}</span>
                @endif
            </nav>
        </div>

        <div class="flex shrink-0 items-center gap-1 sm:gap-2">
            {{-- Jump to any page or common task (Ctrl+K / Cmd+K, or "/"). --}}
            <button type="button" id="admin-palette-open" class="adm-jump" aria-haspopup="dialog" aria-label="{{ __('Jump to a page or task') }}">
                <x-admin.icon name="search" class="h-4 w-4" />
                <span>{{ __('Jump to…') }}</span>
                <kbd class="adm-kbd" data-kbd>Ctrl K</kbd>
            </button>
            <button type="button" class="adm-iconbtn lg:hidden" data-palette-open aria-haspopup="dialog" aria-label="{{ __('Jump to a page or task') }}">
                <x-admin.icon name="search" class="h-[18px] w-[18px]" />
            </button>

            {{-- "+ New": the common create pages, only those this user may use --}}
            <x-admin.quick-links layout="menu" />

            <x-admin.language-switch class="hidden sm:inline-flex" />

            <a
                href="{{ route('public.home') }}"
                target="_blank"
                rel="noopener"
                class="adm-iconbtn hidden sm:inline-flex"
                title="{{ __('View public site') }}"
                aria-label="{{ __('View public site') }}"
            >
                <x-admin.icon name="external" class="h-[18px] w-[18px]" />
            </a>

            {{-- Account menu (native <details>; admin-nav.js closes it on an outside click) --}}
            <details class="relative" data-dropdown>
                <summary class="flex min-h-10 cursor-pointer items-center gap-2 rounded-lg py-1 pl-1 pr-1.5 transition-colors hover:bg-hover sm:pr-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-navy-900 text-xs font-bold uppercase text-white">
                        {{ Illuminate\Support\Str::substr(auth()->user()->name, 0, 1) }}
                    </span>
                    <span class="hidden text-left lg:block">
                        <span class="block max-w-32 truncate text-xs font-semibold leading-tight text-slate-800">{{ auth()->user()->name }}</span>
                        <span class="block text-[11px] leading-tight text-slate-500">{{ auth()->user()->role->name }}</span>
                    </span>
                    <x-icon name="chevron-down" class="hidden h-3.5 w-3.5 text-slate-400 sm:block" />
                </summary>

                <div class="adm-menu right-0 w-64">
                    <div class="border-b border-line px-3.5 pb-3 pt-2">
                        <p class="truncate text-[13px] font-semibold text-slate-900">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                        <p class="mt-1.5 inline-block rounded-full bg-brand-soft px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-brand">{{ auth()->user()->role->name }}</p>
                    </div>
                    <a href="{{ route('public.home') }}" target="_blank" rel="noopener" class="adm-menu-item sm:hidden">
                        <x-admin.icon name="external" class="adm-menu-ico" />
                        {{ __('View site') }}
                    </a>
                    <div class="flex items-center justify-between gap-3 px-3.5 py-2 sm:hidden">
                        <span class="text-xs font-medium text-slate-500">{{ __('Language') }}</span>
                        <x-admin.language-switch />
                    </div>
                    <a href="{{ route('admin.account.password.edit') }}" class="adm-menu-item">
                        <x-icon name="key" class="adm-menu-ico" />
                        {{ __('Change password') }}
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="adm-menu-item" data-no-lock>
                            <x-icon name="logout" class="adm-menu-ico" />
                            {{ __('Logout') }}
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</header>

{{-- Ctrl+K palette: filled and driven by resources/js/admin-ui.js --}}
<script type="application/json" id="admin-palette-pages">@json($palettePages)</script>
<x-admin.quick-links layout="json" />
<dialog id="admin-palette" class="adm-palette" aria-label="{{ __('Jump to a page or task') }}">
    <div class="flex items-center gap-3 border-b border-line px-4">
        <x-admin.icon name="search" class="h-[18px] w-[18px] shrink-0 text-slate-400" />
        <input
            type="text"
            id="admin-palette-input"
            class="h-14 w-full border-0 bg-transparent text-base text-slate-900 outline-none placeholder:text-slate-400 sm:text-sm"
            placeholder="{{ __('Go to a page or start a task…') }}"
            autocomplete="off"
            spellcheck="false"
            role="combobox"
            aria-expanded="true"
            aria-controls="admin-palette-list"
        />
        <button type="button" class="adm-kbd" data-palette-close>Esc</button>
    </div>
    <div id="admin-palette-list" class="max-h-[min(24rem,60vh)] overflow-y-auto p-2" role="listbox"></div>
    <p class="border-t border-line px-4 py-2 text-[11px] text-slate-400">{{ __('Use the arrow keys, then Enter. Press Esc to close.') }}</p>
</dialog>
