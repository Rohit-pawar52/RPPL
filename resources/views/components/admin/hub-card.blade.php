@props(['label', 'value', 'sub' => null, 'href', 'icon' => null, 'extraHref' => null, 'extraLabel' => null])

{{-- A summary tile that is also the way into that section of the season: the
     whole card is a link ("View more"), stretched over the card so the
     numbers are clickable too. An optional second link (extraHref) sits above
     it for a closely related page. --}}
<div {{ $attributes->merge(['class' => 'group relative flex flex-col justify-between rounded-xl border border-line bg-white p-4 shadow-card transition duration-150 hover:-translate-y-px hover:border-brand/40 hover:shadow-raised motion-reduce:transition-none']) }}>
    <span class="flex items-center gap-2 text-xs font-medium text-slate-500">
        @if($icon)
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand">
                <x-admin.icon :name="$icon" class="h-4 w-4" />
            </span>
        @endif
        <span class="min-w-0 truncate">{{ $label }}</span>
    </span>
    <span class="mt-2.5 text-2xl font-bold leading-tight tracking-tight tabular-nums text-slate-900">{{ $value }}</span>
    <span class="mt-0.5 min-h-[1rem] truncate text-[11px] text-slate-500">{{ $sub }}</span>
    <span class="mt-2 flex flex-wrap items-center justify-between gap-x-2 gap-y-0.5 text-[11px] font-semibold">
        <a href="{{ $href }}" class="whitespace-nowrap text-link after:absolute after:inset-0 after:content-[''] group-hover:text-link-hover focus-visible:outline-2 focus-visible:outline-brand">{{ __('View more') }} &rarr;</a>
        @if($extraHref)
            <a href="{{ $extraHref }}" class="relative z-10 whitespace-nowrap text-slate-500 hover:text-brand hover:underline">{{ $extraLabel }}</a>
        @endif
    </span>
</div>
