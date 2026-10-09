@props(['name'])

{{-- The admin panel's extra icons (plus, search, check, arrows, ...) on top of
     <x-icon>. Same 24px outline style; any name not listed here falls back to
     <x-icon name="...">, so <x-admin.icon> can be used for every icon. --}}
@php
    $extra = [
        'plus' => '<path d="M12 5v14M5 12h14" />',
        'search' => '<circle cx="11" cy="11" r="6.5" /><path d="m20 20-3.5-3.5" />',
        'filter' => '<path d="M4 5h16l-6 7.5V19l-4 1.5v-8L4 5z" />',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5" />',
        'check-circle' => '<circle cx="12" cy="12" r="9" /><path d="m8 12.5 2.8 2.8L16 9.8" />',
        'x' => '<path d="M6 6l12 12M18 6 6 18" />',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6" />',
        'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6" />',
        'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6" />',
        'arrow-down' => '<path d="M12 5v14M6 13l6 6 6-6" />',
        'chevron-right' => '<path d="m9 6 6 6-6 6" />',
        'chevron-up-down' => '<path d="m8 9 4-4 4 4M8 15l4 4 4-4" />',
        'eye-off' => '<path d="M3 3l18 18" /><path d="M10.6 5.2A9.7 9.7 0 0 1 12 5c6.5 0 9.5 7 9.5 7a15 15 0 0 1-3 4M6.6 6.7C3.9 8.5 2.5 12 2.5 12s3 7 9.5 7c1.5 0 2.8-.4 4-.9" /><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />',
        'download' => '<path d="M12 4v11M7 11l5 5 5-5M5 20h14" />',
        'upload' => '<path d="M12 16V5M7 9l5-5 5 5M5 20h14" />',
        'printer' => '<path d="M7 9V4h10v5" /><rect x="5" y="9" width="14" height="8" rx="2" /><path d="M8 14h8v6H8z" />',
        'clock' => '<circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />',
        'bolt' => '<path d="M13 3 5 13.5h6L10 21l8-10.5h-6L13 3z" />',
        'alert' => '<path d="M12 4 3 19.5h18L12 4z" /><path d="M12 10v4M12 17h.01" />',
        'info' => '<circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8h.01" />',
        'command' => '<path d="M9 6a3 3 0 1 0-3 3h12a3 3 0 1 0-3-3v12a3 3 0 1 0 3-3H6a3 3 0 1 0 3 3V6z" />',
        'lock' => '<rect x="5" y="11" width="14" height="9" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" />',
        'credit-card' => '<rect x="3" y="6" width="18" height="12" rx="2" /><path d="M3 10h18M7 15h3" />',
        'inbox' => '<path d="M4 13 6.5 5h11L20 13v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-5z" /><path d="M4 13h4.5l1 2h5l1-2H20" />',
        'sparkles' => '<path d="M12 4l1.8 4.7L18.5 10.5l-4.7 1.8L12 17l-1.8-4.7L5.5 10.5l4.7-1.8L12 4z" /><path d="M19 16v4M17 18h4" />',
        'dots' => '<circle cx="5" cy="12" r="1.2" /><circle cx="12" cy="12" r="1.2" /><circle cx="19" cy="12" r="1.2" />',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.5-4M4 4v4h4M4 13a8 8 0 0 0 14.5 4M20 20v-4h-4" />',
        'globe' => '<circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3c2.5 2.5 3.5 5.5 3.5 9s-1 6.5-3.5 9c-2.5-2.5-3.5-5.5-3.5-9s1-6.5 3.5-9z" />',
    ];
@endphp

@if(isset($extra[$name]))
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>{!! $extra[$name] !!}</svg>
@else
    <x-icon :name="$name" {{ $attributes }} />
@endif
