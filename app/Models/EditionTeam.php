<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EditionTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'edition_id',
        'team_id',
    ];

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function teamPlayers(): HasMany
    {
        return $this->hasMany(TeamPlayer::class);
    }

    /**
     * Explicit, named inverse relationships to GameMatch/Innings — used
     * only for the EditionTeamService deletion-eligibility check, not
     * for general querying. A bare "matches()"/"innings()" name would
     * be ambiguous since matches/innings each reference edition_teams
     * through more than one column.
     */
    public function matchesAsTeamA(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'edition_team_a_id');
    }

    public function matchesAsTeamB(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'edition_team_b_id');
    }

    public function inningsAsBattingTeam(): HasMany
    {
        return $this->hasMany(Innings::class, 'batting_team_id');
    }

    public function inningsAsBowlingTeam(): HasMany
    {
        return $this->hasMany(Innings::class, 'bowling_team_id');
    }
}
