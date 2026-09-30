<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'registration_opens_at',
        'registration_closes_at',
        'registration_reminder_enabled',
        'registration_reminder_minutes_before',
        'registration_reminder_dispatched_at',
        'registration_reminder_notification_id',
    ];

    public const REGISTRATION_STATE_OPEN = 'open';

    public const REGISTRATION_STATE_NOT_YET_OPEN = 'not_yet_open';

    public const REGISTRATION_STATE_CLOSED = 'closed';

    protected function casts(): array
    {
        return [
            'registration_open' => 'boolean',
            'registration_fee' => 'decimal:2',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'registration_reminder_enabled' => 'boolean',
            'registration_reminder_minutes_before' => 'integer',
            'registration_reminder_dispatched_at' => 'datetime',
        ];
    }

    public function registrationReminderNotification(): BelongsTo
    {
        return $this->belongsTo(Notification::class, 'registration_reminder_notification_id');
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
        $now = now();

        return $query->publicRegistrationEnabled()
            ->where(fn (Builder $q) => $q->whereNull('registration_opens_at')->orWhere('registration_opens_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('registration_closes_at')->orWhere('registration_closes_at', '>', $now));
    }

    /**
     * The admin-controlled switch alone (status/registration_open/fee),
     * ignoring the optional opens_at/closes_at window — used to find the
     * edition whose window hasn't started yet, so the public page can
     * say "not opened yet" rather than "closed".
     */
    public function scopePublicRegistrationEnabled(Builder $query): Builder
    {
        return $query->openForParticipation()
            ->where('registration_open', true)
            ->whereNotNull('registration_fee');
    }

    /**
     * The single source of truth for public registration availability:
     * the existing switch must be on, and when a window is configured,
     * now must fall inside it (opens_at inclusive, closes_at exclusive).
     * Null dates impose no limit, so pre-window editions are unaffected.
     */
    public function publicRegistrationState(): string
    {
        if ($this->status === 'completed' || ! $this->registration_open || $this->registration_fee === null) {
            return self::REGISTRATION_STATE_CLOSED;
        }

        if ($this->registration_closes_at !== null && ! $this->registration_closes_at->isFuture()) {
            return self::REGISTRATION_STATE_CLOSED;
        }

        if ($this->registration_opens_at !== null && $this->registration_opens_at->isFuture()) {
            return self::REGISTRATION_STATE_NOT_YET_OPEN;
        }

        return self::REGISTRATION_STATE_OPEN;
    }

    /**
     * Instance-level equivalent of scopeAcceptingPublicRegistration(),
     * for re-checking an already-loaded (and, during guest submission,
     * already row-locked) Edition without an extra query.
     */
    public function isAcceptingPublicRegistration(): bool
    {
        return $this->publicRegistrationState() === self::REGISTRATION_STATE_OPEN;
    }

    /**
     * Structural candidates only; the derived due instant
     * (closes_at - minutes_before) is checked per row in
     * RegistrationClosingReminderService, like GameMatch::scopeDueForReminder().
     */
    public function scopeDueForRegistrationReminder(Builder $query): Builder
    {
        return $query->acceptingPublicRegistration()
            ->where('registration_reminder_enabled', true)
            ->whereNotNull('registration_reminder_minutes_before')
            ->whereNotNull('registration_closes_at')
            ->whereNull('registration_reminder_dispatched_at');
    }

    /**
     * The single "current" edition for public-facing pages that need
     * exactly one (the homepage, the header's Points Table link): the
     * active edition if one exists, otherwise the soonest upcoming
     * edition, otherwise the most recently completed one, otherwise
     * null (a brand-new install with zero editions is a real, valid
     * state — never assumed away). Originally HomeController-only
     * logic, promoted here so PublicNavComposer can reuse the exact
     * same selection without a second, potentially-drifting copy.
     */
    public static function current(): ?self
    {
        return self::where('status', 'active')->latest('year')->first()
            ?? self::where('status', 'upcoming')->orderBy('year')->first()
            ?? self::where('status', 'completed')->latest('year')->first();
    }
}
