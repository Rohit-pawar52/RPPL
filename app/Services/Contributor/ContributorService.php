<?php

namespace App\Services\Contributor;

use App\Models\Contributor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $data = $this->tidy($data);
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
        $data = $this->tidy($data);
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
     * Existing contributors who may be the same person as "name, village" - the check behind "this one is
     * already in the list" when somebody is about to be added. A match is the same name (ignoring capitals
     * and extra spaces) AND either the same village or no village recorded on one side: an older contributor
     * with no village is quite possibly the person being typed in again. Inactive ones count too, because
     * the right move may be to switch them back on.
     *
     * @return Collection<int, Contributor>
     */
    public function possibleDuplicates(string $name, ?string $village, ?int $exceptId = null): Collection
    {
        $name = Str::lower(Str::squish($name));
        $village = Str::lower(Str::squish((string) $village));

        if ($name === '') {
            return new Collection;
        }

        return Contributor::query()
            ->whereRaw('LOWER(name) = ?', [$name])
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'village', 'is_active'])
            ->filter(function (Contributor $existing) use ($village) {
                $theirs = Str::lower(Str::squish((string) $existing->village));

                return $theirs === '' || $village === '' || $theirs === $village;
            })
            ->values();
    }

    /**
     * The message shown when somebody is about to be added who may already be in the list.
     *
     * @param  Collection<int, Contributor>  $matches
     */
    public static function duplicateMessage(Collection $matches): string
    {
        $first = $matches->first();
        $who = trim((string) $first->village) === ''
            ? $first->name.' (no village recorded)'
            : $first->name.' — '.$first->village;
        $more = $matches->count() > 1 ? ' and '.($matches->count() - 1).' more like them' : '';

        return "{$who}{$more} is already a contributor. Use that one from the list, or tick \"This is a different person\" and save again to add another.";
    }

    /**
     * Names, villages and the like are typed by hand at the ground: strip the stray and doubled spaces so the
     * same person is not stored in two spellings, and keep an empty optional field as NULL.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function tidy(array $data): array
    {
        foreach (['name', 'village', 'address', 'phone'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = Str::squish($data[$field]);

                if ($data[$field] === '' && $field !== 'name') {
                    $data[$field] = null;
                }
            }
        }

        return $data;
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
