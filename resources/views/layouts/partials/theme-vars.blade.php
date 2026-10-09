{{-- Runtime CSS custom properties for the configured theme (Phase
     3.44B4, extended with the announcement ticker colors in 3.45 and with
     the button / hover / link / header / button-shape settings in the UI
     refresh) - rendered fresh on every request from $branding, so an
     admin's change takes effect on the very next page load, no npm build.
     Every color is ALWAYS an already-sanitized #RRGGBB string (see
     BrandingComposer::sanitizedColor()/optionalColor()) and the button
     radius one of three fixed lengths - never raw settings input - so
     interpolating them here can never inject arbitrary CSS. A color the
     admin left empty is derived with color-mix() from the main colors,
     never stored.

     `html:root` (not plain `:root`) so these win over the static fallback
     values in app.css whatever the order of the two in the page. The
     --color-navy-* lines re-point Tailwind's navy palette, which is what
     the public header / footer and the admin sidebar are drawn with, at the
     configured header color. --}}
@php
    $primary = $branding->primaryColor;
    $button = $branding->buttonColor;
@endphp
<style>
    html:root {
        --rppl-primary: {{ $primary }};
        --rppl-primary-hover: color-mix(in srgb, {{ $primary }} 85%, black);
        --rppl-primary-soft: color-mix(in srgb, {{ $primary }} 12%, white);
        --rppl-primary-fg: {{ $branding->primaryForegroundColor }};
        --rppl-secondary: {{ $branding->secondaryColor }};
        --rppl-link-hover: {{ $branding->linkHoverColor ?? 'color-mix(in srgb, '.$primary.' 80%, black)' }};
        --rppl-button: {{ $button }};
        --rppl-button-hover: {{ $branding->buttonHoverColor ?? 'color-mix(in srgb, '.$button.' 85%, black)' }};
        --rppl-button-fg: {{ $branding->buttonForegroundColor }};
        --rppl-hover-bg: {{ $branding->hoverColor ?? 'color-mix(in srgb, '.$primary.' 7%, white)' }};
        --rppl-accent-on-dark: color-mix(in srgb, {{ $primary }} 55%, white);
        --rppl-radius-btn: {{ $branding->buttonRadius }};
        --rppl-header: {{ $branding->headerColor }};
        --rppl-announcement-bg: {{ $branding->announcementBackgroundColor }};
        --rppl-announcement-text: {{ $branding->announcementTextColor }};
        --color-navy-900: {{ $branding->headerColor }};
        --color-navy-950: color-mix(in srgb, {{ $branding->headerColor }} 78%, black);
        --color-navy-800: color-mix(in srgb, {{ $branding->headerColor }} 90%, white);
        --color-navy-700: color-mix(in srgb, {{ $branding->headerColor }} 78%, white);
    }
</style>
