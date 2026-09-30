<?php

namespace App\Console\Commands;

use App\Models\Edition;
use App\Services\Registration\RegistrationClosingReminderService;
use Illuminate\Console\Command;

/**
 * Registered in bootstrap/app.php's ->withSchedule() to run every
 * minute. Edition::scopeDueForRegistrationReminder() returns structural
 * candidates; the service does the per-row due check and atomic claim.
 */
class DispatchRegistrationClosingReminders extends Command
{
    protected $signature = 'rppl:dispatch-registration-closing-reminders';

    protected $description = 'Dispatch push notifications for editions whose registration closing reminder is due';

    public function handle(RegistrationClosingReminderService $service): int
    {
        $ids = Edition::query()->dueForRegistrationReminder()->pluck('id');

        $dispatched = 0;

        foreach ($ids as $id) {
            if ($service->dispatchIfDue($id)) {
                $dispatched++;
            }
        }

        $this->info("Scanned {$ids->count()} candidate edition(s), dispatched {$dispatched} registration closing reminder(s).");

        return self::SUCCESS;
    }
}
