@props(['name'])

{{-- Small hand-authored outline icon set (24x24, stroke-based), not a
     third-party icon library, so the admin UI needs zero new icon
     dependency. Add new icons here to keep them all in one place. --}}
@php
    $icons = [
        'home' => '<path d="M3 11.5 12 4l9 7.5" /><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9" />',
        'menu' => '<line x1="4" y1="7" x2="20" y2="7" /><line x1="4" y1="12" x2="20" y2="12" /><line x1="4" y1="17" x2="20" y2="17" />',
        'close' => '<line x1="6" y1="6" x2="18" y2="18" /><line x1="18" y1="6" x2="6" y2="18" />',
        'logout' => '<path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3" /><path d="M15 8l4 4-4 4" /><line x1="19" y1="12" x2="9" y2="12" />',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2" /><line x1="3" y1="10" x2="21" y2="10" /><line x1="8" y1="3" x2="8" y2="7" /><line x1="16" y1="3" x2="16" y2="7" />',
        'shield' => '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z" />',
        'user' => '<circle cx="12" cy="8" r="3.5" /><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6" />',
        'clipboard' => '<rect x="6" y="4" width="12" height="17" rx="2" /><rect x="9" y="2.5" width="6" height="3" rx="1" /><line x1="9" y1="10" x2="15" y2="10" /><line x1="9" y1="14" x2="15" y2="14" />',
        'users' => '<circle cx="9" cy="8" r="3" /><circle cx="16.5" cy="9.5" r="2.3" /><path d="M3.5 20c0-3 2.5-5 5.5-5s5.5 2 5.5 5" /><path d="M14.7 15.3c2.3.4 4.1 2.2 4.1 4.7" />',
        'map-pin' => '<path d="M12 21s7-6.5 7-11.5A7 7 0 0 0 5 9.5C5 14.5 12 21 12 21z" /><circle cx="12" cy="9.5" r="2.5" />',
        'trophy' => '<path d="M8 4h8v4a4 4 0 0 1-8 0V4z" /><path d="M8 5H5a3 3 0 0 0 3 5" /><path d="M16 5h3a3 3 0 0 1-3 5" /><line x1="12" y1="12" x2="12" y2="17" /><line x1="9" y1="20" x2="15" y2="20" /><line x1="12" y1="17" x2="12" y2="20" />',
        'chart-bar' => '<line x1="4" y1="20" x2="20" y2="20" /><rect x="6" y="12" width="3" height="8" /><rect x="11" y="8" width="3" height="12" /><rect x="16" y="4" width="3" height="16" />',
        'document-chart' => '<rect x="5" y="3" width="14" height="18" rx="2" /><line x1="8" y1="8" x2="16" y2="8" /><line x1="8" y1="12" x2="13" y2="12" /><line x1="8" y1="16" x2="11" y2="16" />',
        'cog' => '<circle cx="12" cy="12" r="3" /><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1" />',
        'eye' => '<path d="M2.5 12S5.5 5.5 12 5.5 21.5 12 21.5 12 18.5 18.5 12 18.5 2.5 12 2.5 12z" /><circle cx="12" cy="12" r="3" />',
        'pencil' => '<path d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3z" /><path d="M14.5 7 17 9.5" />',
        'trash' => '<path d="M5 7h14" /><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" /><path d="M7 7l1 12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-12" /><line x1="10" y1="11" x2="10" y2="16" /><line x1="14" y1="11" x2="14" y2="16" />',
        'camera' => '<path d="M4 8h3l1.5-2h7L17 8h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z" /><circle cx="12" cy="14" r="3.5" />',
        'star' => '<path d="M12 3.5l2.4 5 5.4.6-4 3.8 1 5.4L12 15.8l-4.8 2.5 1-5.4-4-3.8 5.4-.6L12 3.5z" />',
        'glove' => '<path d="M7 12V6a1.5 1.5 0 0 1 3 0v4M10 10V4.5a1.5 1.5 0 0 1 3 0V10M13 10V5.5a1.5 1.5 0 0 1 3 0V11" /><path d="M16 11V7.5a1.5 1.5 0 0 1 3 0V15c0 3.5-2.5 6-6 6H10c-2 0-3.5-1-4.5-2.5L4 15.5c-.5-.8-.2-1.8.6-2.2.7-.4 1.6-.1 2 .6L8 15" />',
        'undo' => '<path d="M7 8 3.5 11.5 7 15" /><path d="M3.5 11.5H14a5.5 5.5 0 1 1 0 11H10" />',
        'currency' => '<circle cx="12" cy="12" r="9" /><line x1="12" y1="6" x2="12" y2="18" /><path d="M15 9.5c0-1.4-1.5-2.5-3-2.5s-3 .9-3 2.2c0 3 6 1.6 6 4.6 0 1.3-1.5 2.2-3 2.2s-3-1.1-3-2.5" />',
    ];
@endphp

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>
    {!! $icons[$name] ?? '' !!}
</svg>
