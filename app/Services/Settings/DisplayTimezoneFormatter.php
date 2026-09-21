<?php

namespace App\Services\Settings;

use Illuminate\Support\Carbon;

/**
 * Bridges a stored (UTC) timestamp and its DISPLAY in
 * system.display_timezone — never touches config('app.timezone'),
 * Carbon's global timezone, or how a timestamp is persisted.
 */
class DisplayTimezoneFormatter
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * A stored Carbon instance is always copied first, so the original
     * (e.g. an Eloquent attribute) is never mutated by formatting it
     * for display (Phase 3.44B3).
     */
    public function format(?Carbon $dateTime, string $format): ?string
    {
        if ($dateTime === null) {
            return null;
        }

        return $dateTime->copy()
            ->setTimezone($this->settings->get('system.display_timezone'))
            ->format($format);
    }

    /**
     * The reverse direction (Phase 3.45) — an admin's own datetime-local
     * input (e.g. "2026-01-01T19:00") is a NAIVE string meant relative
     * to the configured display timezone, not UTC. This interprets it
     * as being in that timezone and converts it to UTC, matching every
     * other stored datetime column's convention. Returns null for a
     * blank/absent value — presence is a validation concern, not this
     * method's.
     */
    public function parseFromDisplayTimezone(?string $value, string $format = 'Y-m-d\TH:i'): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::createFromFormat($format, $value, $this->settings->get('system.display_timezone'))
            ->setTimezone('UTC');
    }
}
