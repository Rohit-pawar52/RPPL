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

    /**
     * Whether $url is acceptable as this notification's action_url — an
     * internal RPPL path only, never an external URL of any kind (Phase
     * B3). Shared by StoreNotificationRequest/UpdateNotificationRequest
     * so the exact same rule can never quietly drift between the two.
     *
     * null/empty is valid here (presence is the 'nullable' validation
     * rule's job, not this helper's) — everything else must begin with
     * EXACTLY one "/": that alone already rejects any absolute URL
     * (http://, https://, javascript:, data:, ... — none of which start
     * with "/"); "//host" (protocol-relative) is rejected explicitly
     * since it does start with "/". Any control character or whitespace
     * anywhere in the value is rejected outright, defense in depth
     * against encoding tricks. "/" itself is valid.
     */
    public static function isValidActionUrl(?string $url): bool
    {
        if ($url === null || $url === '') {
            return true;
        }

        if (preg_match('/[\x00-\x1F\x7F\s]/', $url) === 1) {
            return false;
        }

        return str_starts_with($url, '/') && ! str_starts_with($url, '//');
    }
}
