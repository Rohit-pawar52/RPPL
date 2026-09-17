<?php

namespace App\Services\Team;

use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TeamService
{
    private const LOGO_DIRECTORY = 'teams';

    /**
     * A database write and a filesystem write are not part of the same
     * transaction — a DB rollback does not undo a file that was already
     * stored. So the logo is stored first, and explicitly cleaned up if
     * the subsequent DB write fails (mirrors PlayerService exactly).
     */
    public function createTeam(array $data, ?UploadedFile $logo = null): Team
    {
        $storedPath = null;

        if ($logo) {
            $storedPath = $this->storeLogo($logo);
            $data['logo_path'] = $storedPath;
        }

        try {
            return Team::create($data);
        } catch (Throwable $e) {
            if ($storedPath) {
                $this->deleteLogo($storedPath);
            }

            throw $e;
        }
    }

    /**
     * Logo replacement follows the required sequence: store the new
     * logo, update the team, and only then remove the old logo. If the
     * update fails, the just-uploaded new logo is removed and the old
     * logo (still referenced by the unchanged row) is left alone.
     */
    public function updateTeam(Team $team, array $data, ?UploadedFile $logo = null): Team
    {
        $oldPath = $team->logo_path;
        $newPath = null;

        if ($logo) {
            $newPath = $this->storeLogo($logo);
            $data['logo_path'] = $newPath;
        }

        try {
            $team->update($data);
        } catch (Throwable $e) {
            if ($newPath) {
                $this->deleteLogo($newPath);
            }

            throw $e;
        }

        if ($newPath && $oldPath) {
            $this->deleteLogo($oldPath);
        }

        return $team;
    }

    /**
     * Deletes a team only when it has no tournament history (any
     * EditionTeam participation). Returns false instead of letting the
     * FK constraint fail, so the controller can show a friendly
     * message. The logo is only removed after the row delete has
     * actually succeeded, never before.
     *
     * Wrapped in a transaction with a row lock, mirroring
     * PlayerService::deletePlayer(): EditionTeamController::store() is a
     * live admin write path, and edition_teams.team_id cascade-deletes
     * on Team deletion, so a concurrent EditionTeam creation between an
     * unlocked check and the delete would be silently destroyed.
     */
    public function deleteTeam(Team $team): bool
    {
        return DB::transaction(function () use ($team) {
            $locked = Team::whereKey($team->getKey())->lockForUpdate()->firstOrFail();

            if ($this->hasTournamentHistory($locked)) {
                return false;
            }

            $logoPath = $locked->logo_path;

            if (! $locked->delete()) {
                return false;
            }

            if ($logoPath) {
                $this->deleteLogo($logoPath);
            }

            return true;
        });
    }

    public function hasTournamentHistory(Team $team): bool
    {
        return $team->editionTeams()->exists();
    }

    /**
     * UploadedFile::store() already generates a random, non-guessable
     * filename (not derived from the original filename), so no extra
     * sanitization is needed here.
     */
    private function storeLogo(UploadedFile $logo): string
    {
        return $logo->store(self::LOGO_DIRECTORY, 'public');
    }

    private function deleteLogo(string $path): void
    {
        Storage::disk('public')->delete($path);
    }
}
