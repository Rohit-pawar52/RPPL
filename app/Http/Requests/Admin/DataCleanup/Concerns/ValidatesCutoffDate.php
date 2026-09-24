<?php

namespace App\Http\Requests\Admin\DataCleanup\Concerns;

use App\Services\Settings\SettingsService;
use Illuminate\Support\Carbon;

/**
 * Phase 3.49 — every date-cutoff cleanup form (notifications, send
 * history, failed jobs) shares this exact rule: the admin picks a date
 * meaning "before this date, in MY timezone", not the server's. Laravel's
 * built-in `before_or_equal:today` compares against the SERVER's
 * default timezone (config('app.timezone'), UTC here) — an admin in
 * Asia/Kolkata (UTC+5:30) picking "today" in the last ~5.5 hours of
 * their day would be rejected by that rule even though they picked a
 * genuinely valid, non-future date. This validates against "today" in
 * system.display_timezone instead, matching the same setting
 * DisplayTimezoneFormatter already uses everywhere else.
 */
trait ValidatesCutoffDate
{
    protected function rejectFutureCutoffDate(string $attribute, mixed $value, \Closure $fail): void
    {
        $todayInDisplayTimezone = Carbon::now(app(SettingsService::class)->get('system.display_timezone'))->toDateString();

        if ((string) $value > $todayInDisplayTimezone) {
            $fail('The date may not be in the future.');
        }
    }
}
