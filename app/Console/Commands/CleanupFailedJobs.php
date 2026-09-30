<?php

namespace App\Console\Commands;

use App\Services\DataCleanup\FailedJobCleanupService;
use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Optional daily pruning of old failed_jobs rows, registered in
 * bootstrap/app.php's ->withSchedule(). Does nothing unless an admin has
 * enabled it in Settings -> System, including when run by hand — there is
 * deliberately no --force. Only ever touches failed_jobs, via the same
 * FailedJobCleanupService the manual Data Cleanup action uses.
 *
 * Not written to data_cleanup_logs: that table requires an acting admin
 * user, which a scheduled run doesn't have. A line in the application log
 * records each run that actually deleted something.
 */
class CleanupFailedJobs extends Command
{
    /**
     * Guard against a corrupted stored value, mirroring the form's own
     * validation range — an out-of-range retention never deletes anything.
     */
    private const MIN_RETENTION_DAYS = 7;

    private const MAX_RETENTION_DAYS = 365;

    protected $signature = 'rppl:cleanup-failed-jobs';

    protected $description = 'Delete failed jobs older than the configured retention period (only when enabled in Settings)';

    public function handle(SettingsService $settings, FailedJobCleanupService $failedJobs): int
    {
        if (! $settings->boolean('system.failed_jobs_auto_cleanup_enabled')) {
            $this->info('Automatic failed job cleanup is disabled. Nothing deleted.');

            return self::SUCCESS;
        }

        $retentionDays = $settings->integer('system.failed_jobs_retention_days');

        if ($retentionDays === null || $retentionDays < self::MIN_RETENTION_DAYS || $retentionDays > self::MAX_RETENTION_DAYS) {
            $this->error('Failed job retention setting is out of range. Nothing deleted.');

            return self::FAILURE;
        }

        // Elapsed-time retention: only rows that failed strictly before
        // this instant are removed (failed_at < cutoff).
        $cutoff = now()->subDays($retentionDays);
        $deleted = $failedJobs->deleteFailedBefore($cutoff);

        if ($deleted > 0) {
            Log::info('Automatic failed job cleanup', [
                'retention_days' => $retentionDays,
                'cutoff' => $cutoff->toIso8601String(),
                'deleted' => $deleted,
            ]);
        }

        $this->info("Deleted {$deleted} failed job(s) older than {$retentionDays} days.");

        return self::SUCCESS;
    }
}
