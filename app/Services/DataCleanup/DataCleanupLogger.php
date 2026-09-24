<?php

namespace App\Services\DataCleanup;

use App\Models\DataCleanupLog;
use App\Models\User;

/**
 * Phase 3.49 — the one place every Data Cleanup tab/service records
 * what it did. Deliberately dumb (a single insert, no business logic):
 * each cleanup service decides ITS OWN criteria/counts and simply
 * reports them here after the destructive operation completes.
 *
 * Not run inside the same DB transaction as the deletion itself when
 * that deletion also involves filesystem work (e.g. registration
 * documents) — a filesystem delete can never be part of a DB
 * transaction's atomicity guarantee anyway, so callers log AFTER the
 * real work is confirmed done, accepting that a crash between "files
 * deleted" and "log written" would lose only the audit trail entry,
 * never data. See each cleanup service's own docblock for specifics.
 */
class DataCleanupLogger
{
    /**
     * @param  array<string, mixed>  $criteria  safe, structured metadata only — never document contents/filenames/PII
     */
    public function log(
        User $admin,
        string $category,
        string $action,
        array $criteria,
        int $recordsAffected,
        ?int $filesDeleted = null,
    ): DataCleanupLog {
        return DataCleanupLog::create([
            'admin_user_id' => $admin->id,
            'category' => $category,
            'action' => $action,
            'criteria' => $criteria,
            'records_affected' => $recordsAffected,
            'files_deleted' => $filesDeleted,
        ]);
    }
}
