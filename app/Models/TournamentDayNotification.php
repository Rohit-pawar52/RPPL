<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Tournament-Day Morning Reminder that was actually queued for an
 * edition on a given display-timezone calendar date. Its existence is the
 * idempotency key (UNIQUE edition_id + notification_date) — only ever
 * written by TournamentDayReminderService.
 */
class TournamentDayNotification extends Model
{
    protected $fillable = [
        'edition_id',
        'notification_date',
        'notification_id',
        'dispatched_at',
    ];

    /**
     * notification_date is deliberately NOT cast to a date: it is always
     * written and compared as a plain 'Y-m-d' string, so SQLite (tests)
     * and MySQL store/compare the identical value — a `date` cast would
     * persist 'Y-m-d 00:00:00' on SQLite and break equality lookups.
     */
    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
