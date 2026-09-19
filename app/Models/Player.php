<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Player extends Model
{
    use HasFactory;

    /**
     * Must match the enum values in the players migration exactly.
     */
    public const BATTING_STYLES = ['right_hand', 'left_hand'];

    public const BOWLING_STYLES = ['right_arm', 'left_arm', 'none'];

    public const PRIMARY_ROLES = ['batter', 'bowler', 'all_rounder', 'wicket_keeper'];

    /**
     * Human-readable labels for PRIMARY_ROLES, kept as one shared map so
     * admin review screens never drift from each other on capitalization
     * (a plain str_replace('_',' ',...) can't produce "All-rounder"'s
     * hyphen or "Wicket Keeper"'s second capital).
     */
    public const PRIMARY_ROLE_LABELS = [
        'batter' => 'Batter',
        'bowler' => 'Bowler',
        'all_rounder' => 'All-rounder',
        'wicket_keeper' => 'Wicket Keeper',
    ];

    protected $fillable = [
        'user_id',
        'is_active',
        'name',
        'phone',
        'email',
        'date_of_birth',
        'photo_path',
        'batting_style',
        'bowling_style',
        'primary_role',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function playerRegistrations(): HasMany
    {
        return $this->hasMany(PlayerRegistration::class);
    }

    /**
     * V1 never sets fcm_tokens.player_id (every subscription is
     * anonymous — see FcmTokenSubscriptionService) — this relation
     * exists now purely so a future identified-player association needs
     * no schema change, per the Phase B1 audit report.
     */
    public function fcmTokens(): HasMany
    {
        return $this->hasMany(FcmToken::class);
    }

    /**
     * The single most recent registration (by registered_at), used to
     * cheaply derive a "current/recent team" per player in listings
     * without loading every registration for every row.
     */
    public function latestRegistration(): HasOne
    {
        return $this->hasOne(PlayerRegistration::class)->latestOfMany('registered_at');
    }

    /**
     * Local scope (NOT a global scope) so historical/reporting queries
     * can still retrieve inactive players deliberately. Intended for
     * future selection dropdowns (e.g. PlayerRegistration), not for the
     * admin Player list, which must keep showing both states by default.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The one shared phone-normalization mechanism (Phase 3.39C) — used
     * by public/guest registration identity resolution so equivalent
     * input variants (spaces, hyphens, a leading +91/91 country code)
     * consistently resolve to the same stored 10-digit form rather than
     * accidentally creating a duplicate Player. Deliberately narrow and
     * conservative: only a 12-digit string that begins with the Indian
     * country code "91" is stripped down to its bare 10 digits — a
     * genuine 10-digit number is never touched, even one that happens
     * to start with "91" itself. Existing stored phone values created
     * before this rule existed are never retroactively rewritten.
     */
    public static function normalizePhone(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $digits = ltrim(preg_replace('/[^\d+]/', '', $raw), '+');

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    /**
     * Trims and lowercases a submitted email for consistent identity
     * matching/storage — never used to guess/merge across a materially
     * different address, only to make trivial case/whitespace variants
     * of the SAME address match.
     */
    public static function normalizeEmail(?string $raw): ?string
    {
        $trimmed = trim((string) $raw);

        return $trimmed === '' ? null : Str::lower($trimmed);
    }
}
