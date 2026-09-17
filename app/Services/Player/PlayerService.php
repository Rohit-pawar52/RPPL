<?php

namespace App\Services\Player;

use App\Models\Player;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PlayerService
{
    private const PHOTO_DIRECTORY = 'players';

    /**
     * A database write and a filesystem write are not part of the same
     * transaction — a DB rollback does not undo a file that was already
     * stored. So the photo is stored first, and explicitly cleaned up
     * if the subsequent DB write fails, rather than relying on
     * DB::transaction() to cover it.
     */
    public function createPlayer(array $data, ?UploadedFile $photo = null): Player
    {
        $storedPath = null;

        if ($photo) {
            $storedPath = $this->storePhoto($photo);
            $data['photo_path'] = $storedPath;
        }

        try {
            return Player::create($data);
        } catch (Throwable $e) {
            if ($storedPath) {
                $this->deletePhoto($storedPath);
            }

            throw $e;
        }
    }

    /**
     * Photo replacement follows the required sequence: store the new
     * photo, update the player, and only then remove the old photo. If
     * the update fails, the just-uploaded new photo is removed and the
     * old photo (still referenced by the unchanged row) is left alone.
     */
    public function updatePlayer(Player $player, array $data, ?UploadedFile $photo = null): Player
    {
        $oldPath = $player->photo_path;
        $newPath = null;

        if ($photo) {
            $newPath = $this->storePhoto($photo);
            $data['photo_path'] = $newPath;
        }

        try {
            $player->update($data);
        } catch (Throwable $e) {
            if ($newPath) {
                $this->deletePhoto($newPath);
            }

            throw $e;
        }

        if ($newPath && $oldPath) {
            $this->deletePhoto($oldPath);
        }

        return $player;
    }

    /**
     * Deletes a player only when no tournament history (any
     * PlayerRegistration) exists for them. Returns false instead of
     * letting a foreign-key constraint fail, so the controller can show
     * a friendly message. The photo is only removed after the row
     * delete has actually succeeded, never before.
     *
     * Wrapped in a transaction with a row lock so a concurrent request
     * cannot create a registration between the eligibility check and
     * the delete itself.
     */
    public function deletePlayer(Player $player): bool
    {
        return DB::transaction(function () use ($player) {
            $locked = Player::whereKey($player->getKey())->lockForUpdate()->firstOrFail();

            if ($this->hasTournamentHistory($locked)) {
                return false;
            }

            $photoPath = $locked->photo_path;

            if (! $locked->delete()) {
                return false;
            }

            if ($photoPath) {
                $this->deletePhoto($photoPath);
            }

            return true;
        });
    }

    public function hasTournamentHistory(Player $player): bool
    {
        return $player->playerRegistrations()->exists();
    }

    /**
     * UploadedFile::store() already generates a random, non-guessable
     * filename (not derived from the original filename), so no extra
     * sanitization is needed here.
     */
    private function storePhoto(UploadedFile $photo): string
    {
        return $photo->store(self::PHOTO_DIRECTORY, 'public');
    }

    private function deletePhoto(string $path): void
    {
        Storage::disk('public')->delete($path);
    }
}
