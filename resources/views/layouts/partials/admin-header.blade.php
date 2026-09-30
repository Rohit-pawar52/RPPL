@php
    $breadcrumb = app(\App\Support\AdminNavigation::class)->current(
        app(\App\Support\AdminNavigation::class)->forUser(auth()->user())
    );
@endphp

<header class="sticky top-0 z-20 border-b border-line bg-white">
    <div class="flex h-14 items-center justify-between gap-3 px-4 lg:px-6">
        <div class="flex min-w-0 items-center gap-2">
            <button
                id="admin-sidebar-toggle"
                type="button"
                class="-ml-1.5 rounded-md p-2 text-slate-500 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-green-600 lg:hidden"
                aria-label="Toggle navigation"
                aria-controls="admin-sidebar"
                aria-expanded="false"
            >
                <x-icon name="menu" class="h-5 w-5" />
            </button>

            {{-- Where am I: "Group / Page", derived from the navigation config --}}
            <nav aria-label="Breadcrumb" class="min-w-0 truncate text-[13px] text-slate-500">
                @if($breadcrumb)
                    @if($breadcrumb['group'])
                        <span class="hidden sm:inline">{{ $breadcrumb['group'] }}<span class="mx-1.5 text-slate-300" aria-hidden="true">/</span></span>
                    @endif
                    <span class="font-medium text-slate-800">{{ $breadcrumb['item'] }}</span>
                @else
                    <span class="font-medium text-slate-800">{{ $branding->shortName }} Admin</span>
                @endif
            </nav>
        </div>

        <div class="flex shrink-0 items-center gap-1.5">
            <a
                href="{{ route('public.home') }}"
                target="_blank"
                rel="noopener"
                class="hidden items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100 sm:flex"
            >
                <x-icon name="external" class="h-4 w-4" />
                View site
            </a>

            {{-- Account menu (native <details>; admin-nav.js closes it on an outside click) --}}
            <details class="group relative" data-dropdown>
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-md py-1 pl-1 pr-2 hover:bg-slate-100 [&::-webkit-details-marker]:hidden">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-navy-900 text-xs font-semibold uppercase text-white">
                        {{ Illuminate\Support\Str::substr(auth()->user()->name, 0, 1) }}
                    </span>
                    <span class="hidden text-left sm:block">
                        <span class="block text-xs font-medium leading-tight text-slate-800">{{ auth()->user()->name }}</span>
                        <span class="block text-[11px] leading-tight text-slate-500">{{ auth()->user()->role->name }}</span>
                    </span>
                    <x-icon name="chevron-down" class="h-3.5 w-3.5 text-slate-400" />
                </summary>

                <div class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-lg border border-line bg-white py-1 shadow-xl">
                    <div class="border-b border-line px-3 py-2">
                        <p class="truncate text-xs font-semibold text-slate-800">{{ auth()->user()->name }}</p>
                        <p class="truncate text-[11px] text-slate-500">{{ auth()->user()->email }}</p>
                        <p class="mt-1 inline-block rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-600">{{ auth()->user()->role->name }}</p>
                    </div>
                    <a href="{{ route('public.home') }}" target="_blank" rel="noopener" class="flex items-center gap-2 px-3 py-2 text-[13px] text-slate-700 hover:bg-slate-50 sm:hidden">
                        <x-icon name="external" class="h-4 w-4" />
                        View site
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-left text-[13px] text-slate-700 hover:bg-slate-50">
                            <x-icon name="logout" class="h-4 w-4" />
                            Logout
                        </button>
                    </form>
                </div>
            </details>
        </div>
    </div>
</header>
