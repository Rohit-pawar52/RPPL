<?php

namespace App\Services\MatchPlayer;

use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\TeamPlayer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchPlayerService
{
    /**
     * Whether the Playing XI for this match may currently be added to,
     * removed from, or have captain/wicket-keeper reassigned.
     *
     * Editable while the match is still 'scheduled' or 'toss'. Locked
     * once 'live'/'completed'/'abandoned'/'cancelled' — but status alone
     * is not trusted: if scoring history already exists (an Innings has
     * been recorded) despite a stale/malformed 'scheduled' or 'toss'
     * status, history wins and modification stays locked.
     */
    public function canModifyPlayingXI(GameMatch $match): bool
    {
        if (! in_array($match->match_status, ['scheduled', 'toss'], true)) {
            return false;
        }

        return ! $match->innings()->exists();
    }

    /**
     * StoreMatchPlayerRequest already validates that the team_player
     * belongs to one of this match's two participating edition_teams,
     * that the underlying player is active, and that the team_player
     * isn't already selected for this match. This defensively re-
     * verifies the same-match consistency rule directly against fresh
     * data before writing — the core rule of this phase, the one this
     * project was explicitly asked not to rely on UI filtering alone
     * for — as cheap insurance against a future caller skipping the
     * FormRequest.
     */
    public function addPlayer(GameMatch $match, TeamPlayer $teamPlayer): MatchPlayer
    {
        $participatingTeamIds = [$match->edition_team_a_id, $match->edition_team_b_id];

        if (! in_array($teamPlayer->edition_team_id, $participatingTeamIds, true)) {
            throw ValidationException::withMessages([
                'team_player_id' => 'The selected player does not belong to either team in this match.',
            ]);
        }

        try {
            return MatchPlayer::create([
                'match_id' => $match->id,
                'team_player_id' => $teamPlayer->id,
            ]);
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                throw ValidationException::withMessages([
                    'team_player_id' => 'This player is already selected for this match.',
                ]);
            }

            throw $e;
        }
    }

    /**
     * Removes a Playing XI selection only when no scoring history
     * references it. Returns false instead of letting the FK
     * (restrictOnDelete) constraint fail, so the controller can show a
     * friendly message.
     */
    public function removePlayer(MatchPlayer $matchPlayer): bool
    {
        if ($this->hasDeliveryHistory($matchPlayer)) {
            return false;
        }

        return (bool) $matchPlayer->delete();
    }

    /**
     * At most one captain per team per match. Assigning a new captain
     * unsets the previous captain for that same team only — the other
     * team's captain (if any) is untouched. Wrapped in a transaction
     * with a row lock on the sibling rows because this is a genuine
     * multi-row consistency operation: the "unset old captain" and "set
     * new captain" writes must succeed or fail together, and a
     * concurrent double-submit must not leave two captains set for the
     * same team.
     */
    public function setCaptain(MatchPlayer $matchPlayer): MatchPlayer
    {
        DB::transaction(function () use ($matchPlayer) {
            $this->teamMatchPlayersQuery($matchPlayer)
                ->where('is_captain', true)
                ->lockForUpdate()
                ->update(['is_captain' => false]);

            $matchPlayer->update(['is_captain' => true]);
        });

        return $matchPlayer->fresh();
    }

    /**
     * Same per-team-max-one pattern and transaction justification as
     * setCaptain(). A player may be both captain and wicket-keeper at
     * the same time — the two designations are independent and neither
     * is derived from TeamPlayer.role.
     */
    public function setWicketKeeper(MatchPlayer $matchPlayer): MatchPlayer
    {
        DB::transaction(function () use ($matchPlayer) {
            $this->teamMatchPlayersQuery($matchPlayer)
                ->where('is_wicket_keeper', true)
                ->lockForUpdate()
                ->update(['is_wicket_keeper' => false]);

            $matchPlayer->update(['is_wicket_keeper' => true]);
        });

        return $matchPlayer->fresh();
    }

    /**
     * All other MatchPlayer rows in the same match belonging to the
     * same edition_team as $matchPlayer — the scope within which
     * captain/wicket-keeper must be unique.
     */
    private function teamMatchPlayersQuery(MatchPlayer $matchPlayer)
    {
        return MatchPlayer::query()
            ->where('match_id', $matchPlayer->match_id)
            ->where('id', '!=', $matchPlayer->id)
            ->whereHas('teamPlayer', function ($query) use ($matchPlayer) {
                $query->where('edition_team_id', $matchPlayer->teamPlayer->edition_team_id);
            });
    }

    /**
     * Whether any Delivery references this MatchPlayer as striker,
     * non-striker, bowler, dismissed player, or fielder. Any one of
     * these means match scoring history exists and this selection must
     * never be removed.
     */
    public function hasDeliveryHistory(MatchPlayer $matchPlayer): bool
    {
        return $matchPlayer->deliveriesAsStriker()->exists()
            || $matchPlayer->deliveriesAsNonStriker()->exists()
            || $matchPlayer->deliveriesAsBowler()->exists()
            || $matchPlayer->deliveriesAsDismissedPlayer()->exists()
            || $matchPlayer->deliveriesAsFielder()->exists();
    }
}
