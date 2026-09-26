<?php

namespace App\Services\DataCleanup;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3.49 — "System" tab cleanup for the failed_jobs table (a real,
 * growing table on this app's database queue connection — OCR and
 * notification-send jobs both genuinely fail sometimes). Deliberately
 * the ONLY System cleanup implemented this phase: job_batches is never
 * populated (this codebase never uses Bus::batch()), and log-file
 * cleanup was judged unsafe to build against the configured `single`
 * log channel (see the Phase 3.49 report) — both were rejected rather
 * than built speculatively.
 *
 * Uses the query builder directly (DB::table), not an Eloquent model:
 * failed_jobs is Laravel's own queue-internal table, not an
 * application domain concept, matching how `php artisan queue:failed`
 * itself reads it.
 */
class FailedJobCleanupService
{
    public function countFailedBefore(Carbon $before): int
    {
        return DB::table('failed_jobs')->where('failed_at', '<', $before)->count();
    }

    public function deleteFailedBefore(Carbon $before): int
    {
        return DB::table('failed_jobs')->where('failed_at', '<', $before)->delete();
    }
}
