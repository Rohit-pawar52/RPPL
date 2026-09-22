<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public notice-ticker message (Phase 3.45) — plain text only, never
 * HTML (see the public ticker partial, which always uses {{ }}, never
 * {!! !!}). Visibility is entirely derived from is_active/starts_at/
 * ends_at at query time (scopeActive()); there is no separate status
 * column and no scheduler/cron job flips one — an announcement simply
 * starts/stops matching the query as real time passes.
 */
class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'message',
        'starts_at',
        'ends_at',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Currently displayable on the public ticker: enabled, and — for
     * each of starts_at/ends_at independently — either that column is
     * null (no boundary on that side) or the current moment already
     * falls inside it. now() is compared in the app's stored (UTC)
     * timezone, never system.display_timezone, which is a display
     * preference only (see DisplayTimezoneFormatter).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    /**
     * The admin index's derived display status — never persisted,
     * always computed fresh from is_active/starts_at/ends_at so it can
     * never drift out of sync with scopeActive()'s own rule.
     *
     * @return 'disabled'|'scheduled'|'expired'|'active'
     */
    public function computedStatus(): string
    {
        if (! $this->is_active) {
            return 'disabled';
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return 'scheduled';
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return 'expired';
        }

        return 'active';
    }
}
