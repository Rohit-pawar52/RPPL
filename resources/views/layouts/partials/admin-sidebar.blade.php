@php
    $navigation = app(\App\Support\AdminNavigation::class)->forUser(auth()->user());
@endphp

{{-- Mobile-only backdrop behind the drawer; admin-nav.js toggles the "hidden" class --}}
<div id="admin-backdrop" class="fixed inset-0 z-40 hidden bg-navy-950/60 backdrop-blur-[2px] lg:hidden no-print"></div>

<aside
    id="admin-sidebar"
    class="sb fixed inset-y-0 left-0 z-50 w-72 max-w-[86vw] -translate-x-full transition-transform duration-200 motion-reduce:transition-none lg:sticky lg:top-0 lg:z-30 lg:h-screen lg:w-64 lg:max-w-none lg:shrink-0 lg:translate-x-0"
>
    {{-- Brand --}}
    <a
        href="{{ route('admin.dashboard') }}"
        class="sb-brand"
        aria-label="{{ $branding->shortName }} Admin"
    >
        @if($branding->logoUrl)
            <span class="sb-logo"><img src="{{ $branding->logoUrl }}" alt="" class="h-full w-full object-contain p-0.5" /></span>
        @else
            <span class="sb-logo sb-logo-letter">{{ Illuminate\Support\Str::upper(Illuminate\Support\Str::substr($branding->shortName, 0, 1)) }}</span>
        @endif
        <span class="sb-label min-w-0 leading-tight">
            <span class="block truncate text-sm font-bold tracking-tight">{{ $branding->shortName }}</span>
            <span class="block truncate text-[11px] font-medium text-slate-400">Admin console</span>
        </span>
    </a>

    <nav class="sb-nav" aria-label="Admin navigation">
        @foreach($navigation['top'] as $item)
            <a
                href="{{ $item['url'] }}"
                data-tip="{{ $item['label'] }}"
                @if($item['active']) aria-current="page" @endif
                class="sb-link {{ $item['active'] ? 'sb-link-active' : '' }}"
            >
                <x-icon :name="$item['icon']" class="sb-ico" />
                <span class="sb-label truncate">{{ $item['label'] }}</span>
            </a>
        @endforeach

        @if($navigation['groups'] !== [])
            <p class="sb-section">Manage</p>
        @endif

        {{-- Each "Management" group is a native <details>: no JS needed to
             expand it, and the group containing the current page is open. --}}
        @foreach($navigation['groups'] as $group)
            <details class="adm-group" data-group="{{ $group['key'] }}" @if($group['active']) open @endif>
                <summary
                    data-tip="{{ $group['label'] }}"
                    class="sb-link justify-between {{ $group['active'] ? 'sb-group-active text-white' : '' }}"
                >
                    <span class="flex min-w-0 items-center gap-3">
                        <x-icon :name="$group['icon']" class="sb-ico" />
                        <span class="sb-label truncate">{{ $group['label'] }}</span>
                    </span>
                    <x-icon name="chevron-down" class="sb-chevron" />
                </summary>

                <div class="sb-sub">
                    @foreach($group['items'] as $item)
                        <a
                            href="{{ $item['url'] }}"
                            @if($item['active']) aria-current="page" @endif
                            class="sb-sublink {{ $item['active'] ? 'sb-sublink-active' : '' }}"
                        >
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </details>
        @endforeach
    </nav>

    {{-- Desktop only: collapse to an icon rail --}}
    <div class="sb-foot hidden lg:block">
        <button
            type="button"
            id="admin-sidebar-collapse"
            class="sb-link"
            data-tip="Collapse sidebar"
            aria-pressed="false"
        >
            <x-icon name="panel-left" class="sb-ico" />
            <span class="sb-label">Collapse</span>
        </button>
    </div>
</aside>
