<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @deprecated Phase 3.48 — LEGACY identity table only, kept solely for
 * historical data preservation (no row is deleted, no column dropped).
 * "Committee member" is no longer a separate person identity: it is now
 * Contributor + an edition-specific EditionCommitteeMember row. No
 * admin UI creates/edits a CommitteeMember any more, and no application
 * code should query this model to determine anyone's current committee
 * status — use Contributor::isCommitteeMemberOf($edition) instead. See
 * the Phase 3.48 report for the full rationale and the data migration
 * that moved every existing CommitteeMember into a Contributor.
 */
class CommitteeMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(EditionContribution::class);
    }

    /**
     * Identity linkage only (Phase 3.38B1) — whether some Contributor
     * record has been explicitly, admin-linked to this same person. This
     * does NOT mean this committee member has any general-contributor
     * history, and does not affect committee contribution behavior.
     */
    public function contributor(): HasOne
    {
        return $this->hasOne(Contributor::class);
    }

    /**
     * Local scope (NOT a global scope) — a deactivated member's
     * historical contributions must remain fully visible; only new
     * contribution eligibility is restricted to active members.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
