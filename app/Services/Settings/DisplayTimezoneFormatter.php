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

    /**
     * The reverse direction for a plain admin DATE picker input (Phase
     * 3.49's Data Cleanup cutoffs) — "23 September 2026" means midnight
     * of that date IN THE DISPLAY TIMEZONE, converted to UTC for
     * comparison against stored timestamps, so the selected date itself
     * is correctly excluded from a "before this date" query. Distinct
     * from parseFromDisplayTimezone(): PHP's createFromFormat() fills
     * any time component NOT present in $format with the CURRENT
     * wall-clock time, so passing a bare 'Y-m-d' format there would
     * silently resolve to "right now" on the given date, not midnight.
     * This method makes that impossible by normalizing to startOfDay()
     * FIRST, then converting to UTC — the reverse order would give UTC
     * midnight, not the display timezone's midnight.
     */
    public function startOfDisplayDate(string $date): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $date, $this->settings->get('system.display_timezone'))
            ->startOfDay()
            ->setTimezone('UTC');
    }
}
