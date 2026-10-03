@php
    $navigation = app(\App\Support\AdminNavigation::class)->forUser(auth()->user());
@endphp

{{-- Mobile-only backdrop behind the drawer; JS toggles the "hidden" class --}}
<div id="admin-backdrop" class="fixed inset-0 z-40 hidden bg-navy-950/60 lg:hidden"></div>

<aside
    id="admin-sidebar"
    class="adm-side fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col transition-transform duration-150 lg:sticky lg:top-0 lg:z-30 lg:h-screen lg:w-64 lg:shrink-0 lg:translate-x-0"
>
    {{-- Brand --}}
    <a
        href="{{ route('admin.dashboard') }}"
        class="adm-brand flex h-14 shrink-0 items-center gap-2.5 border-b border-white/10 px-4 font-semibold text-white"
        aria-label="{{ $branding->shortName }} Admin"
    >
        @if($branding->logoUrl)
            <img src="{{ $branding->logoUrl }}" alt="" class="h-8 w-8 shrink-0 rounded-md bg-white/10 object-contain" />
        @else
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-green-500 text-sm font-bold text-navy-950">
                {{ Illuminate\Support\Str::substr($branding->shortName, 0, 1) }}
            </span>
        @endif
        <span class="adm-label truncate text-sm">{{ $branding->shortName }} Admin</span>
    </a>

    <nav class="admin-nav-scroll min-h-0 flex-1 space-y-0.5 overflow-y-auto px-2.5 py-3" aria-label="Admin navigation">
        @foreach($navigation['top'] as $item)
            <a
                href="{{ $item['url'] }}"
                data-tip="{{ $item['label'] }}"
                @if($item['active']) aria-current="page" @endif
                class="adm-link {{ $item['active'] ? 'adm-link-active' : '' }}"
            >
                <x-icon :name="$item['icon']" class="h-[18px] w-[18px] shrink-0" />
                <span class="adm-label truncate">{{ $item['label'] }}</span>
            </a>
        @endforeach

        @if($navigation['groups'] !== [])
            <p class="adm-eyebrow">Manage</p>
        @endif

        {{-- Each "Management" group is a native <details>: no JS needed to
             expand it, and the group containing the current page is open. --}}
        @foreach($navigation['groups'] as $group)
            <details class="adm-group" data-group="{{ $group['key'] }}" @if($group['active']) open @endif>
                <summary
                    data-tip="{{ $group['label'] }}"
                    class="adm-link justify-between {{ $group['active'] ? 'text-white' : '' }}"
                >
                    <span class="flex min-w-0 items-center gap-2.5">
                        <x-icon :name="$group['icon']" class="h-[18px] w-[18px] shrink-0 {{ $group['active'] ? 'text-green-400' : '' }}" />
                        <span class="adm-label truncate">{{ $group['label'] }}</span>
                    </span>
                    <x-icon name="chevron-down" class="adm-chevron h-4 w-4 shrink-0 text-slate-500" />
                </summary>

                <div class="adm-children">
                    @foreach($group['items'] as $item)
                        <a
                            href="{{ $item['url'] }}"
                            @if($item['active']) aria-current="page" @endif
                            class="adm-sublink {{ $item['active'] ? 'adm-sublink-active' : '' }}"
                        >
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </details>
        @endforeach
    </nav>

    {{-- Desktop only: collapse to an icon rail --}}
    <div class="hidden shrink-0 border-t border-white/10 p-2.5 lg:block">
        <button
            type="button"
            id="admin-sidebar-collapse"
            class="adm-link"
            data-tip="Expand sidebar"
            aria-pressed="false"
        >
            <x-icon name="panel-left" class="h-[18px] w-[18px] shrink-0" />
            <span class="adm-label">Collapse</span>
        </button>
    </div>
</aside>
