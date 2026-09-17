<?php

namespace App\Services\Contributor;

use App\Models\Contributor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Photo lifecycle for the general Contributor directory (Phase 3.40),
 * mirroring PlayerService's proven store-then-write, clean-up-on-failure
 * pattern exactly. ContributorController previously wrote directly to
 * the model since there was no file involved; this is the smallest
 * addition needed now that one exists — no generic media architecture.
 */
class ContributorService
{
    private const PHOTO_DIRECTORY = 'contributors';

    /**
     * A database write and a filesystem write are not part of the same
     * transaction — a DB failure does not undo a file that was already
     * stored, so the photo is stored first and explicitly cleaned up if
     * the subsequent create fails.
     */
    public function createContributor(array $data, ?UploadedFile $photo = null): Contributor
    {
        $storedPath = null;

        if ($photo) {
            $storedPath = $this->storePhoto($photo);
            $data['photo_path'] = $storedPath;
        }

        try {
            return Contributor::create($data);
        } catch (Throwable $e) {
            if ($storedPath) {
                $this->deletePhoto($storedPath);
            }

            throw $e;
        }
    }

    /**
     * Photo replacement follows the required sequence: store the new
     * photo, update the contributor, and only then remove the old
     * photo. If the update fails, the just-uploaded new photo is
     * removed and the old photo (still referenced by the unchanged row)
     * is left alone.
     */
    public function updateContributor(Contributor $contributor, array $data, ?UploadedFile $photo = null): Contributor
    {
        $oldPath = $contributor->photo_path;
        $newPath = null;

        if ($photo) {
            $newPath = $this->storePhoto($photo);
            $data['photo_path'] = $newPath;
        }

        try {
            $contributor->update($data);
        } catch (Throwable $e) {
            if ($newPath) {
                $this->deletePhoto($newPath);
            }

            throw $e;
        }

        if ($newPath && $oldPath) {
            $this->deletePhoto($oldPath);
        }

        return $contributor;
    }

    /**
     * Preserves the existing historical-protection rule unchanged: a
     * Contributor with contribution history cannot be deleted at all.
     * The photo is only removed after the row delete has actually
     * succeeded, never before — a blocked deletion must never touch the
     * file.
     */
    public function deleteContributor(Contributor $contributor): bool
    {
        if ($contributor->contributions()->exists()) {
            return false;
        }

        $photoPath = $contributor->photo_path;

        if (! $contributor->delete()) {
            return false;
        }

        if ($photoPath) {
            $this->deletePhoto($photoPath);
        }

        return true;
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
