<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A general RPPL contributor/"Chanda" identity — not a committee member,
 * not a User, no login. Deliberately global (not edition-scoped), same
 * reasoning as CommitteeMember: this is a reusable person identity;
 * their actual contribution payments will be edition-scoped rows
 * elsewhere (Phase 3.38B2+). committee_member_id is an explicit,
 * admin-set identity link only — it never means "this contributor is
 * automatically a committee contributor", and it never migrates or
 * implies any existing contribution history.
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

    public function committeeMember(): BelongsTo
    {
        return $this->belongsTo(CommitteeMember::class);
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
