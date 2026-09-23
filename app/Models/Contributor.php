<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * THE single person identity for anyone who contributes money to RPPL
 * (Phase 3.48) — a general "Chanda" contributor and a committee member
 * are the same kind of record; "committee member" is now an
 * edition-specific membership (see EditionCommitteeMember), never a
 * second identity. Deliberately global (not edition-scoped) — the same
 * person can be a committee member of one edition and not another,
 * or contribute without ever being on any committee.
 *
 * committee_member_id is LEGACY ONLY (Phase 3.38B1-era identity link,
 * pre-3.48) — it survives purely as a traceability breadcrumb back to
 * the old committee_members table for rows created by the Phase 3.48
 * data migration; no application code reads it for identity/authorization
 * purposes any more. Whether a Contributor is "on the committee" is
 * always answered by isCommitteeMemberOf($edition), never by this
 * column.
 */
class Contributor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'committee_member_id',
        'is_active',
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @deprecated Legacy identity link only — see class docblock. Kept
     * for the handful of historical rows the Phase 3.48 migration
     * populated; do not use this to decide committee status.
     */
    public function committeeMember(): BelongsTo
    {
        return $this->belongsTo(CommitteeMember::class);
    }

    public function committeeMemberships(): HasMany
    {
        return $this->hasMany(EditionCommitteeMember::class);
    }

    /**
     * The only correct way to ask "is this person a committee member of
     * this edition?" (Phase 3.48) — never contributor.committee_member_id,
     * which is legacy identity-link data, not membership.
     */
    public function isCommitteeMemberOf(Edition $edition): bool
    {
        return $this->relationLoaded('committeeMemberships')
            ? $this->committeeMemberships->contains('edition_id', $edition->id)
            : $this->committeeMemberships()->where('edition_id', $edition->id)->exists();
    }

    /**
     * This contributor's own contribution history (Phase 3.38B2) — rows
     * where THIS contributor was the actual identity used at the time
     * of recording, regardless of any committee_member_id link. Linking
     * to a CommitteeMember never moves/merges history between the two.
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(EditionContribution::class);
    }

    /**
     * Local scope (NOT a global scope) — a deactivated contributor's
     * historical contributions must remain fully visible; only new
     * contribution eligibility is restricted to active contributors
     * (that selection rule itself belongs to a later phase).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
