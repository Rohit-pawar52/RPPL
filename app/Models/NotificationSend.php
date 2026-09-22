<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable record of a single Send/Resend click — captures the
 * parent Notification's title/message/action_url exactly as they were
 * AT THAT MOMENT, so a later edit to the Notification can never rewrite
 * what an earlier send actually contained (see the Phase B1 audit
 * report). Mutation guards/service logic belong to the future sending
 * phase, not to this model — this phase only establishes the schema.
 */
class NotificationSend extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_id',
        'title_snapshot',
        'message_snapshot',
        'action_url_snapshot',
        'attempted_count',
        'success_count',
        'failure_count',
        'sent_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
