{{--
    A KPI tile: a big number with a label, an optional icon and a small line.

    Props
      label    what is counted
      value    the figure (already formatted)
      icon     an <x-icon> / <x-admin.icon> name
      subtext  a small line under the figure
      href     (optional) makes the whole tile a link, e.g. to the filtered list
      tone     brand (default) | amber | green | red | sky - the icon chip colour;
               use a tone only when it MEANS something (pending = amber, paid = green)
      hint     (optional) a short coloured note on the right of the figure, e.g. "needs action"
--}}
@props(['label', 'value', 'icon' => null, 'subtext' => null, 'href' => null, 'tone' => 'brand', 'hint' => null])

@php
    $chip = match ($tone) {
        'amber' => 'bg-amber-50 text-amber-600',
        'green' => 'bg-green-50 text-green-600',
        'red' => 'bg-red-50 text-red-600',
        'sky' => 'bg-sky-50 text-sky-600',
        default => 'bg-brand-soft text-brand',
    };
@endphp

<div {{ $attributes->class([
    'group relative flex flex-col gap-2.5 rounded-xl border border-line bg-white p-4 shadow-card sm:flex-row sm:items-start sm:gap-3',
    'transition duration-150 hover:-translate-y-px hover:border-brand/40 hover:shadow-raised motion-reduce:transition-none' => $href,
]) }}>
    @if($icon)
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $chip }}">
            <x-admin.icon :name="$icon" class="h-5 w-5" />
        </span>
    @endif
    <div class="min-w-0 flex-1">
        <p class="text-xs font-medium leading-4 text-slate-500">
            @if($href)
                <a href="{{ $href }}" class="after:absolute after:inset-0 after:content-[''] focus-visible:outline-2 focus-visible:outline-brand">{{ $label }}</a>
            @else
                {{ $label }}
            @endif
        </p>
        <p class="mt-0.5 flex flex-wrap items-baseline gap-x-2">
            <span class="text-2xl font-bold leading-tight tracking-tight tabular-nums text-slate-900">{{ $value }}</span>
            @if($hint)<span class="text-[11px] font-semibold text-slate-500">{{ $hint }}</span>@endif
        </p>
        @if($subtext)
            <p class="mt-0.5 text-[11px] leading-4 text-slate-500">{{ $subtext }}</p>
        @endif
    </div>
    @if($href)
        <x-admin.icon name="arrow-right" class="absolute right-3 top-3 h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand sm:static sm:mt-1" />
    @endif
</div>
