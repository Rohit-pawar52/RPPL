<?php

use App\Services\Settings\DisplayTimezoneFormatter;
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
