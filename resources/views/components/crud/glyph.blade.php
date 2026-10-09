@props(['name'])

{{-- A few outline icons the shared <x-icon> set does not have yet (plus,
     search, download ...), same 24x24 stroke style. Wish: move these into
     x-icon, then this file can go. --}}
@php
    $glyphs = [
        'plus' => '<path d="M12 5v14M5 12h14" />',
        'search' => '<circle cx="11" cy="11" r="6.5" /><path d="m20 20-3.5-3.5" />',
        'download' => '<path d="M12 4v11" /><path d="m7 11 5 5 5-5" /><path d="M5 20h14" />',
        'arrow-left' => '<path d="M19 12H5" /><path d="m11 18-6-6 6-6" />',
        'arrow-right' => '<path d="M5 12h14" /><path d="m13 6 6 6-6 6" />',
        'x' => '<path d="M6 6l12 12M18 6 6 18" />',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5" />',
        'phone' => '<path d="M5 4h3.5l1.5 4-2 1.5a11 11 0 0 0 6.5 6.5L16 14l4 1.5V19a1 1 0 0 1-1 1A15 15 0 0 1 4 5a1 1 0 0 1 1-1z" />',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3.5 7 8.5 6 8.5-6" />',
        'lock' => '<rect x="5" y="11" width="14" height="9" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" />',
        'film' => '<rect x="3" y="4" width="18" height="16" rx="2" /><path d="M7 4v16M17 4v16M3 9h4M3 15h4M17 9h4M17 15h4" />',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="9" cy="10" r="1.6" /><path d="m21 16-5-5-8 9" />',
        'filter' => '<path d="M4 6h16M7 12h10M10 18h4" />',
        'send' => '<path d="m21 3-9.5 18-2.5-8-6-2.5L21 3z" /><path d="M21 3 9 13" />',
        'clock' => '<circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />',
        'income' => '<path d="M7 7l10 10" /><path d="M17 8v9H8" />',
        'expense' => '<path d="M7 17 17 7" /><path d="M8 7h9v9" />',
        'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3z" /><path d="M9 8h6M9 12h6" />',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1" /><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1" />',
        'trash' => '<path d="M5 7h14" /><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" /><path d="M7 7l1 12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-12" />',
        'alert' => '<path d="M12 4 3 19h18L12 4z" /><path d="M12 10v4M12 17v.01" />',
        'info' => '<circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8v.01" />',
        'sparkle' => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3z" />',
        'chevron-right' => '<path d="m9 6 6 6-6 6" />',
        'external' => '<path d="M14 4h6v6" /><path d="M20 4 11 13" /><path d="M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4" />',
    ];
@endphp

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes->merge(['class' => 'h-4 w-4']) }}>
    {!! $glyphs[$name] ?? '' !!}
</svg>
