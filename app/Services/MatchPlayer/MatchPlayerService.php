<?php

namespace App\Services\MatchPlayer;

use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\TeamPlayer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
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
     * FormRequest. Also caps this team's selection at 11 (frozen S02
     * rule 1: the Playing XI is always exactly 11, never configurable)
     * — MatchFlowService/InningsService separately refuse to start a
     * toss/match/innings with fewer than 11, so together the two ends
     * of the range are enforced.
     */
    public function addPlayer(GameMatch $match, TeamPlayer $teamPlayer): MatchPlayer
    {
        $participatingTeamIds = [$match->edition_team_a_id, $match->edition_team_b_id];

        if (! in_array($teamPlayer->edition_team_id, $participatingTeamIds, true)) {
            throw ValidationException::withMessages([
                'team_player_id' => __('The selected player does not belong to either team in this match.'),
            ]);
        }

        $alreadySelected = MatchPlayer::query()
            ->where('match_id', $match->id)
            ->whereHas('teamPlayer', fn ($query) => $query->where('edition_team_id', $teamPlayer->edition_team_id))
            ->count();

        if ($alreadySelected >= 11) {
            throw ValidationException::withMessages([
                'team_player_id' => __('This team already has 11 players selected — the Playing XI cannot exceed 11.'),
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
                    'team_player_id' => __('This player is already selected for this match.'),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Bulk Playing XI selection: replaces one team's ENTIRE Playing XI
     * for this match with exactly the given set of team_player_ids, in
     * a single atomic operation — the fast match-day alternative to
     * addPlayer() called 11 times. A player already selected who is
     * still in $teamPlayerIds keeps their existing MatchPlayer row (and
     * therefore their captain/wicket-keeper designation) untouched;
     * only genuinely added/removed players cause a write. This is what
     * makes re-saving an unchanged selection a safe no-op rather than
     * silently wiping out who was captain.
     *
     * Structural validation (team membership, exact count, duplicates,
     * squad membership) happens here as cheap insurance against a
     * future caller skipping SyncMatchPlayersRequest — the same
     * defense-in-depth pattern addPlayer() already uses. The active-
     * player check is deliberately NOT repeated here (SyncMatchPlayers
     * Request's job only, matching addPlayer()'s existing division of
     * responsibility) so an already-selected player who later became
     * inactive can still be re-submitted as part of an unchanged
     * selection without this method rejecting its own prior state.
     *
     * Callers must check canModifyPlayingXI() first — this method does
     * not re-check it, matching every other write in this service.
     *
     * @param  list<int>  $teamPlayerIds
     * @return Collection<int, MatchPlayer>
     */
    public function syncPlayingXi(GameMatch $match, EditionTeam $editionTeam, array $teamPlayerIds): Collection
    {
        $participatingTeamIds = [$match->edition_team_a_id, $match->edition_team_b_id];

        if (! in_array($editionTeam->id, $participatingTeamIds, true)) {
            throw ValidationException::withMessages([
                'edition_team_id' => __('The selected team does not belong to this match.'),
            ]);
        }

        $uniqueIds = array_values(array_unique($teamPlayerIds));

        if (count($uniqueIds) !== count($teamPlayerIds)) {
            throw ValidationException::withMessages([
                'team_player_ids' => __('The same player cannot be selected twice.'),
            ]);
        }

        if (count($uniqueIds) !== 11) {
            throw ValidationException::withMessages([
                'team_player_ids' => __('Exactly 11 players must be selected.'),
            ]);
        }

        $squadCount = TeamPlayer::where('edition_team_id', $editionTeam->id)->whereIn('id', $uniqueIds)->count();

        if ($squadCount !== 11) {
            throw ValidationException::withMessages([
                'team_player_ids' => __('One or more selected players do not belong to this team\'s squad.'),
            ]);
        }

        return DB::transaction(function () use ($match, $editionTeam, $uniqueIds) {
            $existing = MatchPlayer::query()
                ->where('match_id', $match->id)
                ->whereHas('teamPlayer', fn ($query) => $query->where('edition_team_id', $editionTeam->id))
                ->lockForUpdate()
                ->get()
                ->keyBy('team_player_id');

            $toRemove = $existing->except($uniqueIds);

            // Defense-in-depth: canModifyPlayingXI() (checked by the
            // caller) already guarantees no Innings exists for this
            // match, which is the only thing that can ever create
            // scoring history — so this can never actually trigger
            // today. Kept anyway so a future change to that invariant
            // fails loudly here rather than silently deleting scored
            // history.
            foreach ($toRemove as $matchPlayer) {
                if ($this->hasDeliveryHistory($matchPlayer)) {
                    throw ValidationException::withMessages([
                        'team_player_ids' => __('A previously selected player already has scoring history and cannot be removed.'),
                    ]);
                }
            }

            foreach ($toRemove as $matchPlayer) {
                $matchPlayer->delete();
            }

            foreach ($uniqueIds as $teamPlayerId) {
                if (! $existing->has($teamPlayerId)) {
                    MatchPlayer::create(['match_id' => $match->id, 'team_player_id' => $teamPlayerId]);
                }
            }

            return MatchPlayer::query()
                ->where('match_id', $match->id)
                ->whereHas('teamPlayer', fn ($query) => $query->where('edition_team_id', $editionTeam->id))
                ->with('teamPlayer')
                ->get();
        });
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
