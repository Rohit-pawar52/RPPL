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

    /**
     * Midnight at the END of that display-timezone calendar day — i.e. the
     * start of the next day — in UTC. Used as the exclusive upper bound
     * of a "up to and including this date" filter on a UTC-stored
     * datetime column, so a record at 11:59 PM on that day is in and one
     * at 12:00 AM the next day is out.
     */
    public function startOfNextDisplayDate(string $date): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $date, $this->settings->get('system.display_timezone'))
            ->startOfDay()
            ->addDay()
            ->setTimezone('UTC');
    }

    /**
     * An admin-typed date or date-time of any shape the `date` validation
     * rule lets through ("2026-10-03T00:20" from a datetime-local input,
     * "2026-10-03 00:20:00", a plain "2026-10-03" meaning that day's
     * midnight) read in the display timezone and returned in UTC, like
     * every other stored datetime. A value that carries its own offset
     * keeps it. Null for a blank value.
     */
    public function parseLenientFromDisplayTimezone(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value, $this->settings->get('system.display_timezone'))->setTimezone('UTC');
    }
}
