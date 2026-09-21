{{-- Runtime CSS custom properties for the three configured theme colors
     (Phase 3.44B4) — rendered fresh on every request from $branding, so
     an admin's color change takes effect on the very next page load,
     no npm build. $branding->primaryColor/secondaryColor/buttonColor/
     *ForegroundColor are ALWAYS already-sanitized #RRGGBB strings (see
     BrandingComposer::sanitizedColor()/ForegroundContrast) — never raw
     settings input — so interpolating them directly here can never
     inject arbitrary CSS. Derived shades (hover/soft) use color-mix(),
     never a stored setting, never a second cache. --}}
<style>
    :root {
        --rppl-primary: {{ $branding->primaryColor }};
        --rppl-primary-hover: color-mix(in srgb, {{ $branding->primaryColor }} 85%, black);
        --rppl-primary-soft: color-mix(in srgb, {{ $branding->primaryColor }} 12%, white);
        --rppl-primary-fg: {{ $branding->primaryForegroundColor }};
        --rppl-secondary: {{ $branding->secondaryColor }};
        --rppl-button: {{ $branding->buttonColor }};
        --rppl-button-hover: color-mix(in srgb, {{ $branding->buttonColor }} 85%, black);
        --rppl-button-fg: {{ $branding->buttonForegroundColor }};
    }
</style>
