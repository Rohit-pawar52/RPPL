<?php

namespace App\Console\Commands;

use App\Models\GameMatch;
use App\Services\MatchReminder\MatchReminderService;
use Illuminate\Console\Command;

/**
 * Registered in bootstrap/app.php's ->withSchedule() to run every
 * minute. GameMatch::scopeDueForReminder() returns the structural
 * candidates only (enabled, not yet dispatched, an eligible upcoming
 * status) — MatchReminderService::dispatchIfDue() does the actual
 * per-row due-instant check and the atomic claim, so this command
 * stays a thin loop.
 */
class DispatchMatchReminders extends Command
{
    protected $signature = 'rppl:dispatch-match-reminders';

    protected $description = 'Dispatch push notifications for matches whose reminder time is due';

    public function handle(MatchReminderService $service): int
    {
        $ids = GameMatch::query()->dueForReminder()->pluck('id');

        $dispatched = 0;

        foreach ($ids as $id) {
            if ($service->dispatchIfDue($id)) {
                $dispatched++;
            }
        }

        $this->info("Scanned {$ids->count()} candidate match(es), dispatched {$dispatched} reminder(s).");

        return self::SUCCESS;
    }
}
