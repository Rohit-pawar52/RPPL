@props(['label', 'value', 'icon' => null, 'subtext' => null])

<div class="flex items-center gap-3 rounded-lg border border-neutral-200 bg-white p-3.5">
    @if($icon)
        <span class="theme-primary-soft-bg theme-primary-text flex h-9 w-9 shrink-0 items-center justify-center rounded-md">
            <x-icon :name="$icon" class="h-5 w-5" />
        </span>
    @endif
    <div class="min-w-0">
        <p class="truncate text-xs text-neutral-500">{{ $label }}</p>
        <p class="text-lg font-semibold leading-tight text-neutral-900">{{ $value }}</p>
        @if($subtext)
            <p class="truncate text-[11px] text-neutral-400">{{ $subtext }}</p>
        @endif
    </div>
</div>
