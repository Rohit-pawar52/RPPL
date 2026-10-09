@props(['route' => null, 'icon', 'active' => false, 'disabled' => false])

@if($disabled)
    <span
        aria-disabled="true"
        class="flex cursor-not-allowed select-none items-center justify-between gap-2 rounded-lg px-3 py-2 text-[13px] text-slate-400"
    >
        <span class="flex items-center gap-2">
            <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
            {{ $slot }}
        </span>
        <span class="rounded border border-slate-200 px-1 text-[10px] font-medium uppercase tracking-wide text-slate-300">
            Soon
        </span>
    </span>
@else
    <a
        href="{{ $route }}"
        class="flex min-h-10 items-center gap-2 rounded-lg px-3 py-2 text-[13px] transition-colors {{ $active ? 'theme-primary-soft-bg theme-primary-text font-semibold' : 'text-slate-600 hover:bg-hover hover:text-slate-900' }}"
    >
        <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
        {{ $slot }}
    </a>
@endif
