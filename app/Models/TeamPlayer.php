<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Same-edition consistency (player_registrations.edition_id must equal
 * the parent edition_team's edition_id) is enforced in Phase 3.8 by
 * StoreTeamPlayerRequest + TeamPlayerService, not here — see their
 * docblocks and the Phase 3.8 report.
 */
class TeamPlayer extends Model
{
    use HasFactory;

    /**
     * Must match the enum values in the team_players migration exactly.
     * Deliberately independent from Player::PRIMARY_ROLES — this is the
     * squad/tournament-specific role, not the player's general profile,
     * even though the two enums currently share the same values.
     */
    public const ROLES = ['batter', 'bowler', 'all_rounder', 'wicket_keeper'];

    protected $fillable = [
        'edition_team_id',
        'player_registration_id',
        'jersey_number',
        'role',
    ];

    public function editionTeam(): BelongsTo
    {
        return $this->belongsTo(EditionTeam::class);
    }

    public function playerRegistration(): BelongsTo
    {
        return $this->belongsTo(PlayerRegistration::class);
    }

    public function matchPlayers(): HasMany
    {
        return $this->hasMany(MatchPlayer::class);
    }
}
