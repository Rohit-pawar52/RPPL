<?php

namespace App\Services\EditionTeam;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Team;
use App\Services\Team\TeamService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditionTeamService
{
    public function __construct(private readonly TeamService $teams) {}

    /**
     * StoreEditionTeamRequest already checks eligibility (active team,
     * non-completed edition, no existing edition+team pair) before this
     * runs, so a single INSERT needs no transaction. The database's own
     * UNIQUE(edition_id, team_id) constraint remains the final guarantee
     * against a genuine race between two concurrent requests for the
     * same pair — caught here only to turn that rare case into the same
     * friendly message instead of a raw SQL error (mirrors
     * PlayerRegistrationService::createRegistration()).
     */
    public function createEditionTeam(array $data): EditionTeam
    {
        try {
            return EditionTeam::create($data);
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                throw ValidationException::withMessages([
                    'team_id' => __('This team is already participating in the selected edition.'),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Adds several existing teams to an edition at once. Teams that are
     * inactive, unknown or already in the edition are skipped (the form
     * only offers valid ones; this keeps a stale form from failing the
     * whole batch). Returns how many were added.
     *
     * @param  list<int>  $teamIds
     */
    public function addTeams(Edition $edition, array $teamIds): int
    {
        return DB::transaction(function () use ($edition, $teamIds) {
            $existing = $edition->editionTeams()->pluck('team_id')->all();

            $teams = Team::active()->whereIn('id', $teamIds)->whereNotIn('id', $existing)->get();

            foreach ($teams as $team) {
                $this->createEditionTeam(['edition_id' => $edition->id, 'team_id' => $team->id]);
            }

            return $teams->count();
        });
    }

    /**
     * Creates a brand-new team (name, short name, logo) and puts it in
     * the edition in one step.
     *
     * @param  array{name: string, short_name?: ?string}  $data
     */
    public function createTeamAndAdd(Edition $edition, array $data, ?UploadedFile $logo = null): EditionTeam
    {
        $team = $this->teams->createTeam($data, $logo);

        return $this->createEditionTeam(['edition_id' => $edition->id, 'team_id' => $team->id]);
    }

    /**
     * Removes a team from an edition, with its squad (the players'
     * registrations stay; only their place in this team goes). Allowed
     * only while no match exists for the team (as either side, or as the
     * batting/bowling team of an innings) — once matches exist the
     * history must stay. Returns false instead of letting an FK
     * constraint fail, so the controller can show a friendly message.
     *
     * Wrapped in a transaction with a row lock, mirroring
     * PlayerService::deletePlayer(): the squad cascade-deletes with the
     * EditionTeam, so the match check and the delete must not be
     * separated by a concurrent write.
     */
    public function deleteEditionTeam(EditionTeam $editionTeam): bool
    {
        return DB::transaction(function () use ($editionTeam) {
            $locked = EditionTeam::whereKey($editionTeam->getKey())->lockForUpdate()->firstOrFail();

            if ($this->hasMatchUsage($locked)) {
                return false;
            }

            return (bool) $locked->delete();
        });
    }

    /**
     * toss_winner_team_id/winner_team_id on matches are intentionally
     * not checked separately here: for any match created through this
     * application's own logic, a team can only win a toss or a match it
     * is actually playing in, so those columns are always a subset of
     * edition_team_a_id/edition_team_b_id. (The database itself does
     * not enforce that invariant — a known deferred validation rule
     * from the Phase 1 review — but no admin-writable path exists yet
     * that could violate it.)
     */
    public function hasMatchUsage(EditionTeam $editionTeam): bool
    {
        return $editionTeam->matchesAsTeamA()->exists()
            || $editionTeam->matchesAsTeamB()->exists()
            || $editionTeam->inningsAsBattingTeam()->exists()
            || $editionTeam->inningsAsBowlingTeam()->exists();
    }
}
