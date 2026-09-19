<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An admin-authored broadcast notification's editable CONTENT — not one
 * immutable send event. Deliberately has no status column: whether it
 * has ever been sent, and when it was last sent, are both derived from
 * sends() rather than cached here, so they can never drift out of sync
 * with the actual send history (see NotificationSend's docblock and the
 * Phase B1 audit report). Admin CRUD and sending are a later phase —
 * this phase only establishes the schema/models.
 */
class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'action_url',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sends(): HasMany
    {
        return $this->hasMany(NotificationSend::class);
    }

    /**
     * The most recent send of this notification, or null if it has never
     * been sent — the one derived fact worth a convenience accessor,
     * rather than repeating sends()->latest()->first() at every future
     * call site.
     */
    public function latestSend(): ?NotificationSend
    {
        return $this->sends()->latest('id')->first();
    }
}
