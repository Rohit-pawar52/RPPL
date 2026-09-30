<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Represents a row in the `matches` table.
 *
 * Named GameMatch (not Match) because `match` is a reserved keyword in
 * PHP 8+ and cannot be used as a class name.
 *
 * Same-edition/team-eligibility consistency (team_a/team_b must belong
 * to this match's edition_id, team_a != team_b) is enforced in Phase 3.9
 * by StoreGameMatchRequest/UpdateGameMatchRequest, not here — see their
 * docblocks and the Phase 3.9 report.
 */
class GameMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    /**
     * Must match the enum values in the matches migration exactly.
     */
    public const STATUSES = ['scheduled', 'toss', 'live', 'completed', 'abandoned', 'cancelled'];

    public const STAGES = ['league', 'quarter_final', 'semi_final', 'final'];

    public const TOSS_DECISIONS = ['bat', 'bowl'];

    protected $fillable = [
        'edition_id',
        'match_number',
        'edition_team_a_id',
        'edition_team_b_id',
        'venue_id',
        'match_stage',
        'overs_per_innings',
        'scheduled_at',
        'started_at',
        'completed_at',
        'match_status',
        'toss_winner_team_id',
        'toss_decision',
        'match_result',
        'winner_team_id',
        'result_type',
        'result_source',
        'result_note',
        'win_margin_type',
        'win_margin',
        'reminder_enabled',
        'reminder_minutes_before',
        'reminder_dispatched_at',
        'notification_id',
        'result_notification_dispatched_at',
        'result_notification_id',
    ];

    /**
     * Match statuses a reminder may still fire for — "upcoming", not yet
     * started. Deliberately excludes 'live' (the match has already
     * started; "starts soon" would be stale) as well as the terminal
     * statuses (see scopeDueForReminder()).
     */
    private const REMINDER_ELIGIBLE_STATUSES = ['scheduled', 'toss'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_enabled' => 'boolean',
            'reminder_minutes_before' => 'integer',
            'reminder_dispatched_at' => 'datetime',
            'result_notification_dispatched_at' => 'datetime',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /**
     * The Notification created for this match's reminder push, once
     * dispatched — null before that.
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    /**
     * The Notification created for this match's RESULT push, once
     * dispatched — a distinct FK from notification()/reminder_dispatched_at
     * above (that pair is the pre-match reminder; this pair is the
     * post-finalization result). See the migration's own docblock.
     */
    public function resultNotification(): BelongsTo
    {
        return $this->belongsTo(Notification::class, 'result_notification_id');
    }

    /**
     * Eligible for MatchResultNotificationService::dispatchIfDue(): a
     * genuinely completed match (never cancelled/abandoned — both of
     * those set match_result too, e.g. "Match cancelled", but their
     * match_status is 'cancelled'/'abandoned', never 'completed', so
     * gating on match_status alone already excludes them with no
     * separate check needed) with a canonical result text, not yet
     * dispatched. Covers a normal win, a tie with no Super Over (the
     * canonical "Match tied" text is reused as-is — never a fabricated
     * winner), and a Super Over-decided result — all three reach
     * match_status 'completed' via MatchResultService.
     */
    public function scopeDueForResultNotification(Builder $query): Builder
    {
        return $query
            ->where('match_status', 'completed')
            ->whereNotNull('match_result')
            ->whereNull('result_notification_dispatched_at');
    }

    /**
     * The admin show page's result-notification status — never
     * persisted, always derived so it can't drift from match_status/
     * result_notification_dispatched_at and the linked Notification's
     * latest send. Mirrors Announcement::notificationStatusLabel()
     * exactly. 'dispatched' only ever means "the job was queued", never
     * "Firebase finished" — that's completed_at's job alone.
     *
     * @return 'not_applicable'|'pending'|'dispatched'|'completed'
     */
    public function resultNotificationStatusLabel(): string
    {
        if ($this->match_status !== 'completed' || $this->match_result === null) {
            return 'not_applicable';
        }

        if ($this->result_notification_dispatched_at === null) {
            return 'pending';
        }

        return $this->resultNotification?->latestSend()?->completed_at !== null ? 'completed' : 'dispatched';
    }

    /**
     * The structural half of reminder eligibility (portable across the
     * app's dev/SQLite-test and MySQL-production connections — never a
     * DB-specific date-arithmetic expression): enabled, not yet
     * dispatched, still an upcoming (not live/completed/abandoned/
     * cancelled) status, and the match itself hasn't started yet. This
     * intentionally does NOT check the due instant (scheduled_at minus
     * reminder_minutes_before) — that varies per row and is cheap to
     * finish checking in PHP once this narrow candidate set is loaded;
     * see MatchReminderService::isDueNow().
     */
    public function scopeDueForReminder(Builder $query): Builder
    {
        return $query
            ->where('reminder_enabled', true)
            ->whereNull('reminder_dispatched_at')
            ->whereIn('match_status', self::REMINDER_ELIGIBLE_STATUSES)
            ->where('scheduled_at', '>', now());
    }

    public function teamA(): BelongsTo
    {
        return $this->belongsTo(EditionTeam::class, 'edition_team_a_id');
    }

    public function teamB(): BelongsTo
    {
        return $this->belongsTo(EditionTeam::class, 'edition_team_b_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function tossWinner(): BelongsTo
    {
        return $this->belongsTo(EditionTeam::class, 'toss_winner_team_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(EditionTeam::class, 'winner_team_id');
    }

    public function matchPlayers(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id');
    }

    public function innings(): HasMany
    {
        return $this->hasMany(Innings::class, 'match_id');
    }

    /**
     * Explicit, named single-innings relations — used by InningsService
     * and the match show page, which both repeatedly need "the" first
     * or second innings rather than the whole innings() collection.
     */
    public function firstInnings(): HasOne
    {
        return $this->hasOne(Innings::class, 'match_id')->where('innings_number', 1);
    }

    public function secondInnings(): HasOne
    {
        return $this->hasOne(Innings::class, 'match_id')->where('innings_number', 2);
    }
}
