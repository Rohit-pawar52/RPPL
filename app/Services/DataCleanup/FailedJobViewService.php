<?php

namespace App\Services\DataCleanup;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Read-only visibility into the failed_jobs table for the Data Cleanup
 * → System tab (inspection/debugging only — no retry, no delete; the
 * existing delete-by-date action stays in FailedJobCleanupService).
 *
 * Every row is transformed here into a small, safe shape so the Blade
 * views stay dumb and NEVER see the raw `payload` column. The payload's
 * `data.command` is a PHP-serialized object that may embed Eloquent
 * model data (e.g. a PlayerRegistration's Aadhaar/payment-proof paths,
 * an FcmToken string) — it is never unserialize()d (object-injection
 * risk) and never surfaced. The job name comes only from json_decode()
 * of the outer payload envelope and its `displayName` key, which is the
 * same name Laravel itself computes and `php artisan queue:failed`
 * shows. For a queued broadcast, Laravel sets displayName to the
 * wrapped event's class (BroadcastEvent::displayName()) and
 * data.commandName to Illuminate\Broadcasting\BroadcastEvent — both
 * plain JSON strings, so the event name is derivable safely.
 */
class FailedJobViewService
{
    public const UNKNOWN_JOB = 'Unknown Job';

    private const BROADCAST_EVENT_CLASS = 'Illuminate\\Broadcasting\\BroadcastEvent';

    private const ERROR_SUMMARY_LIMIT = 150;

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        $paginator = DB::table('failed_jobs')
            ->select(['id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
            ->orderByDesc('failed_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (object $row) => $this->summarize($row))
        );

        return $paginator;
    }

    /**
     * One row for the detail page, or null for an unknown/already-
     * cleaned-up UUID. Includes the full exception text but still
     * never the raw payload.
     */
    public function find(string $uuid): ?object
    {
        $row = DB::table('failed_jobs')->where('uuid', $uuid)->first();

        if ($row === null) {
            return null;
        }

        $summary = $this->summarize($row);
        $summary->exception = (string) $row->exception;

        return $summary;
    }

    /**
     * Human-readable job label from the payload envelope only.
     * Never throws: any malformed/missing payload falls back to
     * self::UNKNOWN_JOB.
     */
    public function jobName(?string $payload): string
    {
        try {
            if ($payload === null || $payload === '') {
                return self::UNKNOWN_JOB;
            }

            $decoded = json_decode($payload, true);

            if (! is_array($decoded)) {
                return self::UNKNOWN_JOB;
            }

            $displayName = $decoded['displayName'] ?? null;

            if (! is_string($displayName) || trim($displayName) === '') {
                return self::UNKNOWN_JOB;
            }

            $name = class_basename(trim($displayName));

            $commandName = $decoded['data']['commandName'] ?? null;

            if ($commandName === self::BROADCAST_EVENT_CLASS && $displayName !== self::BROADCAST_EVENT_CLASS) {
                return $name.' (broadcast)';
            }

            return $name;
        } catch (Throwable) {
            return self::UNKNOWN_JOB;
        }
    }

    /**
     * First line of the stored exception (normally "Class: message"),
     * truncated — the list view never shows the full stack trace.
     */
    public function errorSummary(?string $exception): string
    {
        $firstLine = trim(Str::before((string) $exception, "\n"));

        return $firstLine === '' ? '—' : Str::limit($firstLine, self::ERROR_SUMMARY_LIMIT);
    }

    private function summarize(object $row): object
    {
        return (object) [
            'id' => $row->id,
            'uuid' => (string) $row->uuid,
            'connection' => (string) $row->connection,
            'queue' => (string) $row->queue,
            'failed_at' => $row->failed_at ? Carbon::parse($row->failed_at) : null,
            'job_name' => $this->jobName($row->payload),
            'error_summary' => $this->errorSummary($row->exception),
        ];
    }
}
