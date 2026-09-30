<?php

namespace App\Console\Commands;

use App\Services\TournamentDay\TournamentDayReminderService;
use Illuminate\Console\Command;

/**
 * Intended to run every minute from the scheduler. dueEditionIds() only
 * returns candidates inside the morning send window (configured time up
 * to 12:00 in the display timezone) — TournamentDayReminderService::
 * dispatchIfDue() does the per-edition locked claim, so this command
 * stays a thin loop, exactly like DispatchMatchReminders.
 */
class DispatchTournamentDayReminders extends Command
{
    protected $signature = 'rppl:dispatch-tournament-day-reminders';

    protected $description = 'Dispatch the once-per-edition morning push summarizing today\'s matches';

    public function handle(TournamentDayReminderService $service): int
    {
        $now = now();
        $ids = $service->dueEditionIds($now);

        $dispatched = 0;

        foreach ($ids as $id) {
            if ($service->dispatchIfDue($id, $now)) {
                $dispatched++;
            }
        }

        $this->info("Scanned {$ids->count()} candidate edition(s), dispatched {$dispatched} tournament-day reminder(s).");

        return self::SUCCESS;
    }
}
