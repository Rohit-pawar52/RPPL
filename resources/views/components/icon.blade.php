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
        'bell' => '<path d="M12 3a5 5 0 0 0-5 5v3.5c0 .8-.3 1.6-.9 2.1L5 15h14l-1.1-1.4a3 3 0 0 1-.9-2.1V8a5 5 0 0 0-5-5z" /><path d="M9.5 18a2.5 2.5 0 0 0 5 0" />',
        'megaphone' => '<path d="M3 9v6h3l5 4V5L6 9H3z" /><path d="M11 8l8-3v14l-8-3" /><path d="M19 10a3 3 0 0 1 0 4" />',
        'play' => '<circle cx="12" cy="12" r="9" /><path d="M10 8.5v7l6-3.5-6-3.5z" />',
        'newspaper' => '<path d="M5 4h11a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H6a2 2 0 0 1-2-2V5a1 1 0 0 1 1-1z" /><path d="M17 8h2a1 1 0 0 1 1 1v8a2 2 0 0 1-2 2h-1" /><line x1="8" y1="8" x2="13" y2="8" /><line x1="8" y1="12" x2="13" y2="12" /><line x1="8" y1="16" x2="11" y2="16" />',
        'chevron-down' => '<path d="M6 9l6 6 6-6" />',
        'panel-left' => '<rect x="3" y="4" width="18" height="16" rx="2" /><line x1="9" y1="4" x2="9" y2="20" />',
        'external' => '<path d="M14 4h6v6" /><line x1="20" y1="4" x2="11" y2="13" /><path d="M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4" />',
        'gavel' => '<path d="m14 13-8.381 8.38a1 1 0 0 1-3.001-3L11 9.999" /><path d="M15.973 4.027A13 13 0 0 0 5.902 2.373c-1.398.342-1.092 2.158.277 2.601a19.9 19.9 0 0 1 5.822 3.024" /><path d="M16.001 11.999a19.9 19.9 0 0 1 3.024 5.824c.444 1.369 2.26 1.676 2.603.278A13 13 0 0 0 20 8.069" /><path d="M18.352 3.352a1.205 1.205 0 0 0-1.704 0l-5.296 5.296a1.205 1.205 0 0 0 0 1.704l2.296 2.296a1.205 1.205 0 0 0 1.704 0l5.296-5.296a1.205 1.205 0 0 0 0-1.704z" />',
        'book' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H12v18H6.5A2.5 2.5 0 0 1 4 18.5v-13z" /><path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H12v18h5.5a2.5 2.5 0 0 0 2.5-2.5v-13z" />',
        'key' => '<path d="m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4" /><path d="m21 2-9.6 9.6" /><circle cx="7.5" cy="15.5" r="5.5" />',
        'chevron-right' => '<path d="m9 18 6-6-6-6" />',
        'chevron-left' => '<path d="m15 18-6-6 6-6" />',
        'arrow-right' => '<path d="M5 12h14" /><path d="m12 5 7 7-7 7" />',
        'arrow-left' => '<path d="M19 12H5" /><path d="m12 19-7-7 7-7" />',
        'search' => '<circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" />',
        'table' => '<rect x="3" y="4" width="18" height="16" rx="2" /><path d="M3 10h18" /><path d="M3 15h18" /><path d="M9 4v16" />',
        'help' => '<circle cx="12" cy="12" r="9.5" /><path d="M9.5 9.3a2.6 2.6 0 0 1 5 .9c0 1.7-2.5 2.2-2.5 3.8" /><path d="M12 17.2h.01" />',
        'lock' => '<rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7.5a4 4 0 0 1 8 0V11" />',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3.5 7 8.5 6 8.5-6" />',
        'phone' => '<path d="M5 4h3.5l1.5 4-2 1.5a11 11 0 0 0 5.5 5.5L15 13l4 1.5V18a2 2 0 0 1-2 2A13 13 0 0 1 3 6a2 2 0 0 1 2-2z" />',
        'chat' => '<path d="M20 11.5a7.5 7.5 0 0 1-11 6.6L4 19.5l1.4-4.5A7.5 7.5 0 1 1 20 11.5z" />',
        'clock' => '<circle cx="12" cy="12" r="9.5" /><path d="M12 7v5l3.2 1.9" />',
        'alert' => '<path d="M12 3.5 2.5 19.5h19L12 3.5z" /><path d="M12 10v4.5" /><path d="M12 17.2h.01" />',
        'info' => '<circle cx="12" cy="12" r="9.5" /><path d="M12 11v5.5" /><path d="M12 7.8h.01" />',
        'check-circle' => '<circle cx="12" cy="12" r="9.5" /><path d="m8.5 12.3 2.5 2.5 4.7-5" />',
        'wrench' => '<path d="M14.5 6.5a4 4 0 0 0 4.9 4.9l-9.6 9.6a2.1 2.1 0 0 1-3-3l9.6-9.6a4 4 0 0 0-1.9-1.9z" /><path d="m14.5 6.5 2.3-2.3a4 4 0 0 1 3.6 5.3" />',
        'ban' => '<circle cx="12" cy="12" r="9.5" /><path d="m5.3 5.3 13.4 13.4" />',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.3-4.4L4 8.5" /><path d="M4 4v4.5h4.5" /><path d="M4 13a8 8 0 0 0 14.3 4.4L20 15.5" /><path d="M20 20v-4.5h-4.5" />',
        'bolt' => '<path d="M13 2.5 4.5 13.5H11l-1 8 8.5-11H12l1-8z" />',
        'user-plus' => '<circle cx="9.5" cy="8" r="3.5" /><path d="M3 20c0-3.3 2.9-5.5 6.5-5.5S16 16.7 16 20" /><path d="M19 8v6" /><path d="M16 11h6" />',
        'ball' => '<circle cx="12" cy="12" r="9.5" /><path d="M6 5.2c3 2.6 3 11 0 13.6" /><path d="M18 5.2c-3 2.6-3 11 0 13.6" /><path d="M9.5 8.5l1 .6M9.3 12h1.2M9.5 15.5l1-.6M14.5 8.5l-1 .6M14.7 12h-1.2M14.5 15.5l-1-.6" />',
        'share' => '<circle cx="17.5" cy="5.5" r="2.5" /><circle cx="6.5" cy="12" r="2.5" /><circle cx="17.5" cy="18.5" r="2.5" /><path d="m8.7 10.8 6.6-4M8.7 13.2l6.6 4" />',
        'list' => '<path d="M8.5 6.5H20" /><path d="M8.5 12H20" /><path d="M8.5 17.5H20" /><path d="M4 6.5h.01" /><path d="M4 12h.01" /><path d="M4 17.5h.01" />',
    ];
@endphp

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>
    {!! $icons[$name] ?? '' !!}
</svg>
