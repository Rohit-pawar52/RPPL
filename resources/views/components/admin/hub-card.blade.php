@props(['label', 'value', 'sub' => null, 'href', 'icon' => null, 'extraHref' => null, 'extraLabel' => null])

{{-- A compact summary card that is also the way into that section of the
     season: the whole card is a link ("View more"), stretched over the card
     so the numbers are clickable too. An optional second link (extraHref)
     sits above it for a closely related page. --}}
<div {{ $attributes->merge(['class' => 'group relative flex flex-col justify-between rounded-lg border border-slate-200 bg-white p-3 transition hover:border-green-400 hover:shadow-sm']) }}>
    <span class="flex items-center gap-1.5 text-xs text-slate-500">
        @if($icon)<x-icon :name="$icon" class="h-4 w-4 text-green-700" />@endif
        {{ $label }}
    </span>
    <span class="mt-1 text-xl font-semibold leading-tight text-slate-900">{{ $value }}</span>
    <span class="mt-0.5 min-h-[1rem] truncate text-[11px] text-slate-400">{{ $sub }}</span>
    <span class="mt-1.5 flex items-center justify-between gap-2 text-[11px] font-medium">
        <a href="{{ $href }}" class="text-green-700 after:absolute after:inset-0 after:content-[''] group-hover:underline focus-visible:outline-2 focus-visible:outline-green-600">View more &rarr;</a>
        @if($extraHref)
            <a href="{{ $extraHref }}" class="relative z-10 text-slate-500 hover:text-green-700 hover:underline">{{ $extraLabel }}</a>
        @endif
    </span>
</div>
