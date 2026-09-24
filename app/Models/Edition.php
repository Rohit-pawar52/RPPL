<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Edition extends Model
{
    use HasFactory;

    /**
     * Must match the enum values in the editions migration exactly.
     */
    public const STATUSES = ['upcoming', 'active', 'completed'];

    protected $fillable = [
        'name',
        'year',
        'status',
        'registration_open',
        'registration_fee',
    ];

    protected function casts(): array
    {
        return [
            'registration_open' => 'boolean',
            'registration_fee' => 'decimal:2',
        ];
    }

    public function playerRegistrations(): HasMany
    {
        return $this->hasMany(PlayerRegistration::class);
    }

    public function editionTeams(): HasMany
    {
        return $this->hasMany(EditionTeam::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(EditionTransaction::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(EditionContribution::class);
    }

    /**
     * Phase 3.48 — this edition's committee membership rows (see
     * EditionCommitteeMember). Not a belongsToMany to Contributor
     * directly: callers that need the Contributor rows themselves go
     * through ->with('committeeMemberships.contributor') so the pivot
     * row (and its own id/timestamps) stays a first-class, addressable
     * record rather than disappearing into an implicit pivot table.
     */
    public function committeeMemberships(): HasMany
    {
        return $this->hasMany(EditionCommitteeMember::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
    }

    /**
     * Editions still open to new participation of any kind (player
     * registrations, team participation, ...). Per the current RPPL
     * workflow, only a "completed" edition is excluded — both
     * "upcoming" and "active" editions remain open (see the Phase 3.4
     * report for this decision).
     *
     * Renamed from scopeAcceptingRegistrations() in Phase 3.7: the old
     * name was registration-specific and would have been misleading
     * once EditionTeam participation needed the exact same predicate.
     *
     * Deliberately unrelated to registration_open (Phase 3.39B): this
     * scope keeps governing broader admin-side eligibility (admin-
     * created registrations, team participation); the future public
     * guest registration form will require BOTH this AND
     * registration_open = true — a narrower, separate gate the admin
     * controls independently of status.
     */
    public function scopeOpenForParticipation(Builder $query): Builder
    {
        return $query->where('status', '!=', 'completed');
    }

    /**
     * The narrower, separate gate the public/guest registration form
     * (Phase 3.39C) requires — deliberately stricter than
     * openForParticipation(): the admin must explicitly flip
     * registration_open on AND configure a registration_fee. An unset
     * fee is treated as "not actually ready to accept public
     * submissions yet" (see Phase 3.39C's design note on the fee-null
     * case) — guest registration always uploads payment proof against
     * an authoritative fee, so an undefined fee makes acceptance
     * ambiguous. Admin-created/manual/imported registrations are
     * entirely unaffected — they keep using openForParticipation().
     */
    public function scopeAcceptingPublicRegistration(Builder $query): Builder
    {
        return $query->openForParticipation()
            ->where('registration_open', true)
            ->whereNotNull('registration_fee');
    }

    /**
     * Instance-level equivalent of scopeAcceptingPublicRegistration(),
     * for re-checking an already-loaded (and, during guest submission,
     * already row-locked) Edition without an extra query.
     */
    public function isAcceptingPublicRegistration(): bool
    {
        return $this->status !== 'completed'
            && $this->registration_open
            && $this->registration_fee !== null;
    }
}
