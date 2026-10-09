@props(['name'])

{{-- Small stroke icons used by the admin-ops pages (registrations, matches,
     scoring, seasons, auction) on top of the shared <x-icon> set. Same
     look: 24x24, 1.5-2px outline, currentColor. --}}
@php
    $icons = [
        'check' => '<path d="M5 12.5l4.5 4.5L19 7.5" />',
        'x' => '<path d="M6 6l12 12M18 6L6 18" />',
        'plus' => '<path d="M12 5v14M5 12h14" />',
        'minus' => '<path d="M5 12h14" />',
        'search' => '<circle cx="11" cy="11" r="6.5" /><path d="M20 20l-4.2-4.2" />',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6" />',
        'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6" />',
        'chevron-right' => '<path d="M9 6l6 6-6 6" />',
        'chevron-left' => '<path d="M15 6l-6 6 6 6" />',
        'chevron-down' => '<path d="M6 9l6 6 6-6" />',
        'dots' => '<circle cx="5" cy="12" r="1.2" /><circle cx="12" cy="12" r="1.2" /><circle cx="19" cy="12" r="1.2" />',
        'upload' => '<path d="M12 16V4M7 9l5-5 5 5" /><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3" />',
        'download' => '<path d="M12 4v12M7 11l5 5 5-5" /><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3" />',
        'image' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2" /><circle cx="9" cy="10" r="1.6" /><path d="M20.5 16l-5-5-8 8" />',
        'clock' => '<circle cx="12" cy="12" r="8.5" /><path d="M12 7.5V12l3 2" />',
        'bolt' => '<path d="M13 3L5 13.5h6L10 21l8-10.5h-6L13 3z" />',
        'flag' => '<path d="M6 21V4" /><path d="M6 5h11l-2 4 2 4H6" />',
        'phone' => '<path d="M6.5 4h3l1.5 4-2 1.5a11 11 0 0 0 5.5 5.5l1.5-2 4 1.5v3a2 2 0 0 1-2 2A15 15 0 0 1 4.5 6a2 2 0 0 1 2-2z" />',
        'play' => '<path d="M8 5.5v13l11-6.5-11-6.5z" />',
        'pause' => '<path d="M8 5v14M16 5v14" />',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14-4.5L4 9" /><path d="M4 4.5V9h4.5" /><path d="M4 13a8 8 0 0 0 14 4.5L20 15" /><path d="M20 19.5V15h-4.5" />',
        'list' => '<path d="M8 7h12M8 12h12M8 17h12" /><circle cx="4" cy="7" r=".8" /><circle cx="4" cy="12" r=".8" /><circle cx="4" cy="17" r=".8" />',
        'lock' => '<rect x="5" y="11" width="14" height="9" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" />',
        'info' => '<circle cx="12" cy="12" r="8.5" /><path d="M12 11v5M12 7.8v.4" />',
        'warning' => '<path d="M12 4l9 16H3L12 4z" /><path d="M12 10v4.5M12 17.2v.3" />',
        'ball' => '<circle cx="12" cy="12" r="8.5" /><path d="M6.5 6.5c2.5 2 2.5 9 0 11M17.5 6.5c-2.5 2-2.5 9 0 11" />',
        'bat' => '<path d="M14.5 4.5l5 5-8.5 8.5-5-5 8.5-8.5z" /><path d="M6 13l-2.5 6.5L10 17" />',
        'shuffle' => '<path d="M4 7h3.5c3 0 4 2 5 5s2 5 5 5H20M4 17h3.5c1.5 0 2.5-.6 3.2-1.6M20 7h-2.5c-1.5 0-2.5.6-3.2 1.6" /><path d="M18 5l2 2-2 2M18 15l2 2-2 2" />',
        'tag' => '<path d="M3.5 12.2V5a1.5 1.5 0 0 1 1.5-1.5h7.2l8.3 8.3a1.5 1.5 0 0 1 0 2.1l-5.9 5.9a1.5 1.5 0 0 1-2.1 0L3.5 12.2z" /><circle cx="8" cy="8" r="1.3" />',
    ];
@endphp

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes->merge(['class' => 'h-4 w-4 shrink-0']) }}>
    {!! $icons[$name] ?? '' !!}
</svg>
