<?php

namespace App\Services\Settings;

use Illuminate\Support\Carbon;

/**
 * Formats a stored (UTC) timestamp for DISPLAY in system.display_timezone
 * only (Phase 3.44B3) — never touches config('app.timezone'), Carbon's
 * global timezone, or how a timestamp is persisted. A stored Carbon
 * instance is always copied first, so the original (e.g. an Eloquent
 * attribute) is never mutated by formatting it for display.
 */
class DisplayTimezoneFormatter
{
    public function __construct(private readonly SettingsService $settings) {}

    public function format(?Carbon $dateTime, string $format): ?string
    {
        if ($dateTime === null) {
            return null;
        }

        return $dateTime->copy()
            ->setTimezone($this->settings->get('system.display_timezone'))
            ->format($format);
    }
}
