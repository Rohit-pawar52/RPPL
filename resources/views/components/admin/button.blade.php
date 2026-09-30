@props(['href' => null, 'variant' => 'primary', 'size' => 'md', 'icon' => null, 'type' => 'submit'])

@php
    $variants = [
        'primary' => 'bg-green-600 text-white hover:bg-green-700 border border-green-600 hover:border-green-700',
        'secondary' => 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-300',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 border border-red-600 hover:border-red-700',
        'ghost' => 'bg-transparent text-slate-600 hover:bg-slate-100 border border-transparent',
    ];
    $sizes = [
        'sm' => 'px-2.5 py-1 text-xs gap-1',
        'md' => 'px-3.5 py-2 text-[13px] gap-1.5',
    ];
    $classes = 'inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-md font-medium transition-colors '
        . 'focus:outline-none focus-visible:ring-2 focus-visible:ring-green-500 focus-visible:ring-offset-1 '
        . 'disabled:cursor-not-allowed disabled:opacity-60 '
        . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-icon :name="$icon" class="h-4 w-4 shrink-0" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-icon :name="$icon" class="h-4 w-4 shrink-0" />@endif
        {{ $slot }}
    </button>
@endif
