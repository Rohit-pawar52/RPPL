<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Services\Announcement\AnnouncementNotificationService;
use Illuminate\Console\Command;

/**
 * Registered in bootstrap/app.php's ->withSchedule() to run every
 * minute. Scans for due announcements and hands each one to
 * AnnouncementNotificationService::dispatchIfDue(), which does the
 * actual atomic claim — this command stays a thin loop, never Firebase-
 * calling or DB-locking logic itself.
 */
class DispatchScheduledAnnouncements extends Command
{
    protected $signature = 'rppl:dispatch-scheduled-announcements';

    protected $description = 'Dispatch push notifications for announcements whose scheduled send time is due';

    public function handle(AnnouncementNotificationService $service): int
    {
        $ids = Announcement::query()->dueForNotification()->pluck('id');

        $dispatched = 0;

        foreach ($ids as $id) {
            if ($service->dispatchIfDue($id)) {
                $dispatched++;
            }
        }

        $this->info("Scanned {$ids->count()} due announcement(s), dispatched {$dispatched}.");

        return self::SUCCESS;
    }
}
