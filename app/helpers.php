<?php

use App\Services\Settings\DisplayTimezoneFormatter;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Carbon;

if (! function_exists('display_datetime')) {
    /**
     * Formats a stored (UTC) timestamp in system.display_timezone for
     * Blade views (Phase 3.44B3) — the single call site templates use
     * instead of $dateTime->format(...) directly, so every "scheduled
     * at" style timestamp across the site converts the same way. Never
     * changes the underlying value or the app's runtime timezone; see
     * DisplayTimezoneFormatter's own docblock.
     */
    function display_datetime(?Carbon $dateTime, string $format): ?string
    {
        return app(DisplayTimezoneFormatter::class)->format($dateTime, $format);
    }
}

if (! function_exists('money')) {
    /**
     * Formats a numeric amount with the configured
     * system.currency_symbol (pre-UAT polish pass) — the single call
     * site templates use instead of hardcoding '₹' directly, so every
     * monetary display across admin, public, and PDF views reads the
     * same configured symbol. Formatting only: never touches how an
     * amount is calculated, validated, or stored. The registry default
     * for system.currency_symbol is '₹', so with no Settings row
     * present this renders byte-for-byte identical to the previously
     * hardcoded value. Always used via {{ money(...) }}, never
     * {!! !!} — the configured symbol is plain admin-entered text
     * (max:10, no character restriction), so it must go through
     * Blade's normal escaping like any other free-text Settings value
     * (application_name, tagline, footer_text, ...).
     */
    function money(float|int|string|null $amount, int $decimals = 2): string
    {
        $symbol = app(SettingsService::class)->get('system.currency_symbol');

        return $symbol.number_format((float) ($amount ?? 0), $decimals);
    }
}
