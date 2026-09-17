@props(['route' => null, 'icon', 'active' => false, 'disabled' => false])

@if($disabled)
    <span
        aria-disabled="true"
        class="flex items-center justify-between gap-2 rounded-md px-3 py-2 text-[13px] text-neutral-400 select-none cursor-not-allowed"
    >
        <span class="flex items-center gap-2">
            <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
            {{ $slot }}
        </span>
        <span class="rounded border border-neutral-200 px-1 text-[10px] font-medium uppercase tracking-wide text-neutral-300">
            Soon
        </span>
    </span>
@else
    <a
        href="{{ $route }}"
        class="flex items-center gap-2 rounded-md px-3 py-2 text-[13px] {{ $active ? 'bg-blue-50 font-medium text-blue-700' : 'text-neutral-600 hover:bg-neutral-100' }}"
    >
        <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
        {{ $slot }}
    </a>
@endif
