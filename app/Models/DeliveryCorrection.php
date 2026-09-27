<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only audit record for one quick correction of a Delivery
 * (frozen S02 rules 43/44/46 — see DeliveryService::correctDelivery()).
 * Never updated after creation: old_values/new_values are full fact
 * snapshots (the same columns the Delivery row itself carries), not a
 * diff, so "what did this used to say" is always reconstructible without
 * needing to replay other rows.
 */
class DeliveryCorrection extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'delivery_id',
        'innings_id',
        'match_id',
        'old_values',
        'new_values',
        'reason',
        'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function innings(): BelongsTo
    {
        return $this->belongsTo(Innings::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
