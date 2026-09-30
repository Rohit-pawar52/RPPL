@props(['label', 'value', 'icon' => null, 'subtext' => null])

<div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3.5">
    @if($icon)
        <span class="bg-green-50 text-green-700 flex h-9 w-9 shrink-0 items-center justify-center rounded-md">
            <x-icon :name="$icon" class="h-5 w-5" />
        </span>
    @endif
    <div class="min-w-0">
        <p class="truncate text-xs text-slate-500">{{ $label }}</p>
        <p class="text-lg font-semibold leading-tight text-slate-900">{{ $value }}</p>
        @if($subtext)
            <p class="truncate text-[11px] text-neutral-400">{{ $subtext }}</p>
        @endif
    </div>
</div>
