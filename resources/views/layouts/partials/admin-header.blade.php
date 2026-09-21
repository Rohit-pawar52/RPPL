<header class="sticky top-0 z-40 border-b border-neutral-200 bg-white">
    <div class="flex h-14 items-center justify-between gap-3 px-4 lg:px-6">
        <div class="flex items-center gap-3">
            <button
                id="admin-sidebar-toggle"
                type="button"
                class="-ml-1 rounded-md p-2 text-neutral-500 hover:bg-neutral-100 lg:hidden"
                aria-label="Toggle navigation"
                aria-controls="admin-sidebar"
                aria-expanded="false"
            >
                <x-icon name="menu" class="h-5 w-5" />
            </button>

            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 font-semibold text-neutral-900">
                @if($branding->logoUrl)
                    <img src="{{ $branding->logoUrl }}" alt="{{ $branding->applicationName }}" class="h-7 w-7 rounded-md object-contain" />
                @else
                    <span class="flex h-7 w-7 items-center justify-center rounded-md bg-blue-600 text-xs font-bold text-white">
                        {{ Illuminate\Support\Str::substr($branding->shortName, 0, 1) }}
                    </span>
                @endif
                <span class="hidden text-sm sm:inline">{{ $branding->shortName }} Admin</span>
            </a>
        </div>

        <div class="flex items-center gap-3">
            <div class="hidden text-right sm:block">
                <p class="text-xs font-medium leading-tight text-neutral-800">{{ auth()->user()->name }}</p>
                <p class="text-[11px] leading-tight text-neutral-500">{{ auth()->user()->role->name }}</p>
            </div>

            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-neutral-100 text-xs font-semibold uppercase text-neutral-600">
                {{ Illuminate\Support\Str::substr(auth()->user()->name, 0, 1) }}
            </span>

            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button
                    type="submit"
                    class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                >
                    <x-icon name="logout" class="h-4 w-4" />
                    <span class="hidden sm:inline">Logout</span>
                </button>
            </form>
        </div>
    </div>
</header>
