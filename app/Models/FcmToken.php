<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One registered FCM (Firebase Cloud Messaging) device/browser token.
 * V1 is guest-only: user_id and player_id are always null here — both
 * nullable rather than a polymorphic owner column, mirroring
 * EditionContribution's committee_member_id/contributor_id shape (see
 * the Phase B1 audit report). App\Services\Notification\
 * FcmTokenSubscriptionService is the only writer; it never lets a
 * request set user_id/player_id/is_active/last_seen_at directly.
 *
 * token must never be logged, exposed in a public response, or included
 * in any export/report.
 */
class FcmToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'user_id',
        'player_id',
        'is_active',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * The one query shape every future sending feature needs — kept here
     * rather than repeated inline at each call site.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
